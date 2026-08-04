export interface LatencyStats {
  p50_ms: number;
  p95_ms: number;
  p99_ms: number;
  samples: number;
  warmup_requests: number;
  endpoints: string[];
}

function percentile(sorted: number[], p: number): number {
  const idx = Math.min(sorted.length - 1, Math.ceil((p / 100) * sorted.length) - 1);
  return Math.round(sorted[Math.max(0, idx)] * 10) / 10;
}

async function fireBatches(
  baseUrl: string,
  endpoints: string[],
  requestsPerEndpoint: number,
  concurrency: number
): Promise<number[]> {
  const durations: number[] = [];

  for (const endpoint of endpoints) {
    const url = `${baseUrl}${endpoint}`;
    let remaining = requestsPerEndpoint;

    while (remaining > 0) {
      const batchSize = Math.min(concurrency, remaining);
      const batch = Array.from({ length: batchSize }, async () => {
        const start = performance.now();
        const response = await fetch(url);
        await response.arrayBuffer();
        return performance.now() - start;
      });

      const results = await Promise.all(batch);
      durations.push(...results);
      remaining -= batchSize;
    }
  }

  return durations;
}

/**
 * Two explicit phases against real, already-running endpoints
 * (Docker/nginx — not a mock):
 *
 *   1. Warm-up: a handful of requests per endpoint, fired and
 *      discarded entirely, so PHP-FPM/OPcache/Laravel's own bootstrap
 *      caches are hot before anything gets measured.
 *   2. Measurement: the real sample that p50/p95/p99 are computed
 *      from — nothing from the warm-up phase leaks into it.
 */
export async function measureApiLatency(
  baseUrl: string,
  endpoints: string[],
  options: { warmupRequestsPerEndpoint?: number; measuredRequestsPerEndpoint?: number; concurrency?: number } = {}
): Promise<LatencyStats> {
  const warmupRequestsPerEndpoint = options.warmupRequestsPerEndpoint ?? 10;
  const measuredRequestsPerEndpoint = options.measuredRequestsPerEndpoint ?? 100;
  const concurrency = options.concurrency ?? 5;

  await fireBatches(baseUrl, endpoints, warmupRequestsPerEndpoint, concurrency);

  const durations = await fireBatches(baseUrl, endpoints, measuredRequestsPerEndpoint, concurrency);
  durations.sort((a, b) => a - b);

  return {
    p50_ms: percentile(durations, 50),
    p95_ms: percentile(durations, 95),
    p99_ms: percentile(durations, 99),
    samples: durations.length,
    warmup_requests: warmupRequestsPerEndpoint * endpoints.length,
    endpoints,
  };
}
