<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Clinic (hospital module) foundations, part 3: permissions the clinic flow
 * needs on top of the existing hospital ones. They are granted on the Roles
 * screen (role.partials.hospital_permissions).
 */
return new class extends Migration
{
    private $permissions = [
        'visit.receive_patients',      // listed as a doctor at check-in, has a queue
        'visit.override_pay_first',    // let a visit through before its fee is paid
        'patient.view_bill',           // see a patient's bill and balance
        'patient.reset_portal_pin',    // set / reset a patient's portal PIN
        'hospital_charge.void',        // void a wrong hospital charge
        'lab_result.amend',            // correct a validated lab result
        'clinic_package.manage',       // consultation packages
        'clinic_plan.manage',          // monthly plan subscriptions
        'clinic_settings.manage',      // the Clinic settings page
    ];

    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $rows = [];
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                $rows[] = ['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if (! empty($rows)) {
            Permission::insert($rows);
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::whereIn('name', $this->permissions)->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
