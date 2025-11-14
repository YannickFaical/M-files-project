<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MFilesAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Bypass en mode mock
        if (env('MFILES_MOCK', false)) {
            return $next($request);
        }

        $token = $request->header('X-Authentication') ?? $request->query('token') ?? Session::get('mfiles_token');

        if (empty($token)) {
            return response()->json([
                'message' => 'Unauthorized - missing M-Files token'
            ], 401);
        }

        return $next($request);
    }
}
