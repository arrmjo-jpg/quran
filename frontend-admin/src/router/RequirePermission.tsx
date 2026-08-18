import React from 'react';
import { useTranslation } from 'react-i18next';
import { ShieldOff } from 'lucide-react';
import { useAuth } from '@/core/auth/AuthContext';
import { requiredPermissionFor } from '@/core/navigation/routeAccess';

/**
 * Refuses a destination the server would refuse, and says so.
 *
 * The point is the wording rather than the blocking. Without this the page
 * still rendered, issued its request, took the 403 and fell through to its
 * empty state — so `/users` told an operator "لا حسابات مطابقة" and
 * `/roles` told them "لا توجد بيانات متاحة". Both are false: there are
 * accounts and there are roles, and the reason they cannot see them is not
 * that none exist. An operator reading that files a bug about missing data,
 * or worse, believes it.
 *
 * Not a redirect. Sending someone to the dashboard would lose the fact that
 * they asked for something specific and was refused, which is the one thing
 * worth telling them.
 */
export default function RequirePermission({
  path,
  children,
}: {
  path: string;
  children: React.ReactNode;
}): React.JSX.Element {
  const { user } = useAuth();
  const permission = requiredPermissionFor(path);

  if (permission === undefined || user?.permissions?.includes(permission)) {
    return <>{children}</>;
  }

  return <AccessDenied permission={permission} />;
}

/**
 * Names the missing permission on purpose.
 *
 * This is an administrative panel: the person reading has an operator who
 * can grant it, and "you need users.view" turns a dead end into a request
 * they can actually make. It discloses nothing — the catalogue is a fixed
 * list the server publishes to every signed-in account.
 */
function AccessDenied({ permission }: { permission: string }): React.JSX.Element {
  const { t } = useTranslation('common');

  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="w-14 h-14 flex items-center justify-center bg-slate-100 dark:bg-slate-800 mb-5">
        <ShieldOff className="w-7 h-7 text-slate-400" />
      </div>

      <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
        {t('access_denied_title')}
      </h2>

      <p className="text-sm text-slate-500 dark:text-slate-400 max-w-md mb-4">
        {t('access_denied_body')}
      </p>

      <code className="text-xs font-mono px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
        {t('access_denied_permission', { permission })}
      </code>
    </div>
  );
}
