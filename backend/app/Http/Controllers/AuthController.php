<?php

namespace App\Http\Controllers;

use App\Services\MFilesService;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\RequestException;

class AuthController extends Controller
{
    private $mfilesService;

    public function __construct(MFilesService $mfilesService)
    {
        $this->mfilesService = $mfilesService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        try {
            $result = $this->mfilesService->authenticate(
                $request->username,
                $request->password
            );

            // Retour standardisé : success + token
            return response()->json([
                'success' => true,
                'token' => $result['Value'] ?? null,
                'raw' => $result,
            ]);
        } catch (RequestException $e) {
            // Si M-Files a renvoyé une réponse, la renvoyer pour debug
            if ($e->hasResponse()) {
                $body = (string) $e->getResponse()->getBody();
                return response()->json([
                    'success' => false,
                    'message' => 'M-Files authentication error',
                    'details' => $body,
                ], 401);
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'details' => $e->getMessage(),
            ], 401);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
