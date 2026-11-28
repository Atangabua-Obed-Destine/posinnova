<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second optional doctor template: tells a doctor on WhatsApp that one of his
 * appointments was booked/confirmed for him, moved or cancelled (My practice).
 * On by default; it only sends once the clinic has it approved in Meta and the
 * doctor has a number on his profile. Never blocks the patient templates.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('clinic_whatsapp_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_whatsapp_accounts', 'tpl_doctor_update')) {
                $table->string('tpl_doctor_update', 100)->default('posinnova_doctor_appointment_update')->after('tpl_doctor_request');
            }
            if (! Schema::hasColumn('clinic_whatsapp_accounts', 'send_doctor_update')) {
                $table->boolean('send_doctor_update')->default(1)->after('send_doctor_request');
            }
        });
    }

    public function down()
    {
        Schema::table('clinic_whatsapp_accounts', function (Blueprint $table) {
            foreach (['tpl_doctor_update', 'send_doctor_update'] as $c) {
                if (Schema::hasColumn('clinic_whatsapp_accounts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
