<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Doctors manage their own appointments: confirm, decline, cancel, move, book and
 * mark no-shows on their own diary column (appointment.manage_own), and set their
 * own consulting hours and days off (clinic_schedule.manage_own), without
 * appointment.manage / clinic_schedule.manage, which cover every doctor.
 * Roles whose users receive patients get both straight away.
 */
return new class extends Migration
{
    private $permissions = ['appointment.manage_own', 'clinic_schedule.manage_own'];

    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $doctor = Permission::where('name', 'visit.receive_patients')->where('guard_name', 'web')->value('id');
        if ($doctor) {
            $role_ids = DB::table('role_has_permissions')->where('permission_id', $doctor)->pluck('role_id');
            $ids = Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');
            foreach ($role_ids as $role_id) {
                foreach ($ids as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $role_id]);
                }
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
