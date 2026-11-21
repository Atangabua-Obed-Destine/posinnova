<?php

namespace App\Console\Commands;

use App\Utils\InnovaSetup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Updates an installation: backs up the database, gets the new code from
 * GitHub, installs new libraries if they changed, updates the tables and the
 * I-NNOVA superadmin, and clears the caches.
 */
class InnovaUpdate extends Command
{
    protected $signature = 'innova:update
        {--no-backup : Skip the database backup}
        {--no-pull : Do not get new code (it was copied by hand)}';

    protected $description = 'Update this I-NNOVA POS installation to the latest version';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>I-NNOVA POS</> update');
        $this->newLine();

        if (! $this->option('no-backup')) {
            $file = $this->backup();
            if ($file === null) {
                $this->error('  The backup failed, so nothing was updated. Run with --no-backup only if you have another backup.');

                return self::FAILURE;
            }
            $this->line('  Backup: '.$file);
        }

        $lockBefore = $this->hashOf(base_path('composer.lock'));

        if (! $this->option('no-pull')) {
            if (! is_dir(base_path('.git'))) {
                $this->error('  This folder was not cloned with git: copy the new code by hand, then run: php artisan innova:update --no-pull');

                return self::FAILURE;
            }
            $this->output->write('  Getting the new code ... ');
            $git = $this->run(['git', 'pull', '--ff-only']);
            if (! $git->isSuccessful()) {
                $this->line('<fg=red>FAILED</>');
                $this->line('  '.trim($git->getErrorOutput() ?: $git->getOutput()));
                $this->line('  (If files were changed on this computer, "git status" shows which.)');

                return self::FAILURE;
            }
            $this->line('<fg=green>done</>');
        }

        if ($this->hashOf(base_path('composer.lock')) !== $lockBefore) {
            $composer = (new ExecutableFinder())->find('composer');
            $this->output->write('  Installing new libraries ... ');
            if (! $composer) {
                $this->line('<fg=red>FAILED</>');
                $this->error('  Composer is needed: install it, then run "composer install --no-dev" in this folder and this command again with --no-pull.');

                return self::FAILURE;
            }
            $c = $this->run([$composer, 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], 1800);
            $this->line($c->isSuccessful() ? '<fg=green>done</>' : '<fg=red>FAILED</>');
            if (! $c->isSuccessful()) {
                $this->line('  '.trim($c->getErrorOutput()));

                return self::FAILURE;
            }
        }

        // New code: the steps below run in a fresh PHP, so they see it
        $this->output->write('  Updating the tables ... ');
        $m = $this->run([PHP_BINARY, '-d', 'memory_limit=1024M', 'artisan', 'migrate', '--force'], 1800);
        $this->line($m->isSuccessful() ? '<fg=green>done</>' : '<fg=red>FAILED</>');
        if (! $m->isSuccessful()) {
            $this->line('  '.trim($m->getErrorOutput() ?: $m->getOutput()));

            return self::FAILURE;
        }

        if (InnovaSetup::passwordHash() !== null || $this->option('no-pull')) {
            $s = $this->run([PHP_BINARY, 'artisan', 'innova:superadmin']);
            $this->line('  '.trim($s->getOutput()));
        }

        $cached = file_exists(base_path('bootstrap/cache/config.php'));
        foreach (['view:clear', 'route:clear', 'cache:clear', 'config:clear'] as $c) {
            $this->run([PHP_BINARY, 'artisan', $c]);
        }
        if ($cached) {
            $this->run([PHP_BINARY, 'artisan', 'config:cache']);
        }

        $this->newLine();
        $this->info('  Up to date.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function run(array $command, int $timeout = 600): Process
    {
        $p = new Process($command, base_path(), null, null, $timeout);
        $p->run();

        return $p;
    }

    private function hashOf(string $file): ?string
    {
        return is_file($file) ? md5_file($file) : null;
    }

    /** mysqldump of the database into storage/backups; the file, or null */
    private function backup(): ?string
    {
        $finder = new ExecutableFinder();
        $dump = $finder->find('mysqldump', null, ['C:\\xampp\\mysql\\bin', '/usr/bin', '/usr/local/bin']);
        if (! $dump) {
            $this->error('  mysqldump was not found.');

            return null;
        }

        $dir = storage_path('backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.DIRECTORY_SEPARATOR.config('database.connections.mysql.database').'_'.date('Ymd_His').'.sql';

        $db = config('database.connections.mysql');
        $this->output->write('  Backing up the database ... ');
        $p = new Process([$dump, '--single-transaction', '--no-tablespaces', '--routines',
            '-h', $db['host'], '-P', (string) $db['port'], '-u', $db['username'], '--result-file='.$file, $db['database']],
            base_path(), ['MYSQL_PWD' => (string) $db['password']], null, 1800);
        $p->run();

        if (! $p->isSuccessful() || ! is_file($file) || filesize($file) < 1000) {
            $this->line('<fg=red>FAILED</>');
            $this->line('  '.trim($p->getErrorOutput()));

            return null;
        }
        $this->line('<fg=green>done</>');

        return $file;
    }
}
