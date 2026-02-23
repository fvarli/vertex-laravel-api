<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessRuleException extends Exception
{
    protected int $statusCode = 422;

    protected array $errorData = [];

    public function __construct(string $message = '', array $errorData = [], int $statusCode = 422)
    {
        parent::__construct($message);
        $this->errorData = $errorData;
        $this->statusCode = $statusCode;
    }

    public function render(Request $request): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->getMessage(),
        ];

        if (! empty($this->errorData)) {
            $response['errors'] = $this->errorData;
        }

        return response()->json($response, $this->statusCode);
    }
}
