<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova hotel module (phase 1): rooms, reservations and stays, the police
 * register cards, the folio index, night audits and settings.
 *
 * Money is not kept here: every charge is an ordinary `sell` invoice on the
 * guest's contact and every payment an ordinary transaction_payment
 * (App\Utils\HotelFolio). hotel_folio_lines only says which of them belong to
 * which stay. A reservation becomes the stay once checked in (same row).
 */
return new class extends Migration
{
    private $permissions = [
        'hotel.view', 'hotel.reservations', 'hotel.front_desk', 'hotel.manager',
        'hotel.night_audit', 'hotel.housekeeping', 'hotel.reports', 'hotel.setup',
    ];

    public function up()
    {
        Schema::create('hotel_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->unique();
            $t->text('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('hotel_room_types', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->index();
            $t->string('name', 100);
            $t->string('description', 255)->nullable();
            $t->unsignedTinyInteger('capacity')->default(2);
            $t->decimal('rate_room_only', 22, 4)->default(0);
            $t->decimal('rate_bb', 22, 4)->nullable();
            $t->decimal('rate_corporate', 22, 4)->nullable();
            $t->decimal('tourist_tax', 22, 4)->default(0);
            $t->unsignedInteger('product_id')->nullable();
            $t->unsignedInteger('variation_id')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(1);
            $t->timestamps();
        });

        Schema::create('hotel_rooms', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('room_type_id')->index();
            $t->string('number', 20);
            $t->string('floor', 30)->nullable();
            $t->string('hk_status', 12)->default('clean'); // dirty | clean | inspected
            $t->unsignedInteger('hk_by')->nullable();
            $t->dateTime('hk_at')->nullable();
            $t->date('ooo_from')->nullable(); // out of order from … to (inclusive)
            $t->date('ooo_to')->nullable();
            $t->string('ooo_reason', 191)->nullable();
            $t->string('notes', 255)->nullable();
            $t->boolean('is_active')->default(1);
            $t->timestamps();
            $t->unique(['business_id', 'number']);
        });

        Schema::create('hotel_reservations', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('location_id');
            $t->string('ref_no', 30);
            $t->unsignedInteger('contact_id')->index();
            $t->unsignedInteger('room_type_id');
            $t->unsignedInteger('room_id')->nullable()->index();
            $t->date('arrival');
            $t->date('departure');
            $t->unsignedTinyInteger('adults')->default(1);
            $t->unsignedTinyInteger('children')->default(0);
            $t->string('rate_plan', 20)->default('room_only'); // room_only | bb | corporate
            $t->decimal('rate', 22, 4)->default(0);            // agreed price per night
            $t->string('source', 20)->default('walk_in');
            $t->string('status', 20)->default('confirmed');  // tentative | confirmed | checked_in | checked_out | cancelled | no_show
            $t->date('hold_until')->nullable();               // a tentative booking lapses after this date
            $t->decimal('credit', 22, 4)->default(0);         // money received for this stay not yet used on its bill
            $t->string('notes', 500)->nullable();
            $t->dateTime('checked_in_at')->nullable();
            $t->unsignedInteger('checked_in_by')->nullable();
            $t->dateTime('checked_out_at')->nullable();
            $t->unsignedInteger('checked_out_by')->nullable();
            $t->unsignedTinyInteger('keys_given')->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->unsignedInteger('cancelled_by')->nullable();
            $t->string('cancel_reason', 191)->nullable();
            $t->unsignedInteger('created_by');
            $t->timestamps();
            $t->unique(['business_id', 'ref_no']);
            $t->index(['business_id', 'status', 'arrival']);
            $t->index(['business_id', 'room_type_id', 'arrival', 'departure'], 'hotel_res_type_dates');
        });

        // One row per assigned room per night: a room cannot be given twice for the same night.
        Schema::create('hotel_room_nights', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('room_id');
            $t->date('night');
            $t->unsignedInteger('reservation_id')->index();
            $t->unique(['room_id', 'night']);
        });

        Schema::create('hotel_guests', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('reservation_id')->index();
            $t->unsignedInteger('contact_id')->nullable()->index();
            $t->boolean('is_primary')->default(0);
            $t->string('surname', 100);
            $t->string('first_names', 150)->nullable();
            $t->string('sex', 10)->nullable();
            $t->date('dob')->nullable();
            $t->string('birth_place', 100)->nullable();
            $t->string('nationality', 60)->nullable();
            $t->string('profession', 100)->nullable();
            $t->string('id_type', 30)->nullable();
            $t->string('id_number', 60)->nullable();
            $t->date('id_issued_on')->nullable();
            $t->string('id_issued_at', 100)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('address', 191)->nullable();
            $t->string('coming_from', 100)->nullable();
            $t->string('going_to', 100)->nullable();
            $t->string('purpose', 20)->nullable();
            $t->timestamps();
        });

        // Which invoices and payments belong to which stay. night_key is the night for a room
        // night (one per stay per night: the night audit can never charge it twice), null otherwise.
        Schema::create('hotel_folio_lines', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('reservation_id');
            $t->string('kind', 20); // night | charge | payment | credit_used | late_checkout
            $t->string('item', 30)->nullable();
            $t->date('night_key')->nullable();
            $t->unsignedInteger('transaction_id')->nullable()->index();
            $t->unsignedInteger('transaction_payment_id')->nullable();
            $t->decimal('amount', 22, 4)->default(0);
            $t->string('description', 255)->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->dateTime('voided_at')->nullable();
            $t->unsignedInteger('voided_by')->nullable();
            $t->string('void_reason', 191)->nullable();
            $t->timestamps();
            $t->unique(['reservation_id', 'night_key']);
            $t->index(['reservation_id', 'kind']);
        });

        Schema::create('hotel_room_moves', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('reservation_id')->index();
            $t->unsignedInteger('from_room_id')->nullable();
            $t->unsignedInteger('to_room_id');
            $t->decimal('old_rate', 22, 4)->nullable();
            $t->decimal('new_rate', 22, 4)->nullable();
            $t->string('reason', 191)->nullable();
            $t->unsignedInteger('moved_by');
            $t->timestamps();
        });

        Schema::create('hotel_night_audits', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->date('audit_date');
            $t->dateTime('ran_at');
            $t->unsignedInteger('ran_by')->nullable(); // null: the scheduled run
            $t->unsignedInteger('nights_posted')->default(0);
            $t->decimal('room_revenue', 22, 4)->default(0);
            $t->unsignedInteger('no_shows')->default(0);
            $t->mediumText('summary')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'audit_date']);
        });

        $now = \Carbon::now()->toDateTimeString();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        foreach (['hotel_night_audits', 'hotel_room_moves', 'hotel_folio_lines', 'hotel_guests', 'hotel_room_nights',
            'hotel_reservations', 'hotel_rooms', 'hotel_room_types', 'hotel_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Permission::whereIn('name', $this->permissions)->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
