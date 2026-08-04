<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/**
 * HealthCheckController
 *
 * GET /api/v1/health
 * Public health monitoring endpoint that probes MySQL, Redis, and Meilisearch.
 * Follows ADR-014 response format.
 */
final class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = $this->checkDatabase();
        $redisOk = $this->checkRedis();
        $meiliOk = $this->checkMeilisearch();

        $allOk = $dbOk && $redisOk && $meiliOk;
        $status = $allOk ? 200 : 503;

        return response()->json([
            'success' => $allOk,
            'data' => [
                'status' => $allOk ? 'healthy' : 'unhealthy',
                'services' => [
                    'database' => $dbOk ? 'ok' : 'failed',
                    'redis' => $redisOk ? 'ok' : 'failed',
                    'meilisearch' => $meiliOk ? 'ok' : 'failed',
                ],
            ],
        ], $status);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkMeilisearch(): bool
    {
        try {
            $host = config('scout.meilisearch.host', 'http://meilisearch:7700');
            $res = Http::timeout(2)->get($host.'/health');

            return $res->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
