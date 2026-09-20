<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'visit.create',
            'visit.view',
            'visit.close',
            'consultation.create',
            'consultation.view_own',
            'consultation.view_all',
            'consultation.diagnose',
            'consultation.prescribe',
            'consultation.finalize',
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
            'visit.create',
            'visit.view',
            'visit.close',
            'consultation.create',
            'consultation.view_own',
            'consultation.view_all',
            'consultation.diagnose',
            'consultation.prescribe',
            'consultation.finalize',
        ];
        Permission::whereIn('name', $permissions)->delete();
    }
};
