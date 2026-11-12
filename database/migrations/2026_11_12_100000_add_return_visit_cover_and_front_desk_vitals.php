<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * 1. Return visits covered by a paid consultation (clinic packages).
 *    A package can say that its fee also covers the patient's return visits for
 *    a while: N days, one month from the visit, or until the end of that
 *    calendar month; optionally only so many return visits, and only with the
 *    same doctor. Nothing is billed automatically: the next fee is charged when
 *    the patient comes back after the cover has run out.
 *    The paid visit keeps its own copy of the rules (cover_until, ...), so
 *    editing the package later does not change what patients already paid for.
 *    A return visit points at the paid one (covered_by_visit_id).
 *
 * 2. Vitals taken at the front desk, after payment and before the doctor
 *    (visit.record_vitals, granted to the roles that check patients in). The
 *    consultation form opens with them filled in.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('clinic_packages', 'cover_mode')) {
            Schema::table('clinic_packages', function (Blueprint $table) {
                $table->enum('cover_mode', ['none', 'days', 'month', 'calendar_month'])->default('none')->after('visits_per_month');
                $table->unsignedSmallInteger('cover_days')->nullable()->after('cover_mode');
                $table->unsignedSmallInteger('cover_max_visits')->nullable()->after('cover_days'); // null = unlimited
                $table->boolean('cover_same_doctor')->default(0)->after('cover_max_visits');
            });
        }

        if (! Schema::hasColumn('visits', 'cover_until')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->date('cover_until')->nullable()->after('fee_transaction_id');
                $table->unsignedSmallInteger('cover_max_visits')->nullable()->after('cover_until');
                $table->boolean('cover_same_doctor')->nullable()->after('cover_max_visits');
                $table->unsignedInteger('covered_by_visit_id')->nullable()->after('cover_same_doctor');
                $table->index(['business_id', 'contact_id', 'cover_until'], 'visits_cover_lookup');
                $table->index('covered_by_visit_id');
            });
        }

        if (! Schema::hasColumn('visits', 'vitals_taken_at')) {
            Schema::table('visits', function (Blueprint $table) {
                // same units as consultations.vitals_*
                $table->decimal('vitals_weight', 8, 2)->nullable()->after('covered_by_visit_id');
                $table->decimal('vitals_height', 8, 2)->nullable()->after('vitals_weight');
                $table->unsignedSmallInteger('vitals_bp_systolic')->nullable()->after('vitals_height');
                $table->unsignedSmallInteger('vitals_bp_diastolic')->nullable()->after('vitals_bp_systolic');
                $table->decimal('vitals_temperature', 5, 2)->nullable()->after('vitals_bp_diastolic');
                $table->unsignedSmallInteger('vitals_pulse')->nullable()->after('vitals_temperature');
                $table->unsignedSmallInteger('vitals_spo2')->nullable()->after('vitals_pulse');
                $table->string('vitals_note', 191)->nullable()->after('vitals_spo2');
                $table->unsignedInteger('vitals_taken_by')->nullable()->after('vitals_note');
                $table->timestamp('vitals_taken_at')->nullable()->after('vitals_taken_by');
            });
        }

        $now = \Carbon::now()->toDateTimeString();
        if (! Permission::where('name', 'visit.record_vitals')->where('guard_name', 'web')->exists()) {
            Permission::insert(['name' => 'visit.record_vitals', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $vitals = Permission::where('name', 'visit.record_vitals')->where('guard_name', 'web')->value('id');
        $front_desk = Permission::where('name', 'visit.create')->where('guard_name', 'web')->value('id');
        if ($vitals && $front_desk) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $front_desk)->pluck('role_id') as $role_id) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $vitals, 'role_id' => $role_id]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_cover_lookup');
            $table->dropIndex(['covered_by_visit_id']);
            $table->dropColumn(['cover_until', 'cover_max_visits', 'cover_same_doctor', 'covered_by_visit_id',
                'vitals_weight', 'vitals_height', 'vitals_bp_systolic', 'vitals_bp_diastolic', 'vitals_temperature',
                'vitals_pulse', 'vitals_spo2', 'vitals_note', 'vitals_taken_by', 'vitals_taken_at']);
        });
        Schema::table('clinic_packages', function (Blueprint $table) {
            $table->dropColumn(['cover_mode', 'cover_days', 'cover_max_visits', 'cover_same_doctor']);
        });
        Permission::where('name', 'visit.record_vitals')->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
