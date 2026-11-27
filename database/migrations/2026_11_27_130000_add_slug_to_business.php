<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A short public identifier for each business, used in the public clinic page
 * URL (/c/{slug}). Nullable and unique; a clinic only gets a public page once a
 * slug is set and the page is switched on.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('business') && ! Schema::hasColumn('business', 'slug')) {
            Schema::table('business', function (Blueprint $table) {
                $table->string('slug', 150)->nullable()->unique()->after('name');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('business') && Schema::hasColumn('business', 'slug')) {
            Schema::table('business', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            });
        }
    }
};
