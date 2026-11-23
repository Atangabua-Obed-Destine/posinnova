<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PosInnova "Online channels" (first channel: E-Ride, contract v1).
 * POS only ever calls out: it pushes its catalogue and stock, fetches paid orders,
 * and reports which sale rang an order up. Nothing here writes a sale by itself.
 */
return new class extends Migration
{
    public function up()
    {
        // One linked selling location per channel (a warehouse is simply never linked).
        if (! Schema::hasTable('channel_links')) {
            Schema::create('channel_links', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('location_id');
                $t->string('channel', 20)->default('eride');
                $t->string('base_url');
                $t->text('api_key');                       // encrypted with APP_KEY
                $t->string('key_hint', 12)->nullable();    // last characters, to recognise it
                $t->string('qty_display', 10)->default('available'); // available | exact
                $t->string('pay_method', 30)->nullable();  // proposed when the shop was paid (its Mobile Money)
                $t->unsignedInteger('credit_contact_id')->nullable(); // the "E-Ride" contact, for orders E-Ride collected
                $t->boolean('is_active')->default(true);
                $t->string('branch_label')->nullable();    // "Store · Branch" from /hello
                $t->timestamp('linked_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->timestamp('last_ok_at')->nullable();
                $t->timestamp('last_error_at')->nullable();
                $t->text('last_error')->nullable();
                $t->timestamp('last_heartbeat_at')->nullable();
                $t->timestamp('last_push_at')->nullable();
                $t->timestamp('last_check_at')->nullable();  // the every-minute look for changes
                $t->timestamp('last_hourly_at')->nullable();
                $t->date('last_full_on')->nullable();
                $t->timestamp('last_full_at')->nullable();
                $t->boolean('full_requested')->default(true);
                $t->integer('clock_skew')->nullable();     // seconds, E-Ride time minus ours
                $t->unsignedInteger('published')->default(0);
                $t->timestamps();
                $t->unique(['location_id', 'channel']);
            });
        }

        // The owner's choice per product (the whole business): published, never, own price, unit, threshold.
        if (! Schema::hasTable('channel_products')) {
            Schema::create('channel_products', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedInteger('product_id');
                $t->boolean('publish')->default(false);
                $t->boolean('never')->default(false);
                $t->decimal('price', 22, 4)->nullable();      // own E-Ride price (single products), in the published unit
                $t->unsignedInteger('unit_id')->nullable();   // published unit, when not the product's own
                $t->decimal('low_stock', 22, 4)->nullable();  // in the published unit; null = alert quantity
                $t->timestamps();
                $t->unique(['business_id', 'product_id']);
                $t->index('updated_at');
            });
        }

        if (! Schema::hasTable('channel_category_blocks')) {
            Schema::create('channel_category_blocks', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedInteger('category_id');
                $t->timestamps();
                $t->unique(['business_id', 'category_id']);
            });
        }

        // What E-Ride was last sent, per linked location and variation: the change detection.
        if (! Schema::hasTable('channel_sent')) {
            Schema::create('channel_sent', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id');
                $t->unsignedInteger('variation_id');
                $t->unsignedInteger('product_id');
                $t->char('hash', 40);
                $t->char('image_sha1', 40)->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->unique(['link_id', 'variation_id']);
            });
        }

        // Order -> the POS sale that rang it up. One order, one sale.
        if (! Schema::hasTable('channel_orders')) {
            Schema::create('channel_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id')->index();
                $t->unsignedInteger('business_id')->index();
                $t->string('order_id', 64)->unique();
                $t->string('number', 40)->nullable();
                $t->unsignedInteger('transaction_id')->nullable()->index();
                $t->string('status', 20)->default('rung_up'); // rung_up | unrung
                $t->string('paid_to', 10)->nullable();
                $t->unsignedInteger('order_total')->nullable();
                $t->timestamp('rung_up_at')->nullable();
                $t->boolean('matches')->nullable();
                $t->timestamp('cancel_seen_at')->nullable();
                $t->timestamps();
            });
        }

        // Messages for E-Ride that must arrive even when the internet is down: retried by the scheduler.
        if (! Schema::hasTable('channel_outbox')) {
            Schema::create('channel_outbox', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id')->index();
                $t->string('kind', 20);          // rung_up | unrung | cancel_seen | image
                $t->string('ref', 64)->nullable(); // order id, or the image sha1
                $t->string('path');
                $t->json('body')->nullable();
                $t->uuid('idem_key');
                $t->unsignedInteger('attempts')->default(0);
                $t->timestamp('next_try_at')->nullable();
                $t->text('last_error')->nullable();
                $t->timestamp('done_at')->nullable();
                $t->timestamps();
                $t->index(['done_at', 'next_try_at']);
            });
        }
    }

    public function down()
    {
        foreach (['channel_outbox', 'channel_orders', 'channel_sent', 'channel_category_blocks', 'channel_products', 'channel_links'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
