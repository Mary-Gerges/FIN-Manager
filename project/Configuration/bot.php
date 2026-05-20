<?php
// bootstrap/app.php (Laravel 11) or app/Http/Kernel.php (Laravel 10)
// Add Sanctum middleware to api group

// For Laravel 11 — bootstrap/app.php:
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Throttle: 60 requests/minute for api, 5/minute for login
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for all API exceptions
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                $status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = $e->getMessage() ?: 'Server error';

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);
                }
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json(['success'=>false,'message'=>'Resource not found'], 404);
                }
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                    return response()->json(['success'=>false,'message'=>'Too many requests. Please slow down.'], 429);
                }
                if (app()->environment('production')) {
                    $message = $status >= 500 ? 'Internal server error' : $message;
                }

                return response()->json(['success'=>false,'message'=>$message], $status);
            }
        });
    })->create();
