<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Doctors' own consultation fees. The clinic's price stays the default: a
 * package's price, or (a clinic without packages) the business consultation
 * fee. A doctor's fee, when set, replaces it for the patients who see that
 * doctor. package_key = the package's id, or 0 for the plain consultation fee
 * (0 rather than NULL so the unique key holds).
 *
 * clinic_package.manage (admins) sets every doctor's fees; the new
 * clinic_fee.manage_own lets a doctor set their own (granted to roles that
 * receive patients, as the clinic asked).
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('clinic_doctor_fees')) {
            Schema::create('clinic_doctor_fees', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->unsignedInteger('user_id');               // the doctor
                $table->unsignedBigInteger('package_key')->default(0); // package id, 0 = the plain consultation fee
                $table->decimal('price', 22, 4);
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'user_id', 'package_key'], 'clinic_doctor_fees_unique');
                $table->index(['business_id', 'package_key']);
            });
        }

        $now = \Carbon::now()->toDateTimeString();
        if (! Permission::where('name', 'clinic_fee.manage_own')->where('guard_name', 'web')->exists()) {
            Permission::insert(['name' => 'clinic_fee.manage_own', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $own = Permission::where('name', 'clinic_fee.manage_own')->where('guard_name', 'web')->value('id');
        $doctor = Permission::where('name', 'visit.receive_patients')->where('guard_name', 'web')->value('id');
        if ($own && $doctor) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $doctor)->pluck('role_id') as $role_id) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $own, 'role_id' => $role_id]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Schema::dropIfExists('clinic_doctor_fees');
        Permission::where('name', 'clinic_fee.manage_own')->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
