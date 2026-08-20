import type { PermissionKey } from '@/core/permissions';

/**
 * The permission each destination requires — ADR-015 §7.3.
 *
 * The sidebar and the router both read this, and neither names a permission
 * of its own. They used to disagree by construction: the sidebar carried a
 * permission for two of its seventeen links and the router carried none, so
 * `ProtectedRoute` checked only that somebody was signed in. A link could be
 * hidden from the sidebar while the page behind it stayed reachable by
 * typing the URL, and the page would then render its empty state — telling
 * the operator there is no data rather than that they may not see it.
 *
 * Values are `PermissionKey`, so a permission the server does not define is
 * a compile error rather than a gate that silently never opens.
 *
 * A path absent from this map is deliberately open to any signed-in
 * administrator. That is three destinations and each has a reason:
 *
 *   /          the landing page. Gating it would refuse an operator at the
 *              moment they sign in, before they can reach anything they DO
 *              hold — a judge would meet a wall instead of a panel.
 *   /sessions  shows only the viewer's own sessions.
 *   /about     static build and environment facts, no data behind it.
 *
 * NOT A SECURITY BOUNDARY, for the same reason PermissionWrapper is not:
 * this decides what the panel offers, the server decides what it serves. A
 * path missing from this map is a usability bug, never an authorization
 * hole.
 */
export const ROUTE_PERMISSIONS: Readonly<Record<string, PermissionKey>> = {
  '/seasons': 'seasons.view',
  '/contestants': 'contestants.view',
  '/judges': 'judges.view',
  '/applications': 'applications.view',
  '/evaluations': 'evaluations.view',
  '/media': 'media.view',
  '/videos': 'videos.view',
  '/streaming': 'streaming.view',
  '/sponsors': 'sponsors.view',
  '/content': 'content.view',
  '/reports': 'reports.view',
  '/notifications': 'notifications.view',
  '/countries': 'countries.view',
  '/centers': 'centers.view',
  '/users': 'users.view',
  '/roles': 'roles.view',
  '/search': 'search.view',
  '/audit-logs': 'audit.view',
};

/**
 * The permission `path` requires, or undefined if it is open.
 *
 * Paths are matched exactly. Every route in this panel is a top-level
 * screen, so there is nothing to prefix-match yet — and guessing at nesting
 * before it exists would mean `/users` quietly gating a future
 * `/users-something` that has nothing to do with it.
 */
export function requiredPermissionFor(path: string): PermissionKey | undefined {
  return ROUTE_PERMISSIONS[path];
}
