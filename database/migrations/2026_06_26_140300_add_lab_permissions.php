<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'lab_test.manage_catalogue',
            'lab_request.create',
            'lab_request.view',
            'lab_request.process',
            'lab_result.enter',
            'lab_result.validate',
            'lab_result.view',
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
            'lab_test.manage_catalogue',
            'lab_request.create',
            'lab_request.view',
            'lab_request.process',
            'lab_result.enter',
            'lab_result.validate',
            'lab_result.view',
        ];
        Permission::whereIn('name', $permissions)->delete();
    }
};
