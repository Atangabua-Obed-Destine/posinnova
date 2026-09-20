<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'morgue.receive',
            'morgue.view',
            'morgue.record_visit',
            'morgue.authorize_release',
            'morgue.release',
            'morgue.view_report',
            'morgue.manage_slots',
        ];

        $rows = [];
        foreach ($permissions as $perm) {
            $exists = Permission::where('name', $perm)->where('guard_name', 'web')->exists();
            if (! $exists) {
                $rows[] = [
                    'name' => $perm,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            Permission::insert($rows);
        }
    }

    public function down()
    {
        Permission::whereIn('name', [
            'morgue.receive', 'morgue.view', 'morgue.record_visit',
            'morgue.authorize_release', 'morgue.release', 'morgue.view_report', 'morgue.manage_slots',
        ])->delete();
    }
};
