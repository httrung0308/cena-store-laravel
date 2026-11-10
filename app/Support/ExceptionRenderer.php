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

        $requestId = RequestIdentity::get($request);
        if ($request->acceptsHtml()) {
            $html = self::renderHtmlError($code, $status, $requestId, $headers['Retry-After'] ?? null);

            return response($html, $status, $headers)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('X-Request-ID', $requestId);
        }

        return response($code->message().' Mã tra cứu: '.$requestId, $status, $headers)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('X-Request-ID', $requestId);
    }

    private static function renderHtmlError(ErrorCode $code, int $status, string $requestId, ?string $retryAfter): string
    {
        $title = match ($status) {
            403 => 'Truy cập bị từ chối',
            404 => 'Không tìm thấy trang',
            419 => 'Phiên làm việc đã hết hạn',
            429 => 'Thao tác quá nhanh',
            500 => 'Đã xảy ra lỗi hệ thống',
            default => 'Thông báo hệ thống',
        };

        $description = match ($status) {
            419 => 'Phiên làm việc hoặc mã xác thực (CSRF) của bạn đã hết hạn. Vui lòng tải lại trang để tiếp tục.',
            429 => 'Hệ thống nhận được nhiều yêu cầu trong thời gian ngắn. Vui lòng đợi một lát rồi thực hiện lại.',
            default => $code->message(),
        };

        $retryNotice = '';
        if ($retryAfter !== null && is_numeric($retryAfter)) {
            $retryNotice = '<p style="margin-top: 10px; font-size: 13px; color: #b45309; background: #fef3c7; padding: 8px 12px; border-radius: 8px; border: 1px solid #fde68a;">Vui lòng đợi <strong>'.$retryAfter.' giây</strong> trước khi thao tác tiếp.</p>';
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $safeRequestId = htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} — Cena Store</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #0b0f19;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .card {
            background-color: #131b2e;
            border: 1px solid #23314d;
            border-radius: 20px;
            padding: 36px 32px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5);
        }
        .badge {
            display: inline-block;
            background-color: #201464;
            color: #c7d2fe;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 16px;
            letter-spacing: 0.05em;
        }
        h1 { font-size: 22px; font-weight: 700; color: #ffffff; margin-bottom: 12px; }
        p.desc { font-size: 14px; color: #94a3b8; line-height: 1.6; margin-bottom: 20px; }
        .meta {
            font-size: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: #64748b;
            background: #0b0f19;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #1e293b;
            margin-bottom: 24px;
            word-break: break-all;
        }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
        }
        .btn-primary { background-color: #201464; color: #ffffff; border: 1px solid #3b2c96; }
        .btn-primary:hover { background-color: #2e1d8a; }
        .btn-secondary { background-color: #1e293b; color: #e2e8f0; border: 1px solid #334155; }
        .btn-secondary:hover { background-color: #334155; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">HTTP {$status}</span>
        <h1>{$safeTitle}</h1>
        <p class="desc">{$safeDesc}</p>
        {$retryNotice}
        <div class="meta">Mã tra cứu: {$safeRequestId}</div>
        <div class="actions">
            <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn btn-primary">← Quay lại trang trước</button>
            <a href="/" class="btn btn-secondary">Về trang chủ</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
