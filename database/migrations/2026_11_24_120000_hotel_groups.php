<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova hotel: group bookings. Several rooms paid by one person or company; each room stays an
 * ordinary reservation (its own dates, check-in, bill), linked to the group.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('hotel_groups')) {
            Schema::create('hotel_groups', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('business_id');
                $t->string('ref_no', 30);
                $t->unsignedInteger('contact_id')->index();       // the payer
                $t->string('pays', 10)->default('payer');          // payer: the payer settles everything | each: each room settles its own
                $t->string('notes', 500)->nullable();
                $t->unsignedInteger('created_by');
                $t->timestamps();
                $t->unique(['business_id', 'ref_no']);
            });
        }
        Schema::table('hotel_reservations', function (Blueprint $t) {
            if (! Schema::hasColumn('hotel_reservations', 'group_id')) {
                $t->unsignedInteger('group_id')->nullable()->index()->after('contact_id');
            }
            if (! Schema::hasColumn('hotel_reservations', 'occupant')) {
                $t->string('occupant', 150)->nullable()->after('group_id'); // who sleeps there, when not the payer
            }
        });
    }

    public function down()
    {
        Schema::table('hotel_reservations', function (Blueprint $t) {
            $t->dropColumn(['group_id', 'occupant']);
        });
        Schema::dropIfExists('hotel_groups');
    }
};
