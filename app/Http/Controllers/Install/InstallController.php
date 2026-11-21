<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;

/**
 * I-NNOVA POS is installed and updated from a terminal (php artisan innova:install,
 * php artisan innova:update; see INSTALL.md). These pages only explain that.
 */
class InstallController extends Controller
{
    public function index()
    {
        return view('install.innova', ['mode' => 'install']);
    }

    public function update()
    {
        return view('install.innova', ['mode' => 'update']);
    }
}
