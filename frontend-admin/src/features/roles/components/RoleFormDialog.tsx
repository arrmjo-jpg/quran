import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import { Input } from '@/ui/input/Input';
import type { PermissionKey } from '@/core/permissions';
import type { Role } from '../types';
import { useCreateRole, useRenameRole, usePermissionCatalog } from '../hooks/useRoles';
import { PermissionMatrix } from './PermissionMatrix';

export interface RoleFormDialogProps {
  isOpen: boolean;
  onClose: () => void;
  /** Null creates; a role renames it. */
  editing: Role | null;
}

/**
 * Creates a role, or renames one.
 *
 * Renaming deliberately does NOT edit permissions, even though both are a
 * PATCH on the same resource: the server separates roles.update from
 * roles.grant_permissions, and an operator who may only rename would find the
 * save rejected by a form that had offered them the checkboxes. The permission
 * set is edited in its own dialog, behind its own permission.
 *
 * On create the two travel together because POST /admin/roles takes both and
 * a role created empty and then filled is two requests that can half-fail.
 */
export function RoleFormDialog({ isOpen, onClose, editing }: RoleFormDialogProps): React.JSX.Element {
  const { t } = useTranslation('roles');
  const { t: tc } = useTranslation('common');
  const [name, setName] = useState('');
  const [permissions, setPermissions] = useState<PermissionKey[]>([]);

  const catalogue = usePermissionCatalog();
  const createRole = useCreateRole();
  const renameRole = useRenameRole();

  const isEditing = editing !== null;

  useEffect(() => {
    if (!isOpen) return;
    setName(editing?.name ?? '');
    setPermissions([]);
  }, [isOpen, editing]);

  const submit = (e: React.FormEvent): void => {
    e.preventDefault();

    if (isEditing) {
      renameRole.mutate({ id: editing.id, name }, { onSuccess: onClose });
      return;
    }

    createRole.mutate({ name, permissions }, { onSuccess: onClose });
  };

  const isPending = createRole.isPending || renameRole.isPending;

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? t('rename_title') : t('create_title')}
      className="max-w-3xl"
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_name')}
          </label>
          <Input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="content_editor"
            required
          />
          {/* The server requires snake_case and is the authority on it; this
              only says so, rather than re-implementing the rule in a regex
              that could drift from the domain's. */}
          <p className="text-xs text-slate-500">{t('field_name_hint')}</p>
        </div>

        {!isEditing && (
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
              {t('field_permissions')}
            </label>
            {catalogue.isLoading && <p className="text-sm text-slate-500">{tc('loading')}</p>}
            {catalogue.data !== undefined && (
              <PermissionMatrix
                catalogue={catalogue.data}
                selected={permissions}
                onChange={setPermissions}
              />
            )}
          </div>
        )}

        <div className="flex items-center justify-end gap-2 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>
            {tc('cancel')}
          </Button>
          <Button type="submit" isLoading={isPending}>
            {isEditing ? tc('save') : tc('create')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
