<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic WhatsApp booking bot's short-lived conversation state. One row per
 * number in an active chat: which step it is on (state) and what it has chosen
 * so far (data, JSON). Rows expire (expires_at) and are swept; nothing here is
 * a record of value — the appointment it creates lives in clinic_appointments.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_wa_sessions')) {
            Schema::create('clinic_wa_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->string('wa_number', 32);
                $table->unsignedInteger('contact_id')->nullable();
                $table->string('state', 40)->default('');
                $table->json('data')->nullable();
                $table->unsignedSmallInteger('replies')->default(0); // throttle: bot replies this hour
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'wa_number'], 'cws_business_number_unique');
                $table->index('expires_at', 'cws_expires_index');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('clinic_wa_sessions');
    }
};
