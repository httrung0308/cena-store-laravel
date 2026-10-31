<?php

namespace App\Http\Middleware;

use App\Support\RequestIdentity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $id = RequestIdentity::get($request);
        Log::withContext(['request_id' => $id]);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
