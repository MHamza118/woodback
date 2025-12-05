<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OneSignal Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OneSignal push notification service
    |
    */

    'app_id' => env('ONESIGNAL_APP_ID'),
    'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    
    'api_url' => 'https://onesignal.com/api/v1',
];