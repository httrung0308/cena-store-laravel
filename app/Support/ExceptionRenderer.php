<?php

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ExceptionRenderer
{
    /** @var list<string> */
    public const SENSITIVE_INPUTS = ['current_password', 'password', 'password_confirmation', 'otp', 'otp_code', 'token', '_token', 'destination', 'bank_account', 'account_number'];

    public static function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson() || $request->ajax();
    }

    public function render(Throwable $exception, Request $request): ?Response
    {
        if ($exception instanceof HttpResponseException) {
            return null;
        }

        $status = $exception instanceof DomainException ? $exception->errorCode->httpStatus() : match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof TokenMismatchException => 419,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
        $code = $exception instanceof DomainException ? $exception->errorCode : match ($status) {
            401 => ErrorCode::Unauthenticated,
            403 => ErrorCode::Forbidden,
            404 => ErrorCode::NotFound,
            405 => ErrorCode::MethodNotAllowed,
            409 => ErrorCode::Conflict,
            419 => ErrorCode::SessionExpired,
            422 => ErrorCode::ValidationFailed,
            429 => ErrorCode::TooManyRequests,
            503 => ErrorCode::ServiceUnavailable,
            default => $status >= 500 ? ErrorCode::InternalError : ErrorCode::BadRequest,
        };
        $errors = match (true) {
            $exception instanceof DomainException => $exception->errors,
            $exception instanceof ValidationException => $exception->errors(),
            default => [],
        };
        $headers = $exception instanceof HttpExceptionInterface ? array_intersect_key($exception->getHeaders(), array_flip(['Retry-After', 'Allow', 'WWW-Authenticate'])) : [];
        if (self::wantsJson($request)) {
            return ApiResponse::error($request, $code, $errors, $status, $headers);
        }
        if ($exception instanceof DomainException && in_array($status, [409, 422], true) && $request->hasSession()) {
            return redirect()->back()
                ->withErrors($errors ?: ['business' => [$code->message()]])
                ->with('error', $code->message())->with('error_code', $code->value)
                ->withInput($request->except(self::SENSITIVE_INPUTS))
                ->header('X-Request-ID', RequestIdentity::get($request));
        }
        if ($exception instanceof ValidationException) {
            return null;
        }
        if ($exception instanceof AuthenticationException && Route::has('login')) {
            return redirect()->guest(route('login'))->header('X-Request-ID', RequestIdentity::get($request));
        }

        return response($code->message().' Mã tra cứu: '.RequestIdentity::get($request), $status, $headers)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('X-Request-ID', RequestIdentity::get($request));
    }
}
