<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FCM Credentials Path
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase Service Account JSON file used for OAuth2
    | authentication with the FCM v1 API.
    |
    */
    'credentials_path' => env('FCM_CREDENTIALS_PATH', storage_path('firebase-credentials.json')),

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
