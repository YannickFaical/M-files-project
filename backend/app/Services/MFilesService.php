<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class MFilesService
{
    private $client;
    private $baseUrl;
    private $vaultId;
    private $hostHeader;
    private $objectTypeClientId = null;
    
    // IDs à adapter selon votre configuration M-Files
    // Par défaut : 0 = Document, 1 = classe "Other Document", 0 = "Name or Title", 100 = "Class"
    const OBJECT_TYPE_CLIENT = 0;  // À adapter si votre type "Client" a un ID différent
    const OBJECT_TYPE_DOCUMENT = 0;
    const PROPERTY_NAME = 0;
    const PROPERTY_CLASS = 100;
    const CLASS_DOCUMENT = 1;

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

    public function getDocumentFiles(int $objectId)
    {
        $response = $this->client->get('objects/0/' . $objectId . '/files', [
            'headers' => $this->getHeaders(),
        ]);
        $status = $response->getStatusCode();
        $result = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status >= 300) {
            $message = isset($result['Message']) ? $result['Message'] : 'M-Files list files failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }
        return $result;
    }

    public function updateObjectName(int $typeId, int $id, string $name)
    {
        return $this->updateSingleProperty($typeId, $id, self::PROPERTY_NAME, ['DataType' => 1, 'Value' => $name]);
    }

    public function deleteByType(int $typeId, int $id)
    {
        return $this->deleteObject($typeId, $id);
    }

    public function getFileContentResponse(int $objectId, int $fileId)
    {
        // Return the raw Guzzle response to stream content and headers
        return $this->client->get('objects/0/' . $objectId . '/files/' . $fileId . '/content', [
            'headers' => $this->getHeaders(),
            'stream' => true,
        ]);
    }

    public function getObject(int $typeId, int $id)
    {
        $response = $this->client->get('objects/' . $typeId . '/' . $id . '/latest', [
            'headers' => $this->getHeaders(),
        ]);
        $status = $response->getStatusCode();
        $result = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status >= 300) {
            $message = isset($result['Message']) ? $result['Message'] : 'M-Files get object failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }
        return $result;
    }

    public function updateObjectProperties(int $typeId, int $id, array $propertyValues)
    {
        // IIS compatibility: POST to properties.aspx with _method=PUT
        $response = $this->client->post('objects/' . $typeId . '/' . $id . '/latest/properties.aspx', [
            'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
            'query' => [
                '_method' => 'PUT',
            ],
            'json' => $propertyValues,
        ]);
        $status = $response->getStatusCode();
        $result = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status >= 300) {
            $message = isset($result['Message']) ? $result['Message'] : 'M-Files update properties failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }
        return $result;
    }

    private function updateSingleProperty(int $typeId, int $id, int $propDef, array $typedValue)
    {
        // Checkout
        $this->checkoutObject($typeId, $id);
        try {
            // IIS compatibility: POST to properties/{propDef}.aspx with _method=PUT
            $response = $this->client->post('objects/' . $typeId . '/' . $id . '/latest/properties/' . $propDef . '.aspx', [
                'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
                'query' => [
                    '_method' => 'PUT',
                ],
                'json' => [
                    'PropertyDef' => $propDef,
                    'TypedValue' => $typedValue,
                ],
            ]);
            $status = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);
            if ($status < 200 || $status >= 300) {
                $message = isset($result['Message']) ? $result['Message'] : 'M-Files update property failed';
                throw new \RuntimeException($message . " (HTTP $status)");
            }
            return $result;
        } finally {
            // Checkin regardless of success to avoid leaving checked-out
            try { $this->checkinObject($typeId, $id); } catch (\Throwable $e) { /* ignore */ }
        }
    }

    private function checkoutObject(int $typeId, int $id)
    {
        // IIS compatibility: POST to checkedout.aspx with _method=PUT and Value=2 (CheckedOutToMe)
        $resp = $this->client->post('objects/' . $typeId . '/' . $id . '/latest/checkedout.aspx', [
            'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
            'query' => [
                '_method' => 'PUT',
            ],
            'json' => [
                'Value' => 2,
            ],
        ]);
        $status = $resp->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = json_decode((string) $resp->getBody(), true);
            $msg = isset($body['Message']) ? $body['Message'] : 'M-Files checkout failed';
            throw new \RuntimeException($msg . " (HTTP $status)");
        }
    }

    private function checkinObject(int $typeId, int $id)
    {
        // IIS compatibility: POST to checkedout.aspx with _method=PUT and Value=0 (CheckedIn)
        $resp = $this->client->post('objects/' . $typeId . '/' . $id . '/latest/checkedout.aspx', [
            'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
            'query' => [
                '_method' => 'PUT',
            ],
            'json' => [
                'Value' => 0,
            ],
        ]);
        $status = $resp->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = json_decode((string) $resp->getBody(), true);
            $msg = isset($body['Message']) ? $body['Message'] : 'M-Files checkin failed';
            throw new \RuntimeException($msg . " (HTTP $status)");
        }
    }

    public function deleteObject(int $typeId, int $id)
    {
        $attempt = function () use ($typeId, $id) {
            // IIS compatibility: POST to deleted.aspx with _method=PUT and PrimitiveType<bool>
            Log::info('M-Files deleteObject attempt', [
                'typeId' => $typeId,
                'id' => $id,
                'endpoint' => 'objects/' . $typeId . '/' . $id . '/deleted.aspx',
            ]);
            return $this->client->post('objects/' . $typeId . '/' . $id . '/deleted.aspx', [
                'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
                'query' => [
                    '_method' => 'PUT',
                ],
                'json' => [
                    'Value' => true,
                ],
            ]);
        };

        $response = $attempt();
        $status = $response->getStatusCode();
        Log::info('M-Files deleteObject response', [
            'typeId' => $typeId,
            'id' => $id,
            'status' => $status,
        ]);
        if ($status < 200 || $status >= 300) {
            // If conflict or checkout state, try checkin then retry once
            if ($status === 409) {
                try { $this->checkinObject($typeId, $id); } catch (\Throwable $e) { /* ignore */ }
                $response = $attempt();
                $status = $response->getStatusCode();
                Log::info('M-Files deleteObject response after checkin', [
                    'typeId' => $typeId,
                    'id' => $id,
                    'status' => $status,
                ]);
            }

            // Some setups may still return 405; fallback to destroy all versions via IIS-compatible endpoint
            if ($status === 405) {
                $response = $this->client->post('objects/' . $typeId . '/' . $id . '/latest.aspx', [
                    'headers' => $this->getHeaders(),
                    'query' => [
                        '_method' => 'DELETE',
                        'allVersions' => 'true',
                    ],
                ]);
                $status = $response->getStatusCode();
                Log::info('M-Files deleteObject fallback destroy response', [
                    'typeId' => $typeId,
                    'id' => $id,
                    'status' => $status,
                ]);
            }
        }

        if ($status < 200 || $status >= 300) {
            $body = json_decode((string) $response->getBody(), true);
            Log::error('M-Files deleteObject failed', [
                'typeId' => $typeId,
                'id' => $id,
                'status' => $status,
                'body' => $body,
            ]);
            $message = isset($body['Message']) ? $body['Message'] : 'M-Files delete failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }
        return [ 'success' => true ];
    }

    // Convenience: Clients
    public function getClient(int $id)
    {
        $typeId = $this->resolveClientTypeId();
        return $this->getObject($typeId, $id);
    }

    public function updateClientName(int $id, string $name)
    {
        $typeId = $this->resolveClientTypeId();
        return $this->updateSingleProperty($typeId, $id, self::PROPERTY_NAME, ['DataType' => 1, 'Value' => $name]);
    }

    public function deleteClient(int $id)
    {
        $typeId = $this->resolveClientTypeId();
        return $this->deleteObject($typeId, $id);
    }

    // Convenience: Documents (type 0)
    public function getDocument(int $id)
    {
        return $this->getObject(0, $id);
    }

    public function updateDocumentName(int $id, string $name)
    {
        return $this->updateSingleProperty(0, $id, self::PROPERTY_NAME, ['DataType' => 1, 'Value' => $name]);
    }

    public function deleteDocument(int $id)
    {
        return $this->deleteObject(0, $id);
    }

    public function createDocument(string $name)
    {
        $response = $this->client->post('objects/0', [
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
        $status = $response->getStatusCode();
        $result = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status >= 300) {
            $message = isset($result['Message']) ? $result['Message'] : 'M-Files create document failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }
        return $result;
    }

    private function getHeaders($tokenOverride = null)
    {
        // Priorité : header X-Authentication de la requête, sinon session
        $token = $tokenOverride
            ?? request()->header('X-Authentication')
            ?? request()->query('token')
            ?? Session::get('mfiles_token');

        $headers = [
            'Host' => $this->hostHeader,
            'Accept' => 'application/json',
            'Connection' => 'close',
        ];

        if ($token) {
            $headers['X-Authentication'] = $token;
        }

        return $headers;
    }

    private function resolveClientTypeId()
    {
        if ($this->objectTypeClientId !== null) {
            return $this->objectTypeClientId;
        }

        // 1) Check env override
        $fromEnv = env('MFILES_OBJECT_TYPE_CLIENT');
        if (!empty($fromEnv) && is_numeric($fromEnv)) {
            $this->objectTypeClientId = (int) $fromEnv;
            return $this->objectTypeClientId;
        }

        // 2) Try to resolve by name using current request token
        $name = env('MFILES_OBJECT_TYPE_CLIENT_NAME', 'Client');
        try {
            $resp = $this->client->get('structure/objecttypes', [
                'headers' => $this->getHeaders()
            ]);
            $status = $resp->getStatusCode();
            $list = json_decode((string) $resp->getBody(), true);
            if ($status >= 200 && $status < 300 && is_array($list)) {
                foreach ($list as $ot) {
                    if (isset($ot['Name']) && strcasecmp($ot['Name'], $name) === 0 && isset($ot['ID'])) {
                        $this->objectTypeClientId = (int) $ot['ID'];
                        return $this->objectTypeClientId;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore, will fallback
            Log::error('MFilesService::resolveClientTypeId: ' . $e->getMessage());
        }

        // Fallback to placeholder (must be replaced in .env if different)
        $this->objectTypeClientId = self::OBJECT_TYPE_CLIENT;
        return $this->objectTypeClientId;
    }

    public function probe($token)
    {
        try {
            $resp = $this->client->get('structure/objecttypes', [
                'headers' => $this->getHeaders($token),
                'timeout' => 8.0,
            ]);
            $status = $resp->getStatusCode();
            return $status >= 200 && $status < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getClients(int $page = 1, int $perPage = 20)
    {
        try {
            $typeId = $this->resolveClientTypeId();
            // Prefer collection endpoint
            $response = $this->client->get('objects/' . $typeId, [
                'headers' => $this->getHeaders(),
                'query' => [
                    'limit' => max(1, $perPage),
                    'offset' => max(0, ($page - 1) * $perPage),
                ],
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

    public function getDocuments(int $page = 1, int $perPage = 20)
    {
        try {
            // Documents in M-Files are typically object type 0 (OT.Document)
            $response = $this->client->get('objects/0', [
                'headers' => $this->getHeaders(),
                'query' => [
                    'limit' => max(1, $perPage),
                    'offset' => max(0, ($page - 1) * $perPage),
                ],
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
        $typeId = $this->resolveClientTypeId();

        $response = $this->client->post('objects/' . $typeId, [
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
        // 1) Upload brut du fichier vers /files.aspx pour obtenir un UploadInfo
        $fileStream = fopen($file->getRealPath(), 'r');
        $uploadResponse = $this->client->post('files.aspx', [
            'headers' => array_merge($this->getHeaders(), [
                'Content-Type' => 'application/octet-stream',
            ]),
            'body' => $fileStream,
        ]);

        $status = $uploadResponse->getStatusCode();
        $uploadInfo = json_decode((string) $uploadResponse->getBody(), true);
        if ($status < 200 || $status >= 300) {
            $message = isset($uploadInfo['Message']) ? $uploadInfo['Message'] : 'M-Files file upload failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }

        // S'assurer que l'extension et le titre sont correctement renseignés
        $originalName = $file->getClientOriginalName();
        $dotPos = strrpos($originalName, '.');
        $extension = $dotPos !== false ? substr($originalName, $dotPos + 1) : '';
        $title = $dotPos !== false ? substr($originalName, 0, $dotPos) : $originalName;

        $uploadInfo['Extension'] = $extension;
        $uploadInfo['Title'] = $title;

        // 2) Créer l'objet document en utilisant UploadInfo dans Files
        $objectCreationInfo = [
            'PropertyValues' => [
                [
                    'PropertyDef' => self::PROPERTY_CLASS,
                    'TypedValue' => [
                        'DataType' => 9, // Lookup
                        'Lookup' => [
                            'Item' => self::CLASS_DOCUMENT,
                            'Version' => -1,
                        ],
                    ],
                ],
                [
                    'PropertyDef' => self::PROPERTY_NAME,
                    'TypedValue' => [
                        'DataType' => 1, // Text
                        'Value' => $name,
                    ],
                ],
            ],
            'Files' => [ $uploadInfo ],
        ];

        $docResponse = $this->client->post('objects/' . self::OBJECT_TYPE_DOCUMENT . '.aspx', [
            'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
            'json' => $objectCreationInfo,
        ]);

        $status = $docResponse->getStatusCode();
        $docData = json_decode((string) $docResponse->getBody(), true);
        if ($status < 200 || $status >= 300) {
            $message = isset($docData['Message']) ? $docData['Message'] : 'M-Files create document with file failed';
            throw new \RuntimeException($message . " (HTTP $status)");
        }

        return $docData;
    }
}
