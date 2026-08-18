import React from 'react';
import { useAuth } from '@/core/auth/AuthContext';
import type { PermissionKey } from '@/core/permissions';

export interface PermissionWrapperProps {
  /** The server-side permission this element requires. */
  permission: PermissionKey;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

/**
 * Shows its children only if the server would allow the action.
 *
 * The `permission` prop existed before this and was never read — the
 * component destructured `role` and ignored everything else, so the panel
 * had never performed a permission check at all. Every call site passed
 * `role="admin"`, which was true for every administrator, so every button
 * rendered for everyone.
 *
 * That was harmless while `type='admin'` really did grant everything. Now
 * the server refuses on permissions, and a button rendered for someone who
 * cannot use it is worse than a missing one: it fails at the point of
 * action, after the operator has decided to act.
 *
 * `role` is deliberately gone rather than deprecated. Leaving it would
 * keep `user.roles.includes(...)` available as an authorization decision,
 * and the whole point is that roles describe the account while
 * permissions decide what it may do.
 *
 * NOT A SECURITY BOUNDARY. This hides UI; the server decides. A permission
 * omitted here is a usability bug, never an authorization hole.
 */
export function PermissionWrapper({
  permission,
  children,
  fallback = null,
}: PermissionWrapperProps): React.JSX.Element {
  const { user } = useAuth();

  if (!user?.permissions?.includes(permission)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}
