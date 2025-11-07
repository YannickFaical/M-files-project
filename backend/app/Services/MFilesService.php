<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;

class MFilesService
{
    private $client;
    private $baseUrl;
    private $vaultId;
    
    // IDs à remplacer selon votre configuration
    const OBJECT_TYPE_CLIENT = 0;  // À remplacer
    const OBJECT_TYPE_DOCUMENT = 1; // À remplacer
    const PROPERTY_NAME = 0; // À remplacer
    const PROPERTY_CLASS = 100; // À remplacer
    const CLASS_DOCUMENT = 0; // À remplacer

    public function __construct()
    {
        $this->client = new Client();
        $this->baseUrl = env('MFILES_BASE_URL', 'http://localhost/REST');
        $this->vaultId = env('MFILES_VAULT_ID');
    }

    public function authenticate($username, $password)
    {
        $response = $this->client->post($this->baseUrl . "/server/authenticationtokens", [
            'json' => [
                'Username' => $username,
                'Password' => $password,
                'VaultGuid' => $this->vaultId
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        // Optionnel : stocker en session si session disponible
        try {
            Session::put('mfiles_token', $result['Value']);
        } catch (\Throwable $e) {
            // session non disponible sur les routes API stateless, c'est ok
        }

        return $result;
    }

    private function getHeaders()
    {
        // Priorité : header X-Authentication de la requête, sinon session
        $token = request()->header('X-Authentication') ?? Session::get('mfiles_token');

        if ($token) {
            return [
                'X-Authentication' => $token,
            ];
        }

        return [];
    }

    public function getClients()
    {
        $response = $this->client->get($this->baseUrl . "/objects/" . self::OBJECT_TYPE_CLIENT . "/0/latest", [
            'headers' => $this->getHeaders()
        ]);
        return json_decode($response->getBody(), true);
    }

    public function createClient($name)
    {
        $response = $this->client->post($this->baseUrl . "/objects/" . self::OBJECT_TYPE_CLIENT, [
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
        $docResponse = $this->client->post($this->baseUrl . "/objects/" . self::OBJECT_TYPE_DOCUMENT, [
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
        $response = $this->client->post($this->baseUrl . "/objects/" . self::OBJECT_TYPE_DOCUMENT . "/$objectId/files", [
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
