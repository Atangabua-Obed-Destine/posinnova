<?php

namespace App\Console\Commands;

use App\Utils\PressingWhatsApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** PosInnova pressing: one WhatsApp reminder for clothes ready and not collected after the set days. */
class PressingReminders extends Command
{
    protected $signature = 'pressing:reminders {--business= : only this business}';

    protected $description = 'Remind pressing customers whose clothes are ready and not collected';

    public function handle()
    {
        $ids = $this->option('business') ? [(int) $this->option('business')]
            : DB::table('business')->where('enabled_modules', 'like', '%"pressing"%')->pluck('id')->all();
        foreach ($ids as $id) {
            try {
                $n = PressingWhatsApp::sendReminders($id);
                if ($n) {
                    $this->line("business $id: $n reminder(s)");
                }
            } catch (\Throwable $e) {
                \Log::error('pressing:reminders business '.$id.': '.$e->getMessage());
            }
        }

        return 0;
    }
}
