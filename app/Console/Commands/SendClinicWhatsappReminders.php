<?php

namespace App\Console\Commands;

use App\Utils\ClinicWhatsApp;
use Illuminate\Console\Command;

/**
 * Sends tomorrow's appointment reminders on WhatsApp for every clinic that has
 * them switched on, once its reminder time has come. Safe to run as often as
 * you like: an appointment is reminded once. Scheduled every 10 minutes.
 */
class SendClinicWhatsappReminders extends Command
{
    protected $signature = 'clinic:whatsapp-reminders';

    protected $description = 'Send tomorrow\'s appointment reminders on WhatsApp (clinic)';

    public function handle()
    {
        $done = ClinicWhatsApp::runReminders();
        foreach ($done as $business_id => $n) {
            $this->info("Business {$business_id}: {$n} reminder(s) sent");
        }
        if (empty($done)) {
            $this->info('No clinic is due to send reminders now.');
        }

        return 0;
    }
}
