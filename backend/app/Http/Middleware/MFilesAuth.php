<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MFilesAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Mode mock : bypass pour faciliter les tests locaux
        if (env('MFILES_MOCK', false)) {
            return $next($request);
        }

        // Accept token from header X-Authentication or from session
        $token = $request->header('X-Authentication') ?? Session::get('mfiles_token');

        if (empty($token)) {
            return response()->json([
                'message' => 'Unauthorized - missing M-Files token (provide X-Authentication header)'
            ], 401);
        }

        // Optionally you can validate token format here

        return $next($request);
    }
}
