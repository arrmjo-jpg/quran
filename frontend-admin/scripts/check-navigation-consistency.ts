import fs from 'fs';
import path from 'path';

/**
 * Guards that every navigation surface offers only real, permitted routes —
 * ADR-019 D7.
 *
 * The command palette used to filter by a text match alone. Its comment said
 * "the operator searches what they can see", and nothing implemented that:
 * every destination was offered to everyone, and the route guard refused on
 * arrival — after the operator had decided to act. The sidebar had been
 * filtering correctly the whole time, through `requiredPermissionFor`, so the
 * two surfaces disagreed about who may see what.
 *
 * A script rather than a test because this project has no frontend test
 * runner, and not a lint rule because ESLint is not installed — the `lint`
 * npm script names a binary that is not a dependency. Both are worth having
 * and neither is in this epic's scope, so this is wired into gate:functional,
 * which is what CI actually runs. The same reasoning is recorded on
 * check-authorization-model.ts.
 *
 * WHAT THIS IS NOT: a security boundary. The server refuses unauthorized
 * requests whatever any of this renders. It keeps what is OFFERED matching
 * what is PERMITTED, so the two cannot drift apart again silently.
 */

const SRC = path.join(process.cwd(), 'src');

function read(relative: string): string {
  return fs.readFileSync(path.join(SRC, relative), 'utf8');
}

const failures: string[] = [];

/* ── The routes that actually exist ──────────────────────────────────────── */

const routerSource = read(path.join('app', 'router.tsx'));

/**
 * `page('seasons', <SeasonsPage />)` registers `/seasons`. The index route is
 * `page('', ...)`, which is `/`.
 */
const registered = new Set<string>(
  [...routerSource.matchAll(/page\('([^']*)'/g)].map((m) => `/${m[1]}`.replace(/^\/$/, '/'))
);
registered.add('/');
registered.add('/login');

/* ── Every destination a surface offers must be one of them ──────────────── */

const surfaces: Array<{ file: string; label: string }> = [
  { file: path.join('ui', 'command-palette', 'CommandPalette.tsx'), label: 'command palette' },
  { file: path.join('layouts', 'Sidebar.tsx'), label: 'sidebar' },
];

for (const surface of surfaces) {
  const source = read(surface.file);
  const paths = [...source.matchAll(/path: '([^']+)'/g)].map((m) => m[1]);

  if (paths.length === 0) {
    failures.push(`${surface.label}: no destinations found — has the shape of this file changed?`);
    continue;
  }

  for (const destination of paths) {
    if (!registered.has(destination)) {
      failures.push(
        `${surface.label} offers "${destination}", which no page() call registers. ` +
          'A destination that does not resolve is worse than a missing one: it looks available and fails on arrival.'
      );
    }
  }
}

/* ── The palette must consult the permission map, not just match text ────── */

const paletteSource = read(path.join('ui', 'command-palette', 'CommandPalette.tsx'));

if (!paletteSource.includes('requiredPermissionFor')) {
  failures.push(
    'The command palette no longer consults requiredPermissionFor(). It was filtering by ' +
      'label text alone until ADR-019 D7, which offered every destination to everyone. ' +
      'The sidebar filters through the same function; if this one stops, the two disagree again.'
  );
}

/* ── Report ──────────────────────────────────────────────────────────────── */

if (failures.length > 0) {
  console.error('\nNavigation consistency check failed:\n');
  for (const failure of failures) {
    console.error(`  - ${failure}`);
  }
  console.error('');
  process.exit(1);
}

console.log(
  `Navigation consistency: every destination resolves, and the palette filters by permission ` +
    `(${registered.size} routes registered).`
);
