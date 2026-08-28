<?php

declare(strict_types=1);

use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Interface\Http\ApiResponse;
use Game\World\Interface\Console\GenerateWorldCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
        then: function (): void {
            Broadcast::routes([
                'middleware' => ['api', 'auth:sanctum', App\Http\Middleware\CheckDeviceSession::class],
            ]);

            RateLimiter::for('auth', function (Request $request) {
                return Limit::perMinute(max(1, (int) config('game.auth.rate_limit_per_minute', 10)))
                    ->by($request->ip().'|'.($request->input('email') ?? $request->input('device_id')));
            });

            RateLimiter::for('institutional-support', function (Request $request) {
                $email = mb_strtolower(trim((string) $request->input('email', '')));
                $key = hash('sha256', $email.'|'.$request->ip());

                return Limit::perMinute(max(1, (int) config('institutional.support_rate_limit_per_minute', 5)))
                    ->by($key);
            });
        },
    )
    ->withCommands([GenerateWorldCommand::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            Game\Shared\Interface\Http\Middleware\AttachRequestContext::class,
        ]);

        // Never trust a client-declared IP unless it comes through our own edge.
        $middleware->trustProxies(at: explode(',', (string) env('TRUSTED_PROXIES', '')));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every API failure leaves through one funnel so the client only ever
        // parses one error shape. See docs/api/api-guidelines.md.
        $exceptions->render(static function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return match (true) {
                $e instanceof GameException => ApiResponse::error(
                    $e->errorCode,
                    $e->getMessage(),
                    $e->details,
                ),
                $e instanceof ValidationException => ApiResponse::error(
                    ErrorCode::ValidationFailed,
                    'The submitted data is invalid.',
                    ['fields' => $e->errors()],
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    ErrorCode::Unauthenticated,
                    'Authentication is required.',
                ),
                $e instanceof AuthorizationException => ApiResponse::error(
                    ErrorCode::Forbidden,
                    'This action is not allowed.',
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    ErrorCode::NotFound,
                    'The requested resource does not exist.',
                ),
                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    ErrorCode::RateLimited,
                    'Too many requests.',
                ),
                default => null,
            };
        });
    })->create();
