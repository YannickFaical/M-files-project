<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MFilesService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    private $mfilesService;

    public function __construct(MFilesService $mfilesService)
    {
        $this->mfilesService = $mfilesService;
    }

    public function login(Request $request)
    {
        try {
            // Mode mock actif
            if (env('MFILES_MOCK', false)) {
                return response()->json([
                    'success' => true,
                    'token' => 'mock-token-' . bin2hex(random_bytes(6)),
                    'mock' => true
                ]);
            }

            // Mode réel - appel à M-Files
            $result = $this->mfilesService->authenticate(
                $request->username,
                $request->password
            );

            $token = $result['Value'] ?? null;
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'error' => 'No token returned by M-Files',
                ], 401);
            }

            // Probe minimal pour vérifier l'accès
            if (!$this->mfilesService->probe($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication succeeded but access to vault resources is denied',
                    'error' => 'Insufficient permissions or invalid vault configuration',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function logout()
    {
        Session::forget('mfiles_token');
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
