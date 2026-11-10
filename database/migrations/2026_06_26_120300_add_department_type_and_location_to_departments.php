<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classify departments by clinical type and (optionally) map a department to a
 * business location. When a department has a location_id, that location's POS
 * acts as the department's dispensing point. Non-mapped departments keep using
 * the existing department-issue flow.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'department_type')) {
                $table->enum('department_type', [
                    'pharmacy', 'opd', 'lab', 'radiology', 'maternity', 'surgery',
                    'paediatrics', 'ward', 'morgue', 'physiotherapy', 'dental', 'admin', 'other',
                ])->default('other')->after('name');
            }
            if (! Schema::hasColumn('departments', 'location_id')) {
                $table->unsignedInteger('location_id')->nullable()->after('department_type');
                $table->foreign('location_id')->references('id')->on('business_locations')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
            if (Schema::hasColumn('departments', 'department_type')) {
                $table->dropColumn('department_type');
            }
        });
    }
};
