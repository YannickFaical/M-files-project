<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;

class MFilesService
{
    private $client;
    private $baseUrl;
    private $vaultId;
    private $hostHeader;
    
    // IDs à remplacer selon votre configuration
    const OBJECT_TYPE_CLIENT = 0;  // À remplacer
    const OBJECT_TYPE_DOCUMENT = 1; // À remplacer
    const PROPERTY_NAME = 0; // À remplacer
    const PROPERTY_CLASS = 100; // À remplacer
    const CLASS_DOCUMENT = 0; // À remplacer

    public function __construct()
    {
        $this->baseUrl = env('MFILES_BASE_URL', 'http://localhost/REST');
        $this->vaultId = env('MFILES_VAULT_ID');
        $this->hostHeader = env('MFILES_HOST_HEADER', 'localhost');
        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout' => 8.0,
            'connect_timeout' => 3.0,
            'http_errors' => false,
            // Avoid corporate/system proxies for localhost and force IPv4 resolution
            'proxy' => [
                'http' => null,
                'https' => null,
                'no' => ['localhost', '127.0.0.1']
            ],
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ]);
    }

    public function authenticate($username, $password)
    {
        try {
            $response = $this->client->post('server/authenticationtokens', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Expect' => '',
                    'Connection' => 'close',
                    'Host' => $this->hostHeader,
                ],
                'allow_redirects' => false,
                'timeout' => 15.0,
                'json' => [
                    'Username' => $username,
                    'Password' => $password,
                    'VaultGuid' => $this->vaultId
                ]
            ]);

            $status = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($status < 200 || $status >= 300) {
                $message = isset($result['Message']) ? $result['Message'] : 'M-Files authentication failed';
                throw new \RuntimeException($message . " (HTTP $status)");
            }

            // Optionnel : stocker en session si session disponible
            try {
                if (isset($result['Value'])) {
                    Session::put('mfiles_token', $result['Value']);
                }
            } catch (\Throwable $e) {
                // session non disponible sur les routes API stateless, c'est ok
            }

            return $result;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to reach M-Files authentication endpoint: ' . $e->getMessage());
        }
    }

    private function getHeaders()
    {
        // Priorité : header X-Authentication de la requête, sinon session
        $token = request()->header('X-Authentication') ?? Session::get('mfiles_token');

        $headers = [
            'Host' => $this->hostHeader,
        ];

        if ($token) {
            $headers['X-Authentication'] = $token;
        }

        return $headers;
    }

    public function getClients()
    {
        try {
            $response = $this->client->get('objects/' . self::OBJECT_TYPE_CLIENT . '/0/latest', [
                'headers' => $this->getHeaders()
            ]);

            $status = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($status < 200 || $status >= 300) {
                $message = isset($result['Message']) ? $result['Message'] : 'M-Files list clients failed';
                throw new \RuntimeException($message . " (HTTP $status)");
            }

            return $result;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to fetch M-Files clients: ' . $e->getMessage());
        }
    }

    public function getDocuments()
    {
        try {
            // Documents in M-Files are typically object type 0 (OT.Document)
            $response = $this->client->get('objects/0/0/latest', [
                'headers' => $this->getHeaders()
            ]);

            $status = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($status < 200 || $status >= 300) {
                $message = isset($result['Message']) ? $result['Message'] : 'M-Files list documents failed';
                throw new \RuntimeException($message . " (HTTP $status)");
            }

            return $result;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to fetch M-Files documents: ' . $e->getMessage());
        }
    }

    public function createClient($name)
    {
        $response = $this->client->post('objects/' . self::OBJECT_TYPE_CLIENT, [
            'headers' => $this->getHeaders(),
            'json' => [
                'PropertyValues' => [
                    [
                        'PropertyDef' => self::PROPERTY_NAME,
                        'TypedValue' => ['DataType' => 1, 'Value' => $name]
                    ]
                ]
            ]
        ]);
        return json_decode($response->getBody(), true);
    }

    public function uploadDocument($file, $name)
    {
        // Créer d'abord l'objet document
        $docResponse = $this->client->post('objects/' . self::OBJECT_TYPE_DOCUMENT, [
            'headers' => $this->getHeaders(),
            'json' => [
                'PropertyValues' => [
                    [
                        'PropertyDef' => self::PROPERTY_NAME,
                        'TypedValue' => ['DataType' => 1, 'Value' => $name]
                    ],
                    [
                        'PropertyDef' => self::PROPERTY_CLASS,
                        'TypedValue' => ['DataType' => 1, 'Value' => self::CLASS_DOCUMENT]
                    ]
                ]
            ]
        ]);
        
        $docData = json_decode($docResponse->getBody(), true);
        $objectId = $docData['ObjVer']['ID'];
        
        // Upload le fichier
        $fileStream = fopen($file->getRealPath(), 'r');
        $response = $this->client->post('objects/' . self::OBJECT_TYPE_DOCUMENT . "/$objectId/files", [
            'headers' => $this->getHeaders(),
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $fileStream,
                    'filename' => $file->getClientOriginalName()
                ]
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    }
}
