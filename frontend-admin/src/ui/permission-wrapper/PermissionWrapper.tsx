import React from 'react';
import { useAuth } from '@/core/auth/AuthContext';
import type { PermissionKey } from '@/core/permissions';

export interface PermissionWrapperProps {
  permission?: PermissionKey;
  role?: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function PermissionWrapper({
  role,
  children,
  fallback = null,
}: PermissionWrapperProps): React.JSX.Element | null {
  const { user } = useAuth();

  if (!user) return <>{fallback}</>;

  // Check role
  if (role && !user.roles.includes(role) && !user.roles.includes('admin')) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}
