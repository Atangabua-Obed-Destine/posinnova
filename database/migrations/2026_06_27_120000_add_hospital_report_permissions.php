<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            'hospital_report.dashboard',
            'hospital_report.opd',
            'hospital_report.admissions',
            'hospital_report.lab',
            'hospital_report.ward',
            'hospital_report.revenue',
            'hospital_report.mortality',
            'hospital_report.antenatal',
            'hospital_report.morgue',
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
            'hospital_report.dashboard', 'hospital_report.opd', 'hospital_report.admissions',
            'hospital_report.lab', 'hospital_report.ward', 'hospital_report.revenue',
            'hospital_report.mortality', 'hospital_report.antenatal', 'hospital_report.morgue',
        ])->delete();
    }
};
