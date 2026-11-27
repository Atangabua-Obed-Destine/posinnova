<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Patients can now book over WhatsApp, so a booking's source may be 'whatsapp'
 * (an online, self-service source like 'portal': it waits for approval and
 * honours the daily cap). Widen the enum to allow it.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('clinic_appointments')) {
            DB::statement("ALTER TABLE `clinic_appointments` MODIFY `source` ENUM('reception','portal','whatsapp') NOT NULL DEFAULT 'reception'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('clinic_appointments')) {
            // revert any whatsapp bookings to portal so the narrower enum still fits
            DB::table('clinic_appointments')->where('source', 'whatsapp')->update(['source' => 'portal']);
            DB::statement("ALTER TABLE `clinic_appointments` MODIFY `source` ENUM('reception','portal') NOT NULL DEFAULT 'reception'");
        }
    }
};
