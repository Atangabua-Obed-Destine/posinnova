<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'department.view',
            'department.create',
            'department.update',
            'department.delete',
            'department_issue.access',
            'department_issue.create',
            'department_issue.update',
            'department_issue.delete',
            'department_issue.view_report',
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
        $permissions = [
            'department.view',
            'department.create',
            'department.update',
            'department.delete',
            'department_issue.access',
            'department_issue.create',
            'department_issue.update',
            'department_issue.delete',
            'department_issue.view_report',
        ];
        Permission::whereIn('name', $permissions)->delete();
    }
};
