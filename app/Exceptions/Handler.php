<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->wantsJson()) {
            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Data tidak ditemukan!'], 404);
            }

            // if ($e instanceof AuthorizationException) {
            //     dd('AuthorizationException');
            //     // throw new GenericAuthorizationException($e->getMessage());
            // }

            if ($e instanceof HttpException && $e->getStatusCode() == 403) {
                $message = $e->getMessage() ?? null;
                $message = $message ? $message : 'Forbidden!';
                return response()->json(['message' => $message], 403);
            }

            // if ($e instanceof UnauthorizedException) {
            //     return response()->json(['error' => 'Not authorized.'], 403);
            // }
        }

        return parent::render($request, $e);
    }
}
