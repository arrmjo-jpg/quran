import fs from 'fs';
import path from 'path';

/**
 * Generates src/core/permissions/index.ts from the server's PermissionCatalog.
 *
 * That file is the compile-time half of the authorization model: `PermissionKey`
 * is a union of every real permission name, so `<PermissionWrapper permission="…">`
 * with a name the server does not define is a type error rather than a control
 * that silently never renders.
 *
 * It was hand-maintained once, and it drifted completely — nineteen constants in
 * a `manage` vocabulary the catalogue had already removed, not one of which
 * matched a real permission. Hand-maintaining a mirror is the problem; this
 * script is the fix. Any permission added to the catalogue reaches the UI by
 * regenerating, never by typing it here.
 *
 *   npx tsx scripts/generate-permissions.ts          rewrites the file
 *   npx tsx scripts/generate-permissions.ts --check   fails if it is stale
 *
 * The catalogue is parsed rather than executed. Shelling out to PHP would tie a
 * frontend build step to a PHP runtime being installed and to a working
 * backend/vendor — neither is true in the frontend CI job, and a missing
 * interpreter would fail in a way that looks like a permissions bug.
 */

const CATALOG_PHP = path.join(
  process.cwd(),
  '..',
  'backend',
  'Modules',
  'Core',
  'Infrastructure',
  'Permissions',
  'PermissionCatalog.php'
);

const TARGET = path.join(process.cwd(), 'src', 'core', 'permissions', 'index.ts');

interface Group {
  resource: string;
  actions: string[];
}

/**
 * Reads `private const CATALOG = [ 'resource' => ['view', 'create'], … ];`
 *
 * Deliberately anchored to the CATALOG constant and not to "any PHP array":
 * the same file also defines ALLOWED_VERBS, and a looser pattern would happily
 * merge the two into a list of permissions that do not exist.
 */
function parseCatalog(php: string): Group[] {
  const start = php.indexOf('private const CATALOG = [');
  if (start === -1) {
    throw new Error('CATALOG constant not found in PermissionCatalog.php — has it been renamed?');
  }

  // Walk brackets from the opening one so a nested array cannot end the scan
  // early, and so a closing `];` inside a comment cannot either.
  const open = php.indexOf('[', start);
  let depth = 0;
  let end = -1;
  for (let i = open; i < php.length; i++) {
    if (php[i] === '[') depth++;
    else if (php[i] === ']') {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  if (end === -1) throw new Error('CATALOG constant is not closed.');

  const body = php.slice(open + 1, end);
  const groups: Group[] = [];
  const entry = /'([a-z0-9_]+)'\s*=>\s*\[([^\]]*)\]/g;

  let match: RegExpExecArray | null;
  while ((match = entry.exec(body)) !== null) {
    const actions = [...match[2].matchAll(/'([a-z0-9_]+)'/g)].map((m) => m[1]);
    if (actions.length > 0) groups.push({ resource: match[1], actions });
  }

  if (groups.length === 0) throw new Error('CATALOG parsed to nothing — the format has changed.');

  return groups;
}

function render(groups: Group[]): string {
  const lines: string[] = [];

  lines.push('/**');
  lines.push(' * Every permission the server defines — ADR-015 §4.3.');
  lines.push(' *');
  lines.push(' * GENERATED FILE. Do not edit by hand.');
  lines.push(' *');
  lines.push(' *     npm run generate:permissions');
  lines.push(' *');
  lines.push(" * Source of truth is the server's PermissionCatalog.php. This mirror exists");
  lines.push(' * so that a permission name the server does not define is a compile error');
  lines.push(' * rather than a control that silently never renders. It was hand-maintained');
  lines.push(' * once and drifted completely, which is why it is generated now.');
  lines.push(' *');
  lines.push(' * Kept in catalogue order and grouped by resource so a diff against the PHP');
  lines.push(' * file is readable.');
  lines.push(' */');
  lines.push('export const PERMISSIONS = [');

  for (const group of groups) {
    lines.push(`  // ${group.resource}`);
    for (const action of group.actions) {
      lines.push(`  '${group.resource}.${action}',`);
    }
  }

  lines.push('] as const;');
  lines.push('');
  lines.push('/** A permission name the server would recognise. */');
  lines.push('export type PermissionKey = (typeof PERMISSIONS)[number];');
  lines.push('');

  return lines.join('\n');
}

const groups = parseCatalog(fs.readFileSync(CATALOG_PHP, 'utf8'));
const generated = render(groups);
const total = groups.reduce((n, g) => n + g.actions.length, 0);

if (process.argv.includes('--check')) {
  const current = fs.existsSync(TARGET) ? fs.readFileSync(TARGET, 'utf8') : '';

  // Compared with line endings normalised: this repo checks out CRLF on
  // Windows and LF in CI, and a check that failed on that would be noise
  // rather than drift.
  const normalise = (s: string): string => s.replace(/\r\n/g, '\n');

  if (normalise(current) !== normalise(generated)) {
    console.error(
      '\nsrc/core/permissions/index.ts is out of date with PermissionCatalog.php.' +
        '\nRun: npm run generate:permissions\n'
    );
    process.exit(1);
  }

  console.log(`Permissions mirror is current (${total} permissions, ${groups.length} resources).`);
} else {
  fs.writeFileSync(TARGET, generated, 'utf8');
  console.log(`Wrote ${total} permissions across ${groups.length} resources to src/core/permissions/index.ts`);
}
