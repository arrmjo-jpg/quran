<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $cacheKey = "idempotency:{$request->user()?->id}:{$key}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()->json($cached['content'], $cached['status'], [
                'X-Cache' => 'HIT-IDEMPOTENCY',
                'X-Idempotency-Key' => $key,
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->isSuccessful()) {
            $content = json_decode((string) $response->getContent(), true);
            Cache::put($cacheKey, [
                'content' => $content,
                'status' => $response->getStatusCode(),
            ], 86400); // 24 hours
        }

        return $response;
    }
}
