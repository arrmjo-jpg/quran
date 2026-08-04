<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Infrastructure\Database\Models\AuditLogModel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class AuditLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $user = $request->user();
        $requestSize = strlen($request->getContent());
        $responseContent = $response->getContent();

        AuditLogModel::query()->create([
            'id' => (string) Uuid::v7(),
            'correlation_id' => $request->header('X-Correlation-ID'),
            'execution_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'actor_id' => $user?->id,
            'actor_type' => $user?->type,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => $request->route()?->getName(),
            'response_status' => $response->getStatusCode(),
            'device_id' => $request->header('X-Device-ID'),
            'request_size' => $requestSize > 0 ? $requestSize : null,
            'response_size' => $responseContent !== false && $responseContent !== '' ? strlen($responseContent) : null,
        ]);

        return $response;
    }
}
