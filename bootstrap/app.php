<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\RequestId;
use App\Support\ExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestId::class);
        $middleware->redirectGuestsTo(fn (Request $request): ?string => ExceptionRenderer::wantsJson($request) || ! Route::has('login') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(DomainException::class);
        $exceptions->dontFlash(ExceptionRenderer::SENSITIVE_INPUTS);
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => ExceptionRenderer::wantsJson($request));
        $exceptions->render(fn (Throwable $exception, Request $request): ?Response => app(ExceptionRenderer::class)->render($exception, $request));
    })->create();
