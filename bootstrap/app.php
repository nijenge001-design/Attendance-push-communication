<?php

use App\Http\Middleware\LogRequestMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            require base_path('routes/iclock.php');
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'log-request' => LogRequestMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Clean JSON:API-style error responses for the API
        |--------------------------------------------------------------------------
        |
        | Missing models, 404s, validation errors, etc. return a short JSON
        | body — never a full stack trace to the client.
        |
        */

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            // Always JSON for /api/* (and when client asks for JSON)
            return $request->is('api/*') || $request->expectsJson();
        });


        // Model not found (route binding failed, findOrFail, etc.)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $model = class_basename($e->getModel());

            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'title'  => 'Not Found',
                    'detail' => "{$model} not found.",
                ]],
            ], 404)->header('Content-Type', 'application/vnd.api+json');
        });

        // Explicit 404 / NotFoundHttpException (including converted ModelNotFound)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Prefer a short message; avoid leaking internal details
            $detail = $e->getMessage() ?: 'The requested resource was not found.';

            // Laravel sometimes puts "No query results for model [App\Models\Device] SERIAL"
            // Strip the FQCN for a cleaner client message.
            if (preg_match('/No query results for model \[([^\]]+)\](?:\s+(.+))?/', $detail, $m)) {
                $model = class_basename($m[1]);
                $key   = isset($m[2]) ? trim($m[2]) : null;
                $detail = $key
                    ? "{$model} [{$key}] not found."
                    : "{$model} not found.";
            }

            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'title'  => 'Not Found',
                    'detail' => $detail,
                ]],
            ], 404)->header('Content-Type', 'application/vnd.api+json');
        });

        // Validation errors → JSON:API style
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = [
                        'status' => '422',
                        'title'  => 'Validation Error',
                        'detail' => $message,
                        'source' => ['pointer' => '/data/attributes/'.str_replace('.', '/', $field)],
                    ];
                }
            }

            return response()->json(['errors' => $errors], 422)
                ->header('Content-Type', 'application/vnd.api+json');
        });

        // Unauthenticated
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'errors' => [[
                    'status' => '401',
                    'title'  => 'Unauthenticated',
                    "detail"=> "Authentication is required to access this resource. Please ensure you are logged in and that your request includes valid credentials."
                ]],
            ], 401)->header('Content-Type', 'application/vnd.api+json');
        });

        // Forbidden (policy denials that are not "not found")
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'errors' => [[
                    'status' => '403',
                    'title'  => 'Forbidden',
                    'detail' => $e->getMessage() ?: 'Access denied. You are not authorized to view or interact with this specific resource.',
                ]],
            ], 403)->header('Content-Type', 'application/vnd.api+json');
        });

        // Other HTTP exceptions (405, 429, …)
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Skip ones we already handled above
            if ($e instanceof NotFoundHttpException) {
                return null;
            }

            $status = $e->getStatusCode();

            return response()->json([
                'errors' => [[
                    'status' => (string) $status,
                    'title'  => match ($status) {
                        405 => 'Method Not Allowed',
                        429 => 'Too Many Requests',
                        503 => 'Service Unavailable',
                        default => 'Error',
                    },
                    'detail' => $e->getMessage() ?: 'An error occurred.',
                ]],
            ], $status)->header('Content-Type', 'application/vnd.api+json');
        });

    })->create();
