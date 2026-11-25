<?php

namespace App\Console\Commands;

use App\Utils\GymWhatsApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** PosInnova gym: WhatsApp reminders before a membership ends and after it lapsed (not renewed). */
class GymReminders extends Command
{
    protected $signature = 'gym:reminders {--business= : only this business}';

    protected $description = 'Remind gym members whose membership ends soon or has lapsed';

    public function handle()
    {
        $ids = $this->option('business') ? [(int) $this->option('business')]
            : DB::table('business')->where('enabled_modules', 'like', '%"gym"%')->pluck('id')->all();
        foreach ($ids as $id) {
            try {
                $n = GymWhatsApp::sendReminders($id);
                if ($n) {
                    $this->line("business $id: $n reminder(s)");
                }
            } catch (\Throwable $e) {
                \Log::error('gym:reminders business '.$id.': '.$e->getMessage());
            }
        }

        return 0;
    }
}
