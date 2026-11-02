<?php

use App\Exceptions\ApiResponseException;
use App\Http\Middleware\EnsureLoyaltyAccountActive;
use App\Http\Middleware\ValidateLoyaltySignature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        },
        commands: __DIR__.'/../routes/console.php',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->preventRequestForgery(except: ['/api/*']);

        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->redirectGuestsTo(null);
        $middleware->redirectUsersTo('/home');

        $middleware->alias([
            'loyalty.signed' => ValidateLoyaltySignature::class,
            'loyalty.active' => EnsureLoyaltyAccountActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(ApiResponseException::class);

        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unauthenticated.',
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->wantsJson() && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Data tidak ditemukan!'], 404);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->wantsJson() || $e->getStatusCode() != 403 || $e->getPrevious() instanceof AuthorizationException) {
                return null;
            }

            $message = $e->getMessage() ?: 'Forbidden!';

            return response()->json(['message' => $message], 403);
        });
    })->create();
