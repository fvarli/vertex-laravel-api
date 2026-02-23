<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SparseFieldsets
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $response instanceof JsonResponse) {
            return $response;
        }

        $fields = $request->query('fields');
        if (! $fields || ! is_string($fields)) {
            return $response;
        }

        $allowedFields = array_map('trim', explode(',', $fields));
        $allowedFields = array_filter($allowedFields, fn ($f) => $f !== '');

        if (empty($allowedFields)) {
            return $response;
        }

        $data = $response->getData(true);

        if (! isset($data['data'])) {
            return $response;
        }

        $data['data'] = $this->filterData($data['data'], $allowedFields);
        $response->setData($data);

        return $response;
    }

    private function filterData(mixed $data, array $fields): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        // Paginated response: data.data is the array of items
        if (isset($data['data']) && is_array($data['data']) && array_is_list($data['data'])) {
            $data['data'] = array_map(
                fn ($item) => $this->pickFields($item, $fields),
                $data['data']
            );

            return $data;
        }

        // Collection (non-paginated list)
        if (array_is_list($data)) {
            return array_map(
                fn ($item) => $this->pickFields($item, $fields),
                $data
            );
        }

        // Single resource
        return $this->pickFields($data, $fields);
    }

    private function pickFields(mixed $item, array $fields): mixed
    {
        if (! is_array($item)) {
            return $item;
        }

        return array_intersect_key($item, array_flip($fields));
    }
}
