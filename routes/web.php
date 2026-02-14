<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => false,
        'message' => __('api.web.api_only'),
    ], 403);
});

Route::any('/{any}', function () {
    return response()->json([
        'success' => false,
        'message' => __('api.web.api_only'),
    ], 403);
})->where('any', '^(?!api).*$');
