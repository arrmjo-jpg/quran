<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-ID');
        $correlationId = ($incoming && Uuid::isValid($incoming)) ? $incoming : (string) Uuid::v7();

        $request->headers->set('X-Correlation-ID', $correlationId);

        Log::shareContext(['correlation_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
