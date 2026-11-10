<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova clinic: appointments.
 *
 *  - clinic_doctor_hours: each doctor's weekly consulting hours, cut into
 *    fixed-length slots (several blocks a day allowed, e.g. morning and
 *    afternoon);
 *  - clinic_doctor_days_off: dates a doctor does not consult;
 *  - clinic_appointments: a booked slot. A patient asks from the portal
 *    (requested) and reception confirms, or reception books directly
 *    (confirmed). slot_key is set while the booking holds the slot and
 *    cleared when it is cancelled or declined; its unique index means one
 *    slot can never be held twice. Nothing is charged at booking; the
 *    check-in bills as usual and links the visit (visits.appointment_id).
 */
return new class extends Migration
{
    public function up()
    {
        foreach (['clinic_appointments', 'clinic_doctor_days_off', 'clinic_doctor_hours'] as $t) {
            if (Schema::hasTable($t) && DB::table($t)->count() == 0) {
                Schema::drop($t);
            }
        }

        Schema::create('clinic_doctor_hours', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->unsignedTinyInteger('weekday');        // 1 = Monday ... 7 = Sunday (ISO)
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(20);
            $table->timestamps();

            $table->index(['business_id', 'user_id', 'weekday'], 'cdh_business_user_day_index');
        });

        Schema::create('clinic_doctor_days_off', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->date('date');
            $table->string('reason', 191)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date'], 'cddo_user_date_unique');
        });

        Schema::create('clinic_appointments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('doctor_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['requested', 'confirmed', 'checked_in', 'cancelled', 'declined', 'no_show'])->default('requested');
            $table->enum('source', ['reception', 'portal'])->default('reception');
            $table->string('slot_key', 60)->nullable();     // "<doctor>-<date>-<time>" while the slot is held
            $table->string('reason', 500)->nullable();     // what the patient wrote
            $table->string('notes', 500)->nullable();      // staff notes
            $table->unsignedInteger('visit_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedInteger('confirmed_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable(); // null with status cancelled = the patient, from the portal
            $table->string('cancel_reason', 191)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique('slot_key', 'ca_slot_key_unique');
            $table->index(['business_id', 'date', 'doctor_id'], 'ca_business_date_doctor_index');
            $table->index(['business_id', 'status'], 'ca_business_status_index');
            $table->index('contact_id', 'ca_contact_index');
        });

        if (! Schema::hasColumn('visits', 'appointment_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->index('appointment_id');
            });
        }

        foreach (['appointment.view', 'appointment.manage', 'clinic_schedule.manage'] as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $perm, 'guard_name' => 'web']);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        if (Schema::hasColumn('visits', 'appointment_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropIndex(['appointment_id']);
                $table->dropColumn('appointment_id');
            });
        }
        Schema::dropIfExists('clinic_appointments');
        Schema::dropIfExists('clinic_doctor_days_off');
        Schema::dropIfExists('clinic_doctor_hours');
        Permission::whereIn('name', ['appointment.view', 'appointment.manage', 'clinic_schedule.manage'])->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
