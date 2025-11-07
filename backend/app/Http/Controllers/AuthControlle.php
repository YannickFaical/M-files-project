<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Mode mock pour tests locaux
        if (env('MFILES_MOCK', false)) {
            $token = 'mock-token-' . bin2hex(random_bytes(6));
            // Optionnel : stocker en session si besoin
            try {
                session()->put('mfiles_token', $token);
            } catch (\Throwable $e) {
                // ignore
            }
            return response()->json([
                'success' => true,
                'token' => $token,
                'mock' => true,
            ]);
        }

        // Mode réel : appel à M-Files
        $client = new Client();

        try {
            $response = $client->post(env('MFILES_BASE_URL') . "/server/authenticationtokens", [
                'json' => [
                    'Username' => $request->username,
                    'Password' => $request->password,
                    'VaultGuid' => env('MFILES_VAULT_ID')
                ]
            ]);

            $result = json_decode((string) $response->getBody(), true);

            $token = $result['Value'] ?? null;
            if ($token) {
                // Optionnel : stocker en session
                try {
                    session()->put('mfiles_token', $token);
                } catch (\Throwable $e) {
                    // ignore
                }

                return response()->json([
                    'success' => true,
                    'token' => $token,
                    'raw' => $result,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed - no token returned',
                'raw' => $result,
            ], 401);
        } catch (RequestException $e) {
            $details = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            return response()->json([
                'success' => false,
                'message' => 'M-Files authentication error',
                'details' => $details,
            ], 401);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
