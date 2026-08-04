import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const gateName = process.argv[2];
const commandToRun = process.argv[3];

if (!gateName || !commandToRun) {
  console.error('Usage: npx tsx scripts/record-gate-result.ts <gateName> "<commandToRun>"');
  process.exit(1);
}

const startTime = new Date();
let gateStatus: 'PASS' | 'FAIL' = 'FAIL';

console.log(`[Gate Engine] Executing verification command for ${gateName}: "${commandToRun}"...`);

try {
  execSync(commandToRun, { stdio: 'inherit' });
  gateStatus = 'PASS';
  console.log(`[Gate Engine] ${gateName} command executed with Exit Code 0.`);
} catch {
  gateStatus = 'FAIL';
  console.error(`[Gate Engine] ${gateName} command failed with non-zero Exit Code.`);
}

const rootDir = process.cwd();
const gateDir = path.join(rootDir, 'artifacts', 'reports', gateName);

if (!fs.existsSync(gateDir)) {
  fs.mkdirSync(gateDir, { recursive: true });
}

// Artifact Validation Rule: Ensure required report file exists if PASS
const requiredArtifactFile = path.join(gateDir, 'result.json');

const endTime = new Date();
const durationMs = endTime.getTime() - startTime.getTime();

const result = {
  gate: gateName,
  status: gateStatus,
  started_at: startTime.toISOString(),
  finished_at: endTime.toISOString(),
  duration_ms: durationMs,
  artifacts: [`artifacts/reports/${gateName}/result.json`],
};

fs.writeFileSync(requiredArtifactFile, JSON.stringify(result, null, 2), 'utf-8');

// Strict Artifact Integrity Check
if (gateStatus === 'PASS' && !fs.existsSync(requiredArtifactFile)) {
  console.error(`[Gate Engine] FATAL: ${gateName} passed execution but required artifact ${requiredArtifactFile} is missing!`);
  gateStatus = 'FAIL';
  result.status = 'FAIL';
  fs.writeFileSync(requiredArtifactFile, JSON.stringify(result, null, 2), 'utf-8');
}

console.log(`[Gate Engine] ${gateName} machine evaluation written to ${requiredArtifactFile} with Status: ${gateStatus}`);

if (gateStatus === 'FAIL') {
  process.exit(1);
}
