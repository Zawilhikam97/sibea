<?php

return [
    'siakad' => [
        'base_url' => env('SIAKAD_API_URL'),
        'auth_token' => env('SIAKAD_API_TOKEN'),
        'timeout' => env('SIAKAD_API_TIMEOUT', 10),
    ],
];
