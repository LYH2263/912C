<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    protected function handleApiException($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'code' => 401,
                'message' => '未授权，请重新登录',
                'data' => null,
            ], 401);
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'code' => 422,
                'message' => '数据验证失败',
                'data' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'code' => 404,
                'message' => '资源不存在',
                'data' => null,
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'code' => 404,
                'message' => '接口不存在',
                'data' => null,
            ], 404);
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return response()->json([
                'code' => 403,
                'message' => '没有权限访问',
                'data' => null,
            ], 403);
        }

        if ($exception instanceof QueryException) {
            \Log::error('Database query error', [
                'message' => $exception->getMessage(),
                'sql' => $exception->getSql(),
            ]);

            return response()->json([
                'code' => 500,
                'message' => '数据库操作失败',
                'data' => null,
            ], 500);
        }

        $statusCode = method_exists($exception, 'getStatusCode')
            ? $exception->getStatusCode()
            : 500;

        $message = $exception->getMessage() ?: '服务器错误';

        if (config('app.debug')) {
            return response()->json([
                'code' => $statusCode,
                'message' => $message,
                'data' => [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ],
            ], $statusCode);
        }

        return response()->json([
            'code' => $statusCode,
            'message' => $statusCode === 500 ? '服务器错误' : $message,
            'data' => null,
        ], $statusCode);
    }
}
