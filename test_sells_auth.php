<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/sells', 'GET', ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_direct_sale' => 1]);
$request->headers->set('X-Requested-With', 'XMLHttpRequest');

$user = App\User::find(1);
if($user) {
    Auth::login($user);
}

$response = $kernel->handle($request);
echo $response->getContent();
