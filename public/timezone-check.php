<?php
/**
 * PosInnova — timezone diagnostic.
 *
 * TEMPORARY TROUBLESHOOTING TOOL. Open it in the browser on the affected PC:
 *     http://localhost/posinnova/public/timezone-check.php
 *
 * It compares every layer that can decide the time shown on the POS screen and
 * says which one disagrees. DELETE THIS FILE once the issue is resolved.
 */

header('Content-Type: text/html; charset=utf-8');

$root = dirname(__DIR__);
$rows = [];
$problems = [];
$notes = [];

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// ---------------------------------------------------------------- 1. Windows
$win_tz = $win_local = $win_utc = null;
if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
    $ps = 'powershell -NoProfile -Command "(Get-TimeZone).Id; (Get-Date).ToString(\'yyyy-MM-dd HH:mm:ss\'); '
        . '(Get-Date).ToUniversalTime().ToString(\'yyyy-MM-dd HH:mm:ss\')"';
    $out = @shell_exec($ps);
    if ($out) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $out)), 'strlen'));
        $win_tz    = $lines[0] ?? null;
        $win_local = $lines[1] ?? null;
        $win_utc   = $lines[2] ?? null;
    }
}

// ---------------------------------------------------------------- 2. PHP
$ini_path   = php_ini_loaded_file();
$ini_tz     = ini_get('date.timezone');
$php_tz     = date_default_timezone_get();
$php_utc    = gmdate('Y-m-d H:i:s');

// What PHP renders if it believes it is in Douala.
$prev = date_default_timezone_get();
date_default_timezone_set('Africa/Douala');
$php_douala = date('Y-m-d H:i:s');
date_default_timezone_set($prev);

// ---------------------------------------------------------------- 3. Laravel
$app_tz = $env_tz = $carbon_now = $config_cached = null;
$db_rows = [];
$session_tzs = [];

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $app_tz     = config('app.timezone');
    $env_tz     = env('APP_TIMEZONE');
    $carbon_now = \Carbon\Carbon::now()->format('Y-m-d H:i:s T (P)');
    $config_cached = file_exists($root . '/bootstrap/cache/config.php');

    // Business rows, straight from the database.
    foreach (\Illuminate\Support\Facades\DB::table('business')->select('id', 'name', 'time_zone')->get() as $b) {
        $db_rows[] = $b;
    }

    // MySQL's own clock.
    $my = \Illuminate\Support\Facades\DB::select(
        'select @@global.time_zone g, @@session.time_zone s, NOW() n, UTC_TIMESTAMP() u'
    )[0];
} catch (\Throwable $e) {
    $notes[] = 'Could not bootstrap Laravel: ' . $e->getMessage();
    $my = null;
}

// ------------------------------------------------- 4. Stale sessions on disk
// SetSessionData stores the business (with its time_zone) in the session ONCE,
// at login. A session opened before the setting was corrected keeps the old
// zone until that user logs out and back in.
$sess_dir = $root . '/storage/framework/sessions';
if (is_dir($sess_dir)) {
    foreach (glob($sess_dir . '/*') as $f) {
        if (!is_file($f)) { continue; }
        $blob = @file_get_contents($f);
        if ($blob === false) { continue; }
        if (preg_match_all('/"time_zone";s:\d+:"([^"]+)"/', $blob, $m)) {
            foreach ($m[1] as $tz) {
                $key = $tz;
                $session_tzs[$key] = ($session_tzs[$key] ?? 0) + 1;
            }
        }
    }
}

// ---------------------------------------------------------------- 5. Verdict
$expected = 'Africa/Douala';

if ($win_tz !== null && $win_local !== null) {
    // The decisive comparison: what Windows shows on the taskbar, versus what
    // PHP computes from the machine's UTC clock for Douala. If the clock was
    // corrected by hand while the Windows timezone stayed wrong, these differ.
    $a = strtotime($win_local);
    $b = strtotime($php_douala);
    $drift = abs($a - $b);
    if ($drift > 90) {
        $mins = round($drift / 60);
        $problems[] = [
            'Windows clock and PHP disagree by about ' . $mins . ' minutes',
            'The taskbar shows <b>' . h($win_local) . '</b> but PHP computes <b>' . h($php_douala)
            . '</b> for Africa/Douala from this machine\'s UTC clock (' . h($win_utc) . ' UTC). '
            . 'This is the classic case of a <b>wrong Windows time zone with the clock corrected by hand</b>: '
            . 'the displayed time looks right, but the underlying UTC value is off, so every application that '
            . 'converts UTC to a time zone — including the POS — renders the wrong time. '
            . 'Windows time zone is currently <b>' . h($win_tz) . '</b>.',
        ];
    }
    if (stripos((string) $win_tz, 'W. Central Africa') === false) {
        $notes[] = 'Windows time zone is "' . h($win_tz) . '". For Douala it should be '
            . '"W. Central Africa Standard Time" (UTC+01:00).';
    }
}

foreach ($session_tzs as $tz => $count) {
    if ($tz !== $expected) {
        $problems[] = [
            'A logged-in session is still using ' . h($tz),
            $count . ' active session file(s) hold <b>' . h($tz) . '</b> instead of <b>' . h($expected) . '</b>. '
            . 'The business details — including the time zone — are copied into the session <b>once, at login</b>, '
            . 'and are not refreshed afterwards. If the time zone was corrected in Business Settings while this '
            . 'user was already signed in, the settings page shows the new value while the POS keeps using the old '
            . 'one. <b>Fix: that user logs out and logs back in.</b>',
        ];
    }
}

foreach ($db_rows as $b) {
    if ($b->time_zone !== $expected) {
        $notes[] = 'Business "' . h($b->name) . '" (id ' . (int) $b->id . ') is set to "' . h($b->time_zone) . '".';
    }
}

if ($config_cached) {
    $notes[] = 'bootstrap/cache/config.php exists — configuration is cached. If APP_TIMEZONE was changed in .env '
        . 'since it was built, the cached value is still in force. Run: php artisan config:clear';
}

if ($ini_tz && $ini_tz !== $expected) {
    $notes[] = 'php.ini sets date.timezone = "' . h($ini_tz) . '" (' . h($ini_path) . '). Laravel overrides this '
        . 'for web requests, so it is not usually the cause, but setting it to Africa/Douala removes a trap.';
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Timezone check</title>
<style>
  body { font: 15px/1.6 system-ui, "Segoe UI", sans-serif; margin: 0; background: #f4f6f7; color: #16211f; }
  .wrap { max-width: 880px; margin: 0 auto; padding: 28px 20px 60px; }
  h1 { font-size: 24px; margin: 0 0 4px; }
  .sub { color: #5d6b68; margin: 0 0 26px; font-size: 14px; }
  h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .08em; color: #5d6b68; margin: 28px 0 8px; }
  table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #dde3e1; }
  td, th { text-align: left; padding: 9px 13px; border-bottom: 1px solid #eef2f1; vertical-align: top; }
  tr:last-child td { border-bottom: 0; }
  th { width: 240px; font-weight: 600; color: #3b4a47; }
  code, .mono { font-family: ui-monospace, Consolas, monospace; font-size: 13.5px; }
  .bad { background: #fdecea; border: 1px solid #f3b8b1; border-left: 4px solid #c0392b; padding: 14px 16px; margin: 0 0 12px; border-radius: 3px; }
  .bad b.t { display: block; color: #96271b; font-size: 15px; margin-bottom: 5px; }
  .ok { background: #e8f6ef; border: 1px solid #a9ddc6; border-left: 4px solid #12855c; padding: 14px 16px; border-radius: 3px; }
  .note { background: #fff8e6; border: 1px solid #f0dcaa; border-left: 4px solid #b9861a; padding: 11px 15px; margin: 0 0 9px; border-radius: 3px; font-size: 14px; }
  .warnfoot { margin-top: 30px; font-size: 13px; color: #8a4b12; background: #fdf1e2; border: 1px solid #f0d5b4; padding: 11px 15px; border-radius: 3px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>PosInnova timezone check</h1>
  <p class="sub">Run on the machine showing the wrong time. Every layer that can decide the POS clock, compared.</p>

  <h2>Verdict</h2>
  <?php if ($problems): ?>
    <?php foreach ($problems as $p): ?>
      <div class="bad"><b class="t"><?= $p[0] ?></b><?= $p[1] ?></div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="ok"><b>No mismatch detected between the layers.</b> Windows, PHP, Laravel, the database and the
      active sessions all agree. If the POS still shows a wrong time, note the exact wrong value and how far off
      it is, then check the POS setting <em>"Enable transaction date"</em> — when it is on, the date field is
      re-stamped from the browser after each sale.</div>
  <?php endif; ?>

  <?php foreach ($notes as $n): ?>
    <div class="note"><?= $n ?></div>
  <?php endforeach; ?>

  <h2>Windows</h2>
  <table>
    <tr><th>Time zone</th><td class="mono"><?= h($win_tz ?? 'n/a') ?></td></tr>
    <tr><th>Local time (taskbar)</th><td class="mono"><?= h($win_local ?? 'n/a') ?></td></tr>
    <tr><th>UTC time</th><td class="mono"><?= h($win_utc ?? 'n/a') ?></td></tr>
  </table>

  <h2>PHP</h2>
  <table>
    <tr><th>php.ini in use</th><td class="mono"><?= h($ini_path ?: 'none') ?></td></tr>
    <tr><th>date.timezone</th><td class="mono"><?= h($ini_tz ?: '(not set)') ?></td></tr>
    <tr><th>Default timezone</th><td class="mono"><?= h($php_tz) ?></td></tr>
    <tr><th>PHP UTC now</th><td class="mono"><?= h($php_utc) ?></td></tr>
    <tr><th>PHP as Africa/Douala</th><td class="mono"><b><?= h($php_douala) ?></b></td></tr>
  </table>

  <h2>Application</h2>
  <table>
    <tr><th>config('app.timezone')</th><td class="mono"><?= h($app_tz ?? 'n/a') ?></td></tr>
    <tr><th>APP_TIMEZONE in .env</th><td class="mono"><?= h($env_tz ?? '(not set)') ?></td></tr>
    <tr><th>Config cached?</th><td class="mono"><?= $config_cached ? 'YES — bootstrap/cache/config.php' : 'no' ?></td></tr>
    <tr><th>Carbon::now()</th><td class="mono"><b><?= h($carbon_now ?? 'n/a') ?></b></td></tr>
  </table>

  <h2>Business settings (from the database)</h2>
  <table>
    <tr><th>Business</th><th style="width:auto">Time zone</th></tr>
    <?php foreach ($db_rows as $b): ?>
      <tr><td><?= h($b->name) ?> <span class="mono">(id <?= (int) $b->id ?>)</span></td>
          <td class="mono"><?= h($b->time_zone) ?></td></tr>
    <?php endforeach; ?>
  </table>

  <h2>Active login sessions</h2>
  <table>
    <?php if ($session_tzs): foreach ($session_tzs as $tz => $c): ?>
      <tr><th class="mono"><?= h($tz) ?></th><td><?= (int) $c ?> session file(s)</td></tr>
    <?php endforeach; else: ?>
      <tr><td colspan="2">No stored session timezone found (nobody logged in, or sessions are not file-based).</td></tr>
    <?php endif; ?>
  </table>

  <h2>MySQL</h2>
  <table>
    <?php if ($my): ?>
      <tr><th>Global / session zone</th><td class="mono"><?= h($my->g) ?> / <?= h($my->s) ?></td></tr>
      <tr><th>NOW()</th><td class="mono"><?= h($my->n) ?></td></tr>
      <tr><th>UTC_TIMESTAMP()</th><td class="mono"><?= h($my->u) ?></td></tr>
    <?php else: ?>
      <tr><td>Database not reachable.</td></tr>
    <?php endif; ?>
  </table>

  <p class="warnfoot"><b>Delete this file when you are done.</b> It reveals server configuration and should not
  stay in a live installation: <span class="mono"><?= h(__FILE__) ?></span></p>
</div>
</body>
</html>
