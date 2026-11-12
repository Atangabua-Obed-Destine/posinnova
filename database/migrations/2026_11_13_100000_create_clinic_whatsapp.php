<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic WhatsApp messages for appointments, through Meta's WhatsApp Business
 * Platform (Cloud API): confirmation when a booking is made or accepted, a
 * reminder the day before, a notice when a booking is moved. Patients answer
 * with buttons ("I will come" / "I cannot come") or text; their replies come
 * back through a webhook into a replies inbox, and STOP ends the messages.
 *
 *  - clinic_whatsapp_accounts: one per business; the access token and the app
 *    secret are stored encrypted (Laravel encrypted casts, APP_KEY).
 *  - clinic_whatsapp_messages: every message sent or received, with its
 *    delivery status (sent / delivered / read / failed) from the webhook.
 *  - patient_details: the patient's consent (nothing is sent without it) and
 *    the language of their messages.
 *  - clinic_appointments: the patient's answer to the reminder.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_whatsapp_accounts')) {
            Schema::create('clinic_whatsapp_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->unique();
                $table->boolean('enabled')->default(0);
                // Meta account details (WhatsApp > API Setup in the Meta app)
                $table->string('phone_number_id', 64)->nullable();
                $table->string('waba_id', 64)->nullable();          // WhatsApp Business Account ID
                $table->text('access_token')->nullable();           // encrypted
                $table->text('app_secret')->nullable();             // encrypted, checks the webhook's signature
                $table->string('api_version', 10)->default('v25.0');
                $table->string('verify_token', 64);                 // typed into Meta's webhook settings
                // what the clinic sends
                $table->string('language', 5)->default('fr');       // default language of messages
                $table->boolean('send_confirmation')->default(1);
                $table->boolean('send_reminder')->default(1);
                $table->boolean('send_moved')->default(1);
                $table->string('reminder_time', 5)->default('18:00'); // the day before, from this time
                $table->boolean('reminder_buttons')->default(1);    // the reminder template has the two answer buttons
                $table->string('tpl_confirmation', 100)->default('posinnova_appointment_confirmed');
                $table->string('tpl_reminder', 100)->default('posinnova_appointment_reminder');
                $table->string('tpl_moved', 100)->default('posinnova_appointment_moved');
                // what the set-up checks found
                $table->string('display_phone', 40)->nullable();
                $table->string('verified_name', 191)->nullable();
                $table->timestamp('connection_ok_at')->nullable();
                $table->string('connection_error', 500)->nullable();
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('webhook_subscribed_at')->nullable();
                $table->timestamp('webhook_verified_at')->nullable();  // Meta called the verification URL
                $table->timestamp('last_webhook_at')->nullable();      // Meta delivered an event
                $table->json('templates_status')->nullable();
                $table->timestamp('templates_checked_at')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clinic_whatsapp_messages')) {
            Schema::create('clinic_whatsapp_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->enum('direction', ['out', 'in']);
                // out: confirmation | reminder | moved | test | text (a reply typed by staff) | optout (the STOP answer)
                // in:  text | button | other
                $table->string('kind', 20);
                $table->unsignedInteger('contact_id')->nullable();
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->string('wa_number', 32);                    // the patient's number, digits with country code
                $table->string('template', 100)->nullable();
                $table->string('language', 5)->nullable();
                $table->text('body')->nullable();                   // what was sent / received, as text
                $table->string('button_payload', 100)->nullable();
                // out: queued | sent | delivered | read | failed ; in: received | handled
                $table->string('status', 12);
                $table->string('wamid', 191)->nullable()->unique(); // WhatsApp's message id
                $table->string('context_wamid', 191)->nullable();   // in: the message being answered
                $table->string('error_code', 20)->nullable();
                $table->string('error_message', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->unsignedInteger('handled_by')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'direction', 'status']);
                $table->index(['appointment_id', 'kind']);
                $table->index('contact_id');
            });
        }

        if (! Schema::hasColumn('patient_details', 'whatsapp_opt_in')) {
            Schema::table('patient_details', function (Blueprint $table) {
                $table->boolean('whatsapp_opt_in')->default(0);
                $table->timestamp('whatsapp_opt_in_at')->nullable();
                $table->timestamp('whatsapp_opt_out_at')->nullable();  // answered STOP, or unticked
                $table->string('whatsapp_language', 5)->nullable();    // null = the clinic's default
            });
        }

        if (! Schema::hasColumn('clinic_appointments', 'patient_reply')) {
            Schema::table('clinic_appointments', function (Blueprint $table) {
                $table->string('patient_reply', 12)->nullable();       // coming | not_coming
                $table->timestamp('patient_reply_at')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('clinic_whatsapp_messages');
        Schema::dropIfExists('clinic_whatsapp_accounts');
        Schema::table('patient_details', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_opt_in', 'whatsapp_opt_in_at', 'whatsapp_opt_out_at', 'whatsapp_language']);
        });
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn(['patient_reply', 'patient_reply_at']);
        });
    }
};
