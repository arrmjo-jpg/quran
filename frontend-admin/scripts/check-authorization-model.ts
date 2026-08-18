import fs from 'fs';
import path from 'path';

/**
 * Guards the authorization model the admin panel decides by — ADR-015.
 *
 * The panel used to answer "may this operator do X?" by looking at the
 * account: `type === 'admin'`, or `user.roles.includes('admin')`. Both were
 * true for every administrator, so every control rendered for everyone.
 * Epic 6.3d replaced that with the effective permission set the server
 * itself resolves, and this script exists because that replacement is a
 * decision rather than a syntax: nothing about the language stops someone
 * reintroducing a roles check next to a working permission check.
 *
 * It is a script and not a test because this project has no frontend test
 * runner, and not a lint rule because ESLint is not installed here — the
 * `lint` npm script names a binary that is not a dependency. Both are worth
 * having and neither is in this epic's scope, so the check is wired into
 * gate:functional, which is what CI actually runs against the frontend.
 *
 * WHAT THIS IS NOT: a security boundary. The server refuses unauthorized
 * requests whatever the UI renders. This protects the model's consistency,
 * so that what an operator is offered keeps matching what they may do.
 */

const SRC = path.join(process.cwd(), 'src');

/**
 * The component that implements the ban is the one file allowed to
 * describe it — its docblock quotes both patterns to explain why they are
 * gone. Its own props are checked separately below, so exempting the file
 * cannot smuggle the `role` prop back in.
 */
const EXEMPT = path.join('ui', 'permission-wrapper', 'PermissionWrapper.tsx');

interface Ban {
  pattern: RegExp;
  why: string;
}

const BANS: Ban[] = [
  {
    // Not an ARIA role: `admin` is not in the ARIA role vocabulary, so this
    // matches the old authorization prop and nothing legitimate.
    pattern: /role=["']admin["']/,
    why: 'authorization by role name — use <PermissionWrapper permission="..."> instead',
  },
  {
    // The membership tests specifically. Reading or displaying `roles` is
    // fine; asking whether it contains something is a decision.
    pattern: /\.roles\s*\.\s*(includes|some|indexOf)\s*\(/,
    why: 'a UI decision made from roles — decide with user.permissions instead',
  },
];

function walk(dir: string, out: string[] = []): string[] {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else if (/\.tsx?$/.test(entry.name)) out.push(full);
  }
  return out;
}

const failures: string[] = [];
let exemptFileWasScanned = false;
let exemptFileStillNeedsIt = false;

for (const file of walk(SRC)) {
  const relative = path.relative(SRC, file);
  const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
  const isExempt = relative === EXEMPT;

  if (isExempt) exemptFileWasScanned = true;

  lines.forEach((line, i) => {
    for (const ban of BANS) {
      if (!ban.pattern.test(line)) continue;
      if (isExempt) {
        exemptFileStillNeedsIt = true;
        continue;
      }
      failures.push(`${relative}:${i + 1}  ${ban.why}\n    ${line.trim()}`);
    }
  });
}

// The exemption is a liability once it stops being needed, so it is checked
// in both directions: the file must exist, and it must still contain what it
// was exempted for. A stale exemption silently widens the hole it was.
if (!exemptFileWasScanned) {
  failures.push(`The exempt file ${EXEMPT} no longer exists. Remove the exemption from this script.`);
} else if (!exemptFileStillNeedsIt) {
  failures.push(`${EXEMPT} no longer contains the patterns it is exempted for. Remove the exemption from this script.`);
}

// The prop itself, not just its use: re-adding `role` to PermissionWrapper
// would make every call site legal again and no other check would notice.
const wrapper = fs.readFileSync(path.join(SRC, EXEMPT), 'utf8');
const props = wrapper.match(/interface PermissionWrapperProps\s*\{[^}]*\}/);
if (props === null) {
  failures.push(`${EXEMPT}: PermissionWrapperProps not found — this check can no longer verify anything.`);
} else if (/^\s*role\s*[?:]/m.test(props[0])) {
  failures.push(`${EXEMPT}: PermissionWrapperProps declares a \`role\` prop again. Authorization is decided by permission.`);
}

if (failures.length > 0) {
  console.error('\nAuthorization model violations:\n');
  for (const failure of failures) console.error(`  ${failure}\n`);
  console.error(`${failures.length} violation(s). See ADR-015 §4.6.\n`);
  process.exit(1);
}

console.log('Authorization model: the UI decides by permission only.');
