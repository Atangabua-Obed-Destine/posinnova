<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova bulk items: things opened and used a little at a time (a bag of coffee, a pack of
 * spaghetti, a bottle of oil...). The menu items' recipes (combo products) say how much of the
 * bag each sale uses; staff tap "bag finished" when it is empty, and the system compares what the
 * sales should have used with the bag that was really used (App\Utils\BulkItems).
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('bulk_items')) {
            Schema::create('bulk_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('variation_id');
                $table->string('container_label', 20)->default('bag');
                // how much of the product's unit one container holds (1 when the unit is the bag itself)
                $table->decimal('container_size', 22, 4)->default(1);
                $table->unsignedTinyInteger('low_pct')->default(85);
                $table->unsignedSmallInteger('high_pct')->default(115);
                $table->boolean('is_active')->default(1);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'variation_id']);
            });
        }
        if (! Schema::hasTable('bulk_item_cycles')) {
            Schema::create('bulk_item_cycles', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('bulk_item_id');
                $table->unsignedInteger('location_id');
                $table->string('status', 10)->default('open'); // open | closed
                $table->timestamp('opened_at')->nullable();
                $table->unsignedInteger('opened_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->decimal('containers', 10, 2)->default(1);      // how many were finished at that tap
                $table->decimal('expected_usage', 22, 4)->default(0);  // what the sales should have used (product unit)
                $table->decimal('actual_usage', 22, 4)->default(0);    // containers x container size
                $table->decimal('yield_pct', 8, 2)->nullable();
                $table->decimal('unit_cost', 22, 4)->default(0);
                $table->decimal('value', 22, 4)->default(0);           // money of the difference (+ lost / - extra)
                $table->text('breakdown')->nullable();                 // JSON: per menu item, per seller
                $table->unsignedInteger('adjustment_transaction_id')->nullable();
                $table->unsignedInteger('surplus_transaction_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['bulk_item_id', 'location_id', 'status']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('bulk_item_cycles');
        Schema::dropIfExists('bulk_items');
    }
};
