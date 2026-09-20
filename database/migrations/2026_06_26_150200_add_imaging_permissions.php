<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'imaging.manage_catalogue',
            'imaging_request.create',
            'imaging_request.view',
            'imaging_result.enter',
            'imaging_result.view',
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
            'imaging.manage_catalogue',
            'imaging_request.create',
            'imaging_request.view',
            'imaging_result.enter',
            'imaging_result.view',
        ];
        Permission::whereIn('name', $permissions)->delete();
    }
};
