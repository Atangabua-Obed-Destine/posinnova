<?php

use App\Http\Controllers\Install;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installation
|--------------------------------------------------------------------------
|
| I-NNOVA POS is installed and updated from a terminal:
|     php artisan innova:install     php artisan innova:update
| (INSTALL.md). These pages only say so. The last step of an installation,
| creating the business, is /setup/{token} (routes/web.php).
|
*/

Route::get('/install-start', [Install\InstallController::class, 'index'])->name('install.index');
Route::get('/install/update', [Install\InstallController::class, 'update'])->name('install.updateConfirmation');
