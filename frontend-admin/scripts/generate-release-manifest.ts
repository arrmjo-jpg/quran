import fs from 'fs';
import path from 'path';
import crypto from 'crypto';

export type GateStatus = 'PENDING' | 'RUNNING' | 'MEASURED' | 'PASS' | 'FAIL';

export interface GateResult {
  gate:          string;
  status:        GateStatus;
  started_at?:   string;
  finished_at?:  string;
  duration_ms?:  number;
  artifacts?:    string[];
}

export interface ReleaseManifest {
  manifest_version: number;
  schema_version:   number;
  version:          string;
  git_commit:       string;
  build_time:       string;
  total_gates:      number;
  completed_gates:  number;
  ready_for_release:boolean;
  manifest_hash?:   string;
  gates: {
    functional:    GateStatus;
    security:      GateStatus;
    e2e:           GateStatus;
    performance:   GateStatus;
    reliability:   GateStatus;
    observability: GateStatus;
  };
}

export function generateReleaseManifest(): ReleaseManifest {
  const rootDir = process.cwd();
  const reportsDir = path.join(rootDir, 'artifacts', 'reports');
  const manifestDir = path.join(rootDir, 'artifacts', 'release');

  if (!fs.existsSync(manifestDir)) {
    fs.mkdirSync(manifestDir, { recursive: true });
  }

  const readGateResult = (gateName: string): GateStatus => {
    const resultFile = path.join(reportsDir, gateName, 'result.json');
    if (fs.existsSync(resultFile)) {
      try {
        const content = JSON.parse(fs.readFileSync(resultFile, 'utf-8')) as GateResult;
        return content.status || 'PENDING';
      } catch {
        return 'FAIL';
      }
    }
    return 'PENDING';
  };

  const gatesStatus = {
    functional:    readGateResult('functional'),
    security:      readGateResult('security'),
    e2e:           readGateResult('e2e'),
    performance:   readGateResult('performance'),
    reliability:   readGateResult('reliability'),
    observability: readGateResult('observability'),
  };

  const completedCount = Object.values(gatesStatus).filter((s) => s === 'PASS').length;
  const readyForRelease = completedCount === 6;

  const gitCommit = process.env.GITHUB_SHA || process.env.CI_COMMIT_SHA || 'unknown';

  if (process.env.CI && gitCommit === 'unknown') {
    console.error('[Manifest Generator] FATAL: CI environment detected but git_commit is unknown!');
    process.exit(1);
  }

  const baseManifest: Omit<ReleaseManifest, 'manifest_hash'> = {
    manifest_version: 1,
    schema_version: 1,
    version: 'v1.0.0-rc1',
    git_commit: gitCommit,
    build_time: new Date().toISOString(),
    total_gates: 6,
    completed_gates: completedCount,
    ready_for_release: readyForRelease,
    gates: gatesStatus,
  };

  // Compute SHA256 checksum integrity hash for manifest
  const checksumData = JSON.stringify(baseManifest, null, 0);
  const manifestHash = crypto.createHash('sha256').update(checksumData).digest('hex');

  const finalManifest: ReleaseManifest = {
    ...baseManifest,
    manifest_hash: manifestHash,
  };

  const outputPath = path.join(manifestDir, 'release_manifest.json');
  fs.writeFileSync(outputPath, JSON.stringify(finalManifest, null, 2), 'utf-8');
  console.log(`[Manifest Generator] Release manifest dynamically compiled with SHA256 Hash (${manifestHash.substring(0, 12)}...) at: ${outputPath}`);
  return finalManifest;
}

if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith('generate-release-manifest.ts')) {
  generateReleaseManifest();
}
