<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Response::macro('apiSuccess', function ($data = null, string $message = 'success', int $code = 0, array $meta = null) {
            $response = [
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ];

            if ($meta !== null) {
                $response['meta'] = $meta;
            }

            return response()->json($response, 200);
        });

        Response::macro('apiError', function (string $message = 'error', int $code = 400, $data = null, int $httpStatusCode = 200) {
            return response()->json([
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ], $httpStatusCode);
        });
    }
}
