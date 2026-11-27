<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional utility template that tells a doctor, over WhatsApp, that a new
 * appointment request is waiting for them (used when the clinic routes approval
 * to the doctor). Off by default; it never blocks the patient-facing templates.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('clinic_whatsapp_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_whatsapp_accounts', 'tpl_doctor_request')) {
                $table->string('tpl_doctor_request', 100)->default('posinnova_doctor_appointment_request')->after('tpl_moved');
            }
            if (! Schema::hasColumn('clinic_whatsapp_accounts', 'send_doctor_request')) {
                $table->boolean('send_doctor_request')->default(1)->after('send_moved');
            }
        });
    }

    public function down()
    {
        Schema::table('clinic_whatsapp_accounts', function (Blueprint $table) {
            foreach (['tpl_doctor_request', 'send_doctor_request'] as $c) {
                if (Schema::hasColumn('clinic_whatsapp_accounts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
