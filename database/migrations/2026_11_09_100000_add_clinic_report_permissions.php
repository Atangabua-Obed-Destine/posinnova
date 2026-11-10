<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Clinic reports (/clinic/reports). Four permissions, granted on the Roles screen
 * (role.partials.hospital_permissions, "Reports" group). Roles whose users receive
 * patients as doctors get "My practice" straight away, so doctors see their own
 * reports without anyone having to edit the role first.
 */
return new class extends Migration
{
    private $permissions = [
        'clinic_report.my_practice',   // the doctor's reports, for their own patients
        'clinic_report.all_doctors',   // the same reports for any doctor / the whole clinic
        'clinic_report.finance',       // money received, income, who owes, insurers, plans
        'clinic_report.attendance',    // check-ins, appointments kept, busiest days
    ];

    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $practice = Permission::where('name', 'clinic_report.my_practice')->where('guard_name', 'web')->value('id');
        $doctor = Permission::where('name', 'visit.receive_patients')->where('guard_name', 'web')->value('id');
        if ($practice && $doctor) {
            $role_ids = DB::table('role_has_permissions')->where('permission_id', $doctor)->pluck('role_id');
            foreach ($role_ids as $role_id) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $practice, 'role_id' => $role_id]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::whereIn('name', $this->permissions)->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
