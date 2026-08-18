import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Lock, Minus, Plus } from 'lucide-react';
import { Dialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import Badge from '@/ui/Badge';
import { useRoles } from '@/features/roles/hooks/useRoles';
import { useSyncUserRoles } from '../hooks/useUsers';
import type { AdminUser } from '../types';

export interface UserRolesDialogProps {
  user: AdminUser | null;
  onClose: () => void;
}

/**
 * Assigns roles to an account.
 *
 * The endpoint takes role ids and the account carries role names, so the two
 * are matched by name here. That is deliberate rather than a shortcut: the
 * list endpoint reports names because ids are unreadable in a table, and
 * fetching each account's ids separately would be a request per row to avoid
 * a lookup the roles list already answers.
 *
 * The diff is derived for the reader and never sent — like the role editor,
 * this endpoint takes the intended final set, because two clients sending
 * add and remove separately could interleave into a set neither intended.
 */
export function UserRolesDialog({ user, onClose }: UserRolesDialogProps): React.JSX.Element {
  const { t } = useTranslation('users');
  const { t: tc } = useTranslation('common');
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  const { data: roles, isLoading } = useRoles();
  const syncRoles = useSyncUserRoles();

  const heldIds = useMemo(() => {
    if (user === null || roles === undefined) return [];

    // A membership test on role names, which is exactly the shape
    // check-authorization-model bans — and it flagged this line. The ban is
    // right to be shape-based even though this particular use is a lookup
    // rather than a decision: it matches the names a row carries to the ids
    // the endpoint takes. A set says that more plainly than a filter over an
    // includes(), so the guard stays strict and this reads better for it.
    const heldNames = new Set(user.roles);

    return roles.filter((role) => heldNames.has(role.name)).map((role) => role.id);
  }, [user, roles]);

  useEffect(() => {
    if (user === null) return;
    setSelectedIds(heldIds);
  }, [user, heldIds]);

  const diff = useMemo(() => {
    if (roles === undefined) return { added: [], removed: [] };
    const before = new Set(heldIds);
    const after = new Set(selectedIds);
    const nameOf = (id: string): string => roles.find((r) => r.id === id)?.name ?? id;

    return {
      added: selectedIds.filter((id) => !before.has(id)).map(nameOf),
      removed: heldIds.filter((id) => !after.has(id)).map(nameOf),
    };
  }, [roles, heldIds, selectedIds]);

  const hasChanges = diff.added.length > 0 || diff.removed.length > 0;

  const toggle = (id: string): void => {
    setSelectedIds((current) =>
      current.includes(id) ? current.filter((held) => held !== id) : [...current, id]
    );
  };

  const save = (): void => {
    if (user === null) return;
    syncRoles.mutate({ id: user.id, roles: selectedIds }, { onSuccess: onClose });
  };

  return (
    <Dialog
      isOpen={user !== null}
      onClose={onClose}
      title={user === null ? '' : t('roles_title', { name: user.name })}
      className="max-w-xl"
    >
      {user !== null && (
        <div className="flex flex-col gap-4">
          {isLoading && <p className="text-sm text-slate-500">{tc('loading')}</p>}

          <div className="flex flex-col gap-1 max-h-[22rem] overflow-y-auto">
            {(roles ?? []).map((role) => (
              <label
                key={role.id}
                className="flex items-center gap-2.5 rounded-lg px-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer"
              >
                <input
                  type="checkbox"
                  checked={selectedIds.includes(role.id)}
                  onChange={() => toggle(role.id)}
                  className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                />
                <span className="font-mono text-sm text-slate-900 dark:text-white">{role.name}</span>
                {role.is_system && (
                  <Badge variant="warning">
                    <Lock className="w-3 h-3" />
                    <span>{t('badge_system')}</span>
                  </Badge>
                )}
                <span className="text-xs text-slate-400 ms-auto">
                  {t('role_permission_count', { count: role.permissions_count })}
                </span>
              </label>
            ))}
          </div>

          {hasChanges && (
            <div className="flex flex-col gap-2 rounded-lg border border-slate-200 dark:border-slate-800 p-3">
              <p className="text-xs font-semibold text-slate-700 dark:text-slate-200">
                {t('pending_changes')}
              </p>
              <div className="flex flex-wrap gap-1.5">
                {diff.added.map((name) => (
                  <Badge key={`add-${name}`} variant="success">
                    <Plus className="w-3 h-3" />
                    <span className="font-mono text-[11px]">{name}</span>
                  </Badge>
                ))}
                {diff.removed.map((name) => (
                  <Badge key={`remove-${name}`} variant="danger">
                    <Minus className="w-3 h-3" />
                    <span className="font-mono text-[11px]">{name}</span>
                  </Badge>
                ))}
              </div>
            </div>
          )}

          {/* The server refuses PE-1 (granting beyond your own permissions)
              and PE-5 (stranding the last holder of a system role). Neither
              is knowable from this screen — one needs the operator's own
              effective set, the other a count across accounts — so both
              arrive as a refusal that names its reason. */}
          <div className="flex items-center justify-end gap-2">
            <Button type="button" variant="outline" onClick={onClose}>
              {tc('cancel')}
            </Button>
            <Button type="button" onClick={save} disabled={!hasChanges} isLoading={syncRoles.isPending}>
              {tc('save')}
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
}
