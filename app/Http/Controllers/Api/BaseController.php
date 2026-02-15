<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    protected function getRequestId(): ?string
    {
        $request = app(Request::class);

        return $request->attributes->get('request_id');
    }

    /**
     * Send a success response.
     */
    protected function sendResponse(mixed $data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'request_id' => $this->getRequestId(),
        ];

        // Keep backward compatibility while exposing pagination metadata at top-level.
        if (is_array($data) && array_key_exists('meta', $data) && array_key_exists('data', $data)) {
            $response['meta'] = $data['meta'];
        }

        if (is_array($data) && array_key_exists('links', $data) && is_array($data['links'])) {
            $response['links'] = $data['links'];
        }

        return response()->json($response, $code);
    }

    /**
     * Send an error response.
     */
    protected function sendError(string $message = 'Error', array $errors = [], int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'request_id' => $this->getRequestId(),
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
