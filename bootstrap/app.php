<?php

/*
|--------------------------------------------------------------------------
| PosInnova: keep this app's .env out of the process-wide environment
|--------------------------------------------------------------------------
|
| Under Apache on Windows PHP runs as threads inside ONE process, and by
| default Laravel copies every .env value into that process's environment
| with putenv(). When several Laravel apps share the server, a request here
| could read another app's APP_KEY - failing to decrypt the session cookie and
| answering 401 "Unauthenticated" - or, in a narrower window, its DB_DATABASE.
| Measured on the dev server: 0% failures alone, 50.6% of POS add-product
| requests failing while another app on the same Apache was busy.
|
| With putenv off, .env values live only in this request's $_SERVER/$_ENV
| (thread-local), so nothing leaks in or out. env() keeps working everywhere;
| only raw getenv() would stop seeing .env values (config/paystack.php was the
| one user and now calls env()). Must run before the kernel loads .env.
|
*/
Illuminate\Support\Env::disablePutenv();

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
