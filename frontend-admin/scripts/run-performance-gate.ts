import fs from 'fs';
import path from 'path';
import { execSync, spawn, spawnSync, ChildProcess } from 'child_process';
import { measureBundle } from './measure-bundle';
import { measureApiLatency } from './measure-api-latency';

const ROOT = process.cwd();
const REPORTS_DIR = path.join(ROOT, 'artifacts', 'reports', 'performance');
const PREVIEW_PORT = 4173;
const PREVIEW_URL = `http://localhost:${PREVIEW_PORT}/login`;
const API_BASE_URL = process.env.PERF_API_BASE_URL ?? 'http://localhost:8080';

// PERF_ENFORCE=1 switches this gate from baseline (MEASURED, always
// exits 0 as long as the tools ran) to enforcement (PASS/FAIL against
// these thresholds, non-zero exit on FAIL). Stays in baseline mode
// until real staging numbers have been reviewed and these are
// deliberately confirmed as the final policy — see the RC1.1 thread.
const ENFORCE = process.env.PERF_ENFORCE === '1';

const THRESHOLD_CANDIDATES = {
  lighthouseDesktopPerf: 80,
  lighthouseMobilePerf: 60,
  accessibility: 90,
  bestPractices: 90,
  lcpMs: 2500,
  clsScore: 0.1,
  inpMs: 200,
  apiP95Ms: 300,
  apiP99Ms: 500,
  initialJsKb: 400,
};

function sh(cmd: string): void {
  console.log(`[performance-gate] $ ${cmd}`);
  execSync(cmd, { stdio: 'inherit', cwd: ROOT });
}

/**
 * child.kill() on Windows only terminates the process it was actually
 * given a PID for. When that process was launched via `spawn(..., {shell:
 * true})`, the PID belongs to the cmd.exe wrapper, not the real node/vite
 * process underneath it — killing the wrapper leaves vite orphaned and
 * still bound to the port. Spawning vite's own entrypoint directly (no
 * shell) avoids the wrapper entirely; `taskkill /T /F` is used as a
 * belt-and-suspenders in case vite itself ever spawns children.
 */
function killProcessTree(child: ChildProcess): void {
  if (!child.pid) return;
  if (process.platform === 'win32') {
    spawnSync('taskkill', ['/pid', String(child.pid), '/T', '/F']);
  } else {
    child.kill('SIGTERM');
  }
}

function waitForServer(url: string, timeoutMs: number): Promise<void> {
  const start = Date.now();
  return new Promise((resolve, reject) => {
    const tick = async () => {
      try {
        const res = await fetch(url);
        if (res.status < 500) return resolve();
      } catch {
        // not ready yet
      }
      if (Date.now() - start > timeoutMs) {
        return reject(new Error(`Server at ${url} did not become ready within ${timeoutMs}ms`));
      }
      setTimeout(tick, 500);
    };
    tick();
  });
}

function runLighthouse(url: string, outputPath: string, preset: 'mobile' | 'desktop'): Record<string, unknown> {
  const presetFlag = preset === 'desktop' ? '--preset=desktop' : '';
  const chromePath = process.env.CHROME_PATH ? `--chrome-path="${process.env.CHROME_PATH}"` : '';
  sh(
    `npx lighthouse "${url}" ${presetFlag} ${chromePath} --output=json --output-path="${outputPath}" ` +
      `--chrome-flags="--headless --no-sandbox --disable-gpu" --quiet`
  );
  return JSON.parse(fs.readFileSync(outputPath, 'utf-8'));
}

function extractLighthouseSummary(report: Record<string, unknown>) {
  const categories = report.categories as Record<string, { score: number }>;
  const audits = report.audits as Record<string, { numericValue?: number }>;

  return {
    performance: Math.round(categories.performance.score * 100),
    accessibility: Math.round(categories.accessibility.score * 100),
    best_practices: Math.round(categories['best-practices'].score * 100),
    seo: Math.round(categories.seo.score * 100),
    lcp_ms: audits['largest-contentful-paint']?.numericValue
      ? Math.round(audits['largest-contentful-paint'].numericValue)
      : null,
    cls: audits['cumulative-layout-shift']?.numericValue ?? null,
    inp_ms: audits['interaction-to-next-paint']?.numericValue
      ? Math.round(audits['interaction-to-next-paint'].numericValue)
      : null,
  };
}

function evaluateThresholds(
  desktop: ReturnType<typeof extractLighthouseSummary>,
  mobile: ReturnType<typeof extractLighthouseSummary>,
  latency: Awaited<ReturnType<typeof measureApiLatency>>,
  bundle: ReturnType<typeof measureBundle>
): string[] {
  const t = THRESHOLD_CANDIDATES;
  const failures: string[] = [];

  if (desktop.performance < t.lighthouseDesktopPerf) {
    failures.push(`desktop performance ${desktop.performance} < ${t.lighthouseDesktopPerf}`);
  }
  if (mobile.performance < t.lighthouseMobilePerf) {
    failures.push(`mobile performance ${mobile.performance} < ${t.lighthouseMobilePerf}`);
  }
  if (desktop.accessibility < t.accessibility) {
    failures.push(`desktop accessibility ${desktop.accessibility} < ${t.accessibility}`);
  }
  if (desktop.best_practices < t.bestPractices) {
    failures.push(`desktop best_practices ${desktop.best_practices} < ${t.bestPractices}`);
  }
  if (desktop.lcp_ms !== null && desktop.lcp_ms > t.lcpMs) {
    failures.push(`LCP ${desktop.lcp_ms}ms > ${t.lcpMs}ms`);
  }
  if (desktop.cls !== null && desktop.cls > t.clsScore) {
    failures.push(`CLS ${desktop.cls} > ${t.clsScore}`);
  }
  if (desktop.inp_ms !== null && desktop.inp_ms > t.inpMs) {
    failures.push(`INP ${desktop.inp_ms}ms > ${t.inpMs}ms`);
  }
  if (latency.p95_ms > t.apiP95Ms) {
    failures.push(`API p95 ${latency.p95_ms}ms > ${t.apiP95Ms}ms`);
  }
  if (latency.p99_ms > t.apiP99Ms) {
    failures.push(`API p99 ${latency.p99_ms}ms > ${t.apiP99Ms}ms`);
  }
  if (bundle.initial_js_kb > t.initialJsKb) {
    failures.push(`initial JS ${bundle.initial_js_kb}KB > ${t.initialJsKb}KB`);
  }

  return failures;
}

async function main(): Promise<void> {
  fs.mkdirSync(REPORTS_DIR, { recursive: true });

  console.log(`[performance-gate] Mode: ${ENFORCE ? 'ENFORCE (PASS/FAIL)' : 'BASELINE (MEASURED)'}`);
  console.log('[performance-gate] Building production bundle...');
  sh('npm run build');

  const bundle = measureBundle(path.join(ROOT, 'dist'));

  console.log('[performance-gate] Starting preview server...');
  const viteBin = path.join(ROOT, 'node_modules', 'vite', 'bin', 'vite.js');
  const preview: ChildProcess = spawn(
    process.execPath,
    [viteBin, 'preview', '--port', String(PREVIEW_PORT), '--strictPort'],
    { cwd: ROOT, stdio: 'inherit' }
  );

  let lighthouseDesktopRaw: Record<string, unknown> | null = null;
  let lighthouseMobileRaw: Record<string, unknown> | null = null;
  let latency: Awaited<ReturnType<typeof measureApiLatency>> | null = null;

  try {
    await waitForServer(`http://localhost:${PREVIEW_PORT}/`, 20000);

    console.log('[performance-gate] Running Lighthouse (desktop)...');
    lighthouseDesktopRaw = runLighthouse(PREVIEW_URL, path.join(REPORTS_DIR, 'lighthouse-desktop.json'), 'desktop');

    console.log('[performance-gate] Running Lighthouse (mobile)...');
    lighthouseMobileRaw = runLighthouse(PREVIEW_URL, path.join(REPORTS_DIR, 'lighthouse-mobile.json'), 'mobile');

    console.log('[performance-gate] Measuring API latency against', API_BASE_URL, '(warm-up, then measured)...');
    latency = await measureApiLatency(API_BASE_URL, ['/up', '/api/v1/languages'], {
      warmupRequestsPerEndpoint: 10,
      measuredRequestsPerEndpoint: 100,
      concurrency: 5,
    });
    fs.writeFileSync(path.join(REPORTS_DIR, 'api-latency.json'), JSON.stringify(latency, null, 2));
  } finally {
    killProcessTree(preview);
  }

  const desktop = extractLighthouseSummary(lighthouseDesktopRaw!);
  const mobile = extractLighthouseSummary(lighthouseMobileRaw!);
  const failures = evaluateThresholds(desktop, mobile, latency!, bundle);

  const metrics = {
    lighthouse: { desktop, mobile },
    core_web_vitals: { lcp_ms: desktop.lcp_ms, cls: desktop.cls, inp_ms: desktop.inp_ms },
    api: latency,
    bundle,
  };

  const artifacts = [
    'artifacts/reports/performance/result.json',
    'artifacts/reports/performance/lighthouse-desktop.json',
    'artifacts/reports/performance/lighthouse-mobile.json',
    'artifacts/reports/performance/api-latency.json',
    'artifacts/reports/performance/bundle-analysis.html',
  ];

  const result = ENFORCE
    ? {
        gate: 'performance',
        status: failures.length === 0 ? 'PASS' : 'FAIL',
        baseline: false,
        thresholds: THRESHOLD_CANDIDATES,
        failures,
        metrics,
        artifacts,
        measured_at: new Date().toISOString(),
      }
    : {
        gate: 'performance',
        status: 'MEASURED',
        baseline: true,
        note: 'Baseline stage — thresholds are candidates only and do not gate status yet. Re-run with PERF_ENFORCE=1 once real staging numbers have been reviewed and the thresholds confirmed.',
        threshold_candidates: THRESHOLD_CANDIDATES,
        would_fail_if_enforced: failures,
        metrics,
        artifacts,
        measured_at: new Date().toISOString(),
      };

  fs.writeFileSync(path.join(REPORTS_DIR, 'result.json'), JSON.stringify(result, null, 2));

  console.log(`[performance-gate] Status: ${result.status}`);
  if (failures.length > 0) {
    console.log(`[performance-gate] ${ENFORCE ? 'Failures' : 'Would fail if enforced'}:`);
    failures.forEach((f) => console.log(`  - ${f}`));
  }

  if (ENFORCE && failures.length > 0) {
    process.exit(1);
  }
}

main().catch((err) => {
  console.error('[performance-gate] Fatal error:', err);
  process.exit(1);
});
