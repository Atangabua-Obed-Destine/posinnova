<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova hospital shifts — permissions.
 *
 * shift_settings.access  configure the shift feature (enable + time windows).
 * shift_report.view      view the Shift Summary report.
 *
 * Admin roles bypass permission checks via Gate::before, so they get these
 * automatically; other roles can be granted them from the Roles screen.
 */
return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'shift_settings.access',
            'shift_report.view',
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
            'shift_settings.access',
            'shift_report.view',
        ];
        Permission::whereIn('name', $permissions)->delete();
    }
};
