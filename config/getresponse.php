<?php

return [
    /*
     * Api Information
     */
    'api_key' => env('GETRESPONSE_API_KEY', ''),
    'accessToken' => env('GETRESPONSE_ACCESS_TOKEN', ''),
    'use_access_token_authentication' => env('GETRESPONSE_USE_ACCESS_TOKEN_AUTHENTICATION', false),
    'is_enterprise' => env('GETRESPONSE_IS_ENTERPRISE', false),
    'domain' => env('GETRESPONSE_DOMAIN', ''),
    'max_server' => env('GETRESPONSE_MAX_SERVER', 'US'), // As of now, "US" or "PL", case-sensitive!
];
