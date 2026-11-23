<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * PosInnova hotel <-> E-Ride Hotels (hotel contract v1):
 * - an E-Ride price per room type (empty = the room-only rate);
 * - a hold lapses at a date AND time (an old date-only hold keeps lapsing at the end of that day);
 * - a reservation remembers the E-Ride inquiry it came from;
 * - what E-Ride was last told about free rooms, per type.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('hotel_room_types', 'eride_price')) {
            Schema::table('hotel_room_types', function (Blueprint $t) {
                $t->decimal('eride_price', 22, 4)->nullable()->after('tourist_tax');
            });
        }

        if (Schema::hasColumn('hotel_reservations', 'hold_until')) {
            $type = DB::selectOne("SELECT DATA_TYPE t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotel_reservations' AND COLUMN_NAME = 'hold_until'");
            if ($type && strtolower($type->t) === 'date') {
                DB::statement('ALTER TABLE hotel_reservations MODIFY hold_until DATETIME NULL');
                DB::statement("UPDATE hotel_reservations SET hold_until = DATE_ADD(DATE(hold_until), INTERVAL '23:59:59' HOUR_SECOND) WHERE hold_until IS NOT NULL");
            }
        }
        if (! Schema::hasColumn('hotel_reservations', 'channel_ref')) {
            Schema::table('hotel_reservations', function (Blueprint $t) {
                $t->string('channel_ref', 64)->nullable()->after('source')->index();   // the E-Ride inquiry id
            });
        }

        if (! Schema::hasTable('channel_hotel_sent')) {
            Schema::create('channel_hotel_sent', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id');
                $t->unsignedInteger('room_type_id');
                $t->date('hotel_day');
                $t->char('meta_hash', 40);   // name, capacity, price, rooms
                $t->json('free');            // free rooms per night from hotel_day
                $t->timestamp('sent_at')->nullable();
                $t->unique(['link_id', 'room_type_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('channel_hotel_sent');
        if (Schema::hasColumn('hotel_reservations', 'channel_ref')) {
            Schema::table('hotel_reservations', fn (Blueprint $t) => $t->dropColumn('channel_ref'));
        }
        if (Schema::hasColumn('hotel_room_types', 'eride_price')) {
            Schema::table('hotel_room_types', fn (Blueprint $t) => $t->dropColumn('eride_price'));
        }
    }
};
