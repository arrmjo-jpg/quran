import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { useDeleteRole, useRoles } from '../hooks/useRoles';
import { RoleFormDialog } from '../components/RoleFormDialog';
import { RolePermissionsDialog } from '../components/RolePermissionsDialog';
import type { Role } from '../types';

export default function RolesPage(): React.JSX.Element {
  const { t } = useTranslation('roles');
  const { t: tc } = useTranslation('common');

  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Role | null>(null);
  const [permissionsTarget, setPermissionsTarget] = useState<Role | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Role | null>(null);

  const { data: roles, isLoading, isError, refetch } = useRoles();
  const deleteRole = useDeleteRole();

  const openCreate = (): void => {
    setEditing(null);
    setIsFormOpen(true);
  };

  const openRename = (role: Role): void => {
    setEditing(role);
    setIsFormOpen(true);
  };

  const columns: ColumnDef<Role>[] = [
    {
      accessorKey: 'name',
      header: t('column_name'),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <span className="font-mono text-sm text-slate-900 dark:text-white">{row.original.name}</span>
          {row.original.is_system && (
            <Badge variant="warning">
              <Lock className="w-3 h-3" />
              <span>{t('badge_system')}</span>
            </Badge>
          )}
        </div>
      ),
    },
    {
      accessorKey: 'permissions_count',
      header: t('column_permissions'),
      cell: ({ row }) => (
        <span className="text-slate-600 dark:text-slate-300">
          {t('permission_count', { count: row.original.permissions_count })}
        </span>
      ),
    },
    {
      id: 'actions',
      header: t('column_actions'),
      cell: ({ row }) => {
        const role = row.original;

        return (
          <div className="flex flex-wrap items-center gap-2">
            {/* Two independent questions per button: is_system asks whether
                the server would accept the edit at all, PermissionWrapper
                asks whether this operator may make it. */}
            <PermissionWrapper permission="roles.grant_permissions">
              <Button size="sm" variant="outline" onClick={() => setPermissionsTarget(role)}>
                <ShieldCheck className="w-3.5 h-3.5" />
                <span>{t('action_permissions')}</span>
              </Button>
            </PermissionWrapper>

            {!role.is_system && (
              <PermissionWrapper permission="roles.update">
                <Button size="sm" variant="outline" onClick={() => openRename(role)}>
                  <Pencil className="w-3.5 h-3.5" />
                  <span>{t('action_rename')}</span>
                </Button>
              </PermissionWrapper>
            )}

            {!role.is_system && (
              <PermissionWrapper permission="roles.delete">
                <Button size="sm" variant="danger" onClick={() => setDeleteTarget(role)}>
                  <Trash2 className="w-3.5 h-3.5" />
                  <span>{tc('delete')}</span>
                </Button>
              </PermissionWrapper>
            )}
          </div>
        );
      },
    },
  ];

  if (isError) {
    return (
      <PageLayout title={t('page_title')} subtitle={t('page_subtitle')}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  return (
    <PageLayout
      title={t('page_title')}
      subtitle={t('page_subtitle')}
      actions={
        <PermissionWrapper permission="roles.create">
          <Button onClick={openCreate}>
            <Plus className="w-4 h-4" />
            <span>{t('action_create')}</span>
          </Button>
        </PermissionWrapper>
      }
    >
      <DataTable columns={columns} data={roles ?? []} loading={isLoading} />

      <RoleFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} editing={editing} />

      <RolePermissionsDialog role={permissionsTarget} onClose={() => setPermissionsTarget(null)} />

      <ConfirmDialog
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        title={t('delete_title')}
        description={t('delete_message', { name: deleteTarget?.name ?? '' })}
        confirmLabel={tc('delete')}
        isLoading={deleteRole.isPending}
        onConfirm={() => {
          if (deleteTarget === null) return;
          deleteRole.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) });
        }}
      />
    </PageLayout>
  );
}
