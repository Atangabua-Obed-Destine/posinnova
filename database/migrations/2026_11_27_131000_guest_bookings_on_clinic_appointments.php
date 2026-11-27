<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public-page bookings: a new patient who books from the public clinic page is
 * NOT given an account — their typed name/phone are kept on the request and
 * reception creates/links the real patient when they approve it. So an
 * appointment may be a "guest" request: contact_id null + guest_name/guest_phone,
 * source 'public'. contact_id becomes nullable; the engine fills it on approval.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_appointments')) {
            return;
        }
        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_appointments', 'guest_name')) {
                $table->string('guest_name', 150)->nullable()->after('contact_id');
            }
            if (! Schema::hasColumn('clinic_appointments', 'guest_phone')) {
                $table->string('guest_phone', 40)->nullable()->after('guest_name');
            }
        });
        // make contact_id nullable (a guest request has none until approved)
        DB::statement('ALTER TABLE `clinic_appointments` MODIFY `contact_id` INT UNSIGNED NULL');
        // allow the public source
        DB::statement("ALTER TABLE `clinic_appointments` MODIFY `source` ENUM('reception','portal','whatsapp','public') NOT NULL DEFAULT 'reception'");
    }

    public function down()
    {
        if (! Schema::hasTable('clinic_appointments')) {
            return;
        }
        // drop guest rows and narrow back
        DB::table('clinic_appointments')->where('source', 'public')->whereNull('contact_id')->delete();
        DB::statement("ALTER TABLE `clinic_appointments` MODIFY `source` ENUM('reception','portal','whatsapp') NOT NULL DEFAULT 'reception'");
        DB::statement('ALTER TABLE `clinic_appointments` MODIFY `contact_id` INT UNSIGNED NOT NULL');
        Schema::table('clinic_appointments', function (Blueprint $table) {
            foreach (['guest_phone', 'guest_name'] as $c) {
                if (Schema::hasColumn('clinic_appointments', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
