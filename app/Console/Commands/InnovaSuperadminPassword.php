<?php

namespace App\Console\Commands;

use App\Utils\InnovaSetup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Sets the password of the I-NNOVA superadmin for every future installation.
 * Only a one-way hash is written (config/innova.php), never the password:
 * the repository is public.
 */
class InnovaSuperadminPassword extends Command
{
    protected $signature = 'innova:superadmin-password';

    protected $description = 'Set the password the I-NNOVA superadmin has on every installation';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  The superadmin <options=bold>'.InnovaSetup::username().'</> will have this password on every installation.');
        $this->line('  At least 12 characters, with letters and numbers. It is stored only as a one-way hash.');
        $this->newLine();

        $password = (string) $this->secret('New superadmin password');
        if (strlen($password) < 12 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            $this->error('  Too weak: at least 12 characters, with letters and numbers. Nothing was changed.');

            return self::FAILURE;
        }
        if ($password !== (string) $this->secret('The same password again')) {
            $this->error('  The two passwords are different. Nothing was changed.');

            return self::FAILURE;
        }

        $hash = Hash::driver('bcrypt')->make($password, ['rounds' => 12]);

        $path = config_path('innova.php');
        $content = File::get($path);
        $new = preg_replace("/'password_hash' => '[^']*'/", "'password_hash' => '".$hash."'", $content, 1, $count);
        if ($count !== 1) {
            $this->error("  Could not find 'password_hash' in config/innova.php. Nothing was changed.");

            return self::FAILURE;
        }
        File::put($path, $new);

        $this->info('  Saved in config/innova.php.');
        $this->newLine();
        $this->line('  Next:');
        $this->line('   1. Commit and push config/innova.php.');
        $this->line('   2. New installations get this password by themselves.');
        $this->line('   3. On an existing installation, after updating its code: php artisan innova:superadmin');
        $this->newLine();

        return self::SUCCESS;
    }
}
