<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova clinic phase 3: the dispensary's "Prescriptions waiting" list.
 * Selling still needs the normal POS permissions; this one shows the list
 * and lets the dispensary close a line it will not fill.
 */
return new class extends Migration
{
    private $permissions = [
        'prescription.dispense',
    ];

    public function up()
    {
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $perm, 'guard_name' => 'web']);
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
