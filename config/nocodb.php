<?php

return [
    'api_url' => env('NOCODB_API_URL', 'https://app.nocodb.com'),
    'api_token' => env('NOCODB_API_TOKEN'),
    'project' => env('NOCODB_PROJECT'), // Project ID (e.g., p_xxxx)
    'workspace' => env('NOCODB_WORKSPACE'), // Optional if needed
];
