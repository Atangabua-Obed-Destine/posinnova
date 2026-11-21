<?php

namespace App\Console\Commands;

use App\Utils\InnovaSetup;
use Illuminate\Console\Command;

/**
 * Makes this installation's superadmin the standard I-NNOVA one: creates it
 * (with its business "I-NNOVA") if missing, or brings its password back to the
 * one shipped with the code, and lists it in ADMINISTRATOR_USERNAMES.
 */
class InnovaSuperadmin extends Command
{
    protected $signature = 'innova:superadmin';

    protected $description = 'Create or update the I-NNOVA superadmin on this installation';

    public function handle(): int
    {
        try {
            $result = InnovaSetup::ensureSuperadmin();
        } catch (\Throwable $e) {
            $this->error('  '.$e->getMessage());

            return self::FAILURE;
        }

        // Superadmin rights come from ADMINISTRATOR_USERNAMES in .env
        $path = base_path('.env');
        if (is_file($path)) {
            $current = array_filter(array_map('trim', explode(',', strtolower((string) config('constants.administrator_usernames')))));
            if (! in_array(InnovaSetup::username(), $current, true)) {
                $current[] = InnovaSetup::username();
                InnovaInstall::setEnv($path, ['ADMINISTRATOR_USERNAMES' => implode(',', $current)]);
                $this->callSilent('config:clear');
            }
        }

        $this->info('  '.($result['created']
            ? 'Superadmin '.InnovaSetup::username().' created, in the business "'.config('innova.superadmin.business_name').'".'
            : ($result['password_changed']
                ? 'Superadmin '.InnovaSetup::username().': password brought back to the standard I-NNOVA one.'
                : 'Superadmin '.InnovaSetup::username().' is already the standard one. Nothing to change.')));

        return self::SUCCESS;
    }
}
