import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import Badge from '@/ui/Badge';
import { Lock, Minus, Plus } from 'lucide-react';
import type { PermissionKey } from '@/core/permissions';
import type { Role } from '../types';
import { usePermissionCatalog, useSyncRolePermissions } from '../hooks/useRoles';
import { PermissionMatrix } from './PermissionMatrix';

export interface RolePermissionsDialogProps {
  role: Role | null;
  onClose: () => void;
}

/**
 * Edits what a role can do.
 *
 * The endpoint takes the intended FINAL set rather than a delta, because two
 * clients sending add and remove separately could interleave and leave a set
 * neither of them intended. The delta shown below is therefore for the reader,
 * not for the request — it is derived here and never sent.
 *
 * A system role opens read-only: the server refuses every edit to it, so
 * offering the checkboxes would be offering an action that cannot succeed.
 */
export function RolePermissionsDialog({
  role,
  onClose,
}: RolePermissionsDialogProps): React.JSX.Element {
  const { t } = useTranslation('roles');
  const { t: tc } = useTranslation('common');
  const [selected, setSelected] = useState<PermissionKey[]>([]);

  const catalogue = usePermissionCatalog();
  const syncPermissions = useSyncRolePermissions();

  useEffect(() => {
    if (role === null) return;
    setSelected(role.permissions);
  }, [role]);

  const diff = useMemo(() => {
    if (role === null) return { added: [], removed: [] };
    const before = new Set(role.permissions);
    const after = new Set(selected);

    return {
      added: selected.filter((p) => !before.has(p)),
      removed: role.permissions.filter((p) => !after.has(p)),
    };
  }, [role, selected]);

  const hasChanges = diff.added.length > 0 || diff.removed.length > 0;

  const save = (): void => {
    if (role === null) return;
    syncPermissions.mutate({ id: role.id, permissions: selected }, { onSuccess: onClose });
  };

  return (
    <Dialog
      isOpen={role !== null}
      onClose={onClose}
      title={role === null ? '' : t('permissions_title', { name: role.name })}
      className="max-w-3xl"
    >
      {role !== null && (
        <div className="flex flex-col gap-4">
          {role.is_system && (
            <div className="flex items-start gap-2 rounded-lg border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/40 px-3 py-2">
              <Lock className="w-4 h-4 text-amber-600 mt-0.5 shrink-0" />
              <p className="text-xs text-amber-800 dark:text-amber-200">{t('system_role_notice')}</p>
            </div>
          )}

          {catalogue.isLoading && <p className="text-sm text-slate-500">{tc('loading')}</p>}

          {catalogue.data !== undefined && (
            <PermissionMatrix
              catalogue={catalogue.data}
              selected={selected}
              onChange={setSelected}
              disabled={role.is_system}
            />
          )}

          {hasChanges && (
            <div className="flex flex-col gap-2 rounded-lg border border-slate-200 dark:border-slate-800 p-3">
              <p className="text-xs font-semibold text-slate-700 dark:text-slate-200">
                {t('pending_changes')}
              </p>
              <div className="flex flex-wrap gap-1.5">
                {diff.added.map((permission) => (
                  <Badge key={`add-${permission}`} variant="success">
                    <Plus className="w-3 h-3" />
                    <span className="font-mono text-[11px]">{permission}</span>
                  </Badge>
                ))}
                {diff.removed.map((permission) => (
                  <Badge key={`remove-${permission}`} variant="danger">
                    <Minus className="w-3 h-3" />
                    <span className="font-mono text-[11px]">{permission}</span>
                  </Badge>
                ))}
              </div>
            </div>
          )}

          <div className="flex items-center justify-end gap-2">
            <Button type="button" variant="outline" onClick={onClose}>
              {tc('cancel')}
            </Button>
            <Button
              type="button"
              onClick={save}
              disabled={role.is_system || !hasChanges}
              isLoading={syncPermissions.isPending}
            >
              {tc('save')}
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
}
