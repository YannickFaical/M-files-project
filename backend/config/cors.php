<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Liste d'origines séparées par des virgules dans .env (ex: http://localhost:5173,http://localhost:3000)
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // IMPORTANT : true si vous envoyez des cookies / credentials depuis le front (credentials: 'include')
    'supports_credentials' => true,
];
