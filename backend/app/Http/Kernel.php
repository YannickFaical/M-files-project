<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\SubstituteBindings;

class Kernel extends HttpKernel
{
    protected $middleware = [
        HandleCors::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            SubstituteBindings::class,
        ],

        'api' => [
            SubstituteBindings::class,
        ],
    ];

    protected $middlewareAliases = [
        'mfiles.auth' => \App\Http\Middleware\MFilesAuth::class,
    ];

    // For compatibility with older resolution paths
    protected $routeMiddleware = [
        'mfiles.auth' => \App\Http\Middleware\MFilesAuth::class,
    ];
}