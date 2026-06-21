<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function success($data = null, string $message = 'success', array $meta = null): JsonResponse
    {
        return response()->apiSuccess($data, $message, 0, $meta);
    }

    protected function error(string $message = 'error', int $code = 400, $data = null, int $httpStatusCode = 200): JsonResponse
    {
        return response()->apiError($message, $code, $data, $httpStatusCode);
    }

    protected function paginated($items, $paginator, string $message = 'success'): JsonResponse
    {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return $this->success($items, $message, $meta);
    }
}
