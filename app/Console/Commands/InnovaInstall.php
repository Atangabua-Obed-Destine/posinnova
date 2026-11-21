<?php

namespace App\Console\Commands;

use App\Utils\InnovaSetup;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;

/**
 * Prepares a freshly cloned server for one client: settings, tables, the
 * I-NNOVA superadmin, then a one-time link where the client's business and
 * its owner are created in a browser.
 *
 * Safe to run again after a failure; it never touches an installation that
 * already has a client business.
 */
class InnovaInstall extends Command
{
    protected $signature = 'innova:install
        {--url= : The address the shop\'s computers will use to open I-NNOVA POS}
        {--db-host= : Database server}
        {--db-port= : Database port}
        {--db-database= : Database name, created if it does not exist}
        {--db-username= : Database user}
        {--db-password= : Database password}';

    protected $description = 'Install I-NNOVA POS for a new client on this computer';

    private const REQUIRED_EXTENSIONS = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'json', 'mbstring', 'openssl', 'pdo_mysql',
        'tokenizer', 'xml', 'zip',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>I-NNOVA POS</> installation');
        $this->newLine();

        // A cached configuration would ignore everything written to .env below.
        $this->callSilent('config:clear');

        if ($problems = $this->serverProblems()) {
            $this->error('  This computer is not ready yet:');
            foreach ($problems as $p) {
                $this->line('   - '.$p);
            }
            $this->newLine();

            return self::FAILURE;
        }

        $url = $this->askUrl();

        $database = $this->connectToDatabase();
        if ($database === null) {
            return self::FAILURE;
        }

        if ($reason = $this->whyDatabaseCannotBeUsed()) {
            $this->error('  '.$reason.' Nothing was changed.');

            return self::FAILURE;
        }

        $this->writeEnvironment($url, $database);

        $this->newLine();
        $ok = $this->step('Creating the tables (about a minute)', fn () => $this->migrate())
            && $this->step('Adding permissions, currencies and barcode types', fn () => $this->seed())
            && $this->step('Preparing the folders and keys', fn () => $this->prepareFolders())
            && $this->step('Creating the I-NNOVA superadmin ('.InnovaSetup::username().')', fn () => (bool) InnovaSetup::ensureSuperadmin())
            && $this->step('Adding the I-NNOVA package', function () {
                InnovaSetup::ensurePackage();

                return true;
            });

        if (! $ok) {
            $this->newLine();
            $this->error('  The installation stopped. Fix the problem above, then run the same command again.');

            return self::FAILURE;
        }

        $this->callSilent('view:clear');
        $this->callSilent('cache:clear');

        $link = $url.'/setup/'.InnovaSetup::issueToken();

        $this->newLine();
        $this->info('  The computer is ready. One step left, in a browser:');
        $this->newLine();
        $this->line('  <options=bold>'.$link.'</>');
        $this->newLine();
        $this->line('  Open it to name the client\'s business and create its owner account.');
        $this->line('  The link works once, for 7 days. If it gets lost, run this command again for a new one.');
        $this->line('  Superadmin: '.InnovaSetup::username().' (the usual I-NNOVA password).');
        $this->newLine();

        return self::SUCCESS;
    }

    private function step(string $title, callable $task): bool
    {
        $this->output->write('  '.$title.' ... ');
        try {
            $ok = (bool) $task();
        } catch (\Throwable $e) {
            $this->line('<fg=red>FAILED</>');
            $this->line('  <fg=red>'.$e->getMessage().'</>');

            return false;
        }
        $this->line($ok ? '<fg=green>done</>' : '<fg=red>FAILED</>');

        return $ok;
    }

    /** @return array<int, string> */
    private function serverProblems(): array
    {
        $problems = [];

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $problems[] = 'PHP 8.1 or newer is required. This is PHP '.PHP_VERSION.'.';
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (! extension_loaded($extension)) {
                $problems[] = "The PHP extension \"{$extension}\" is off. Turn it on in ".(php_ini_loaded_file() ?: 'php.ini').' (remove the ; before extension='.$extension.'), then restart Apache.';
            }
        }

        foreach ([storage_path(), base_path('bootstrap/cache')] as $folder) {
            if (! is_dir($folder) || ! is_writable($folder)) {
                $problems[] = "The folder {$folder} must exist and be writable.";
            }
        }

        foreach (['public/css/app.css', 'public/js/vendor.js', 'public/fonts'] as $asset) {
            if (! file_exists(base_path($asset))) {
                $problems[] = "{$asset} is missing: this copy of the code is incomplete. Clone it again from GitHub.";
            }
        }

        if (InnovaSetup::passwordHash() === null) {
            $problems[] = 'The I-NNOVA superadmin password is not set in this copy of the code (config/innova.php). On the I-NNOVA computer run "php artisan innova:superadmin-password", commit and push, then get the code again.';
        }

        return $problems;
    }

    private function askUrl(): string
    {
        $url = $this->option('url') ?: $this->ask(
            'What address will the shop\'s computers use to open I-NNOVA POS?',
            $this->guessUrl()
        );

        return rtrim(str_contains($url, '://') ? $url : 'http://'.$url, '/');
    }

    /** XAMPP and plain Apache serve a cloned folder at <server>/<folder>/public. */
    private function guessUrl(): string
    {
        $ip = gethostbyname(gethostname());
        $inWebRoot = in_array(strtolower(basename(dirname(base_path()))), ['htdocs', 'www', 'html'], true);

        return 'http://'.$ip.($inWebRoot ? '/'.basename(base_path()).'/public' : '');
    }

    /** @return array{host: string, port: string, database: string, username: string, password: string}|null */
    private function connectToDatabase(): ?array
    {
        do {
            $database = [
                'host' => $this->option('db-host') ?: $this->ask('Database server', '127.0.0.1'),
                'port' => $this->option('db-port') ?: $this->ask('Database port', '3306'),
                'database' => $this->option('db-database') ?: $this->ask('Database name (created if it does not exist)', 'innova_pos'),
                'username' => $this->option('db-username') ?: $this->ask('Database user', 'root'),
                'password' => $this->option('db-password') ?? ((string) $this->secret('Database password (press Enter if there is none)')),
            ];

            $error = $this->openDatabase($database);

            if ($error === null) {
                config([
                    'database.default' => 'mysql',
                    'database.connections.mysql.host' => $database['host'],
                    'database.connections.mysql.port' => $database['port'],
                    'database.connections.mysql.database' => $database['database'],
                    'database.connections.mysql.username' => $database['username'],
                    'database.connections.mysql.password' => $database['password'],
                ]);
                DB::purge('mysql');
                DB::setDefaultConnection('mysql');

                return $database;
            }

            $this->error('  '.$error);
        } while ($this->input->isInteractive() && $this->confirm('Try different database details?', true));

        return null;
    }

    /** Connects, and creates the database if it is not there yet. */
    private function openDatabase(array $database): ?string
    {
        if (! preg_match('/^\w+$/', $database['database'])) {
            return 'A database name can only use letters, numbers and underscores.';
        }

        try {
            $pdo = new PDO(
                "mysql:host={$database['host']};port={$database['port']};charset=utf8mb4",
                $database['username'],
                $database['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            return 'Could not open the database: '.$e->getMessage();
        }

        return null;
    }

    private function whyDatabaseCannotBeUsed(): ?string
    {
        if (InnovaSetup::hasClientBusiness()) {
            return 'This database already holds a business, so it belongs to a running shop.';
        }

        // Empty, or an installation of ours that stopped part-way, can both continue.
        $tables = DB::select('SHOW TABLES');
        if ($tables !== [] && ! Schema::hasTable('migrations')) {
            return 'This database holds tables from something else. Choose an empty one (or a new name).';
        }

        return null;
    }

    private function writeEnvironment(string $url, array $database): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            File::copy(base_path('.env.example'), $path);
        }

        $values = [
            'APP_NAME' => '"'.config('innova.brand', 'I-NNOVA POS').'"',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'ADMINISTRATOR_USERNAMES' => InnovaSetup::username(),
            'ALLOW_REGISTRATION' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'] === '' ? '' : '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $database['password']).'"',
        ];

        // A key already set must stay: it opens everything encrypted with it.
        // \S, not .: on Windows the file may have CRLF line ends, and "." would take the \r for a key
        if (! preg_match('/^APP_KEY=\S+/m', File::get($path))) {
            $values['APP_KEY'] = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
            config(['app.key' => $values['APP_KEY']]);
        }

        self::setEnv($path, $values);
        config([
            'app.url' => $url,
            'constants.administrator_usernames' => InnovaSetup::username(),
        ]);
    }

    /** Replaces or appends KEY=value lines in a .env file */
    public static function setEnv(string $path, array $values): void
    {
        $content = File::get($path);
        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content)) {
                $content = preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $content, 1);
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }
        File::put($path, $content);
    }

    private function migrate(): bool
    {
        @ini_set('memory_limit', '1024M');
        DB::statement('SET default_storage_engine=INNODB;');

        return $this->callSilent('migrate', ['--force' => true]) === 0;
    }

    private function seed(): bool
    {
        // Running again after a failure must not add them twice.
        if (DB::table('permissions')->exists() && DB::table('currencies')->exists()) {
            return true;
        }

        return $this->callSilent('db:seed', ['--force' => true]) === 0;
    }

    private function prepareFolders(): bool
    {
        foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            File::ensureDirectoryExists(storage_path($dir));
        }
        foreach (['uploads/business_logos', 'uploads/invoice_logos', 'uploads/img', 'uploads/documents', 'uploads/media', 'uploads/temp'] as $dir) {
            File::ensureDirectoryExists(public_path($dir));
        }

        if (! file_exists(public_path('storage'))) {
            $this->callSilent('storage:link');
        }

        // The modules' screen files (public/modules)
        $this->callSilent('module:publish');

        // The API's signing keys (Laravel Passport), once
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->callSilent('passport:keys', ['--force' => true]);
        }

        return true;
    }
}
