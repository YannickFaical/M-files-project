<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        'web' => \App\Http\Middleware\EncryptCookies::class . '\EncryptCookies',
        'auth' => \App\Http\Middleware\Authenticate::class . '\Authenticate',
        'auth.basic' => \Illuminate\Auth\Middleware\Authenticate::class . '\Authenticate',
        'guest' => \App\Http\Middleware\EnsureGuest::class . '\EnsureGuest',
        'mfiles.auth' => \App\Http\Middleware\MFilesAuth::class,
    ];
}