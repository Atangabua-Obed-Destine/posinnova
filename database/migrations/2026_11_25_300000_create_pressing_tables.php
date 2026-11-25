<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova pressing (laundry / dry cleaning) module: a price list per type of item and service,
 * orders with a ticket and one tag per piece, their progress to collection, problems, and the
 * WhatsApp messages sent about them. Every order is an ordinary sale (App\Utils\Pressing).
 */
return new class extends Migration
{
    private $permissions = ['pressing.view', 'pressing.counter', 'pressing.workshop', 'pressing.reports', 'pressing.setup'];

    public function up(): void
    {
        Schema::create('pressing_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->unique();
            $t->longText('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('pressing_services', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('name', 120);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(1);
            $t->timestamps();
        });

        Schema::create('pressing_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('name', 120);
            $t->unsignedSmallInteger('ready_days')->default(2);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(1);
            $t->timestamps();
        });

        Schema::create('pressing_prices', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('service_id');
            $t->decimal('price', 22, 4);
            $t->decimal('guest_price', 22, 4)->nullable();
            $t->unsignedInteger('variation_id')->nullable();
            $t->timestamps();
            $t->unique(['item_id', 'service_id']);
        });

        Schema::create('pressing_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('location_id')->nullable();
            $t->unsignedInteger('seq');
            $t->string('ticket_no', 30);
            $t->unsignedInteger('contact_id');
            $t->unsignedBigInteger('reservation_id')->nullable();
            $t->boolean('on_room_bill')->default(0);
            $t->unsignedInteger('transaction_id')->nullable();
            $t->string('status', 20)->default('received'); // received | washing | ironing | ready | collected | cancelled
            $t->boolean('express')->default(0);
            $t->decimal('total', 22, 4)->default(0);
            $t->string('phone', 40)->nullable();
            $t->boolean('whatsapp')->default(1);
            $t->string('lang', 5)->default('fr');
            $t->text('note')->nullable();
            $t->dateTime('received_at');
            $t->dateTime('ready_at');
            $t->dateTime('ready_marked_at')->nullable();
            $t->dateTime('reminded_at')->nullable();
            $t->dateTime('collected_at')->nullable();
            $t->string('collected_by', 150)->nullable();
            $t->unsignedInteger('collected_user_id')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'seq']);
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'contact_id']);
        });

        Schema::create('pressing_pieces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->unsignedSmallInteger('tag');
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('service_id');
            $t->decimal('price', 22, 4);
            $t->string('note', 255)->nullable();
            $t->boolean('handed_over')->default(0);
            $t->timestamps();
            $t->unique(['order_id', 'tag']);
        });

        Schema::create('pressing_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->string('status', 20);
            $t->string('note', 255)->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->dateTime('created_at');
        });

        Schema::create('pressing_problems', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedBigInteger('order_id')->index();
            $t->unsignedBigInteger('piece_id')->nullable();
            $t->string('kind', 20);     // lost | damaged | complaint
            $t->text('description');
            $t->string('outcome', 20);  // refund | rewash | none
            $t->decimal('amount', 22, 4)->default(0);
            $t->unsignedInteger('expense_id')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
        });

        if (Schema::hasTable('clinic_whatsapp_messages') && ! Schema::hasColumn('clinic_whatsapp_messages', 'pressing_order_id')) {
            Schema::table('clinic_whatsapp_messages', function (Blueprint $t) {
                $t->unsignedBigInteger('pressing_order_id')->nullable()->after('appointment_id')->index();
            });
        }

        $now = now();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinic_whatsapp_messages', 'pressing_order_id')) {
            Schema::table('clinic_whatsapp_messages', function (Blueprint $t) {
                $t->dropColumn('pressing_order_id');
            });
        }
        foreach (['pressing_problems', 'pressing_events', 'pressing_pieces', 'pressing_orders', 'pressing_prices', 'pressing_items', 'pressing_services', 'pressing_settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
