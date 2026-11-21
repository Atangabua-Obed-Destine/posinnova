<?php
/*
 * I-NNOVA POS: shown when the application has no .env yet (app/Http/Middleware/IsInstalled.php
 * sends visitors here). Plain PHP on purpose: Laravel cannot run before the installation.
 */
$phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$exts = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'];
$missing = array_values(array_filter($exts, fn ($e) => ! extension_loaded($e)));
$base = dirname(__DIR__, 2);
$vendor = is_file($base.'/vendor/autoload.php');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install I-NNOVA POS</title>
<style>
  body { margin: 0; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f6fb; color: #101828; }
  .wrap { max-width: 640px; margin: 48px auto; padding: 0 18px; }
  .brand { font-weight: 800; font-size: 22px; color: #0b1f4d; letter-spacing: .01em; }
  .brand small { display: block; font-weight: 500; font-size: 13px; color: #667085; }
  .card { background: #fff; border: 1px solid #e4e7ec; border-radius: 14px; padding: 22px 24px; margin-top: 18px; }
  h1 { font-size: 20px; margin: 0 0 8px; }
  pre { background: #0f172a; color: #e2e8f0; padding: 12px 14px; border-radius: 8px; overflow: auto; font-size: 13px; }
  ol { padding-left: 20px; } li { margin: 6px 0; }
  .ok { color: #067647; } .bad { color: #b42318; }
  .muted { color: #667085; font-size: 13px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">I-NNOVA POS<small>Powered by I-NNOVA</small></div>

  <div class="card">
    <h1>Not installed yet</h1>
    <p>I-NNOVA POS is installed from a terminal (PowerShell or Command Prompt on Windows), in the folder of the code:</p>
    <ol>
      <li>Get the libraries (once): <pre>composer install --no-dev</pre></li>
      <li>Run the installer and answer its questions: <pre>php artisan innova:install</pre></li>
      <li>Open the link it prints at the end, to create the business and its owner.</li>
    </ol>
    <p class="muted">The full guide is <b>INSTALL.md</b>, in the folder of the code.</p>
  </div>

  <div class="card">
    <h1>This computer</h1>
    <p class="<?= $phpOk ? 'ok' : 'bad' ?>">PHP <?= htmlspecialchars(PHP_VERSION) ?> <?= $phpOk ? '✓' : '— 8.1 or newer is needed' ?></p>
    <?php if ($missing) { ?>
      <p class="bad">PHP extensions to turn on in php.ini: <b><?= htmlspecialchars(implode(', ', $missing)) ?></b> (remove the ; before each extension= line, then restart Apache).</p>
    <?php } else { ?>
      <p class="ok">PHP extensions ✓</p>
    <?php } ?>
    <p class="<?= $vendor ? 'ok' : 'bad' ?>"><?= $vendor ? 'Libraries installed ✓' : 'Libraries not installed yet: run composer install --no-dev (step 1)' ?></p>
  </div>
</div>
</body>
</html>
