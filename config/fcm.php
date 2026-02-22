<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FCM Server Key
    |--------------------------------------------------------------------------
    |
    | The server key for Firebase Cloud Messaging (legacy HTTP API).
    |
    */
    'server_key' => env('FCM_SERVER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | FCM Project ID
    |--------------------------------------------------------------------------
    |
    | The Firebase project ID for FCM v1 API.
    |
    */
    'project_id' => env('FCM_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | FCM Enabled
    |--------------------------------------------------------------------------
    |
    | Set to false to disable actual FCM HTTP calls (useful for testing).
    |
    */
    'enabled' => env('FCM_ENABLED', false),

];
