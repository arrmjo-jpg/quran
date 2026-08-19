import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, Clock, Pencil, Plus, RotateCcw, ShieldCheck, Trash2, XCircle } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { useAuth } from '@/core/auth/AuthContext';
import {
  useActivateUser,
  useDeactivateUser,
  useDeleteUser,
  useRestoreUser,
  useUsers,
} from '../hooks/useUsers';
import { UserRolesDialog } from '../components/UserRolesDialog';
import { UserFormDialog } from '../components/UserFormDialog';
import type { AdminUser, UserStatus } from '../types';

/**
 * Four states, one badge. Derived on the server (AdminUserResource) rather
 * than recombined here from is_active and is_deleted: pending and deactivated
 * are both is_active === false, and a screen that got that wrong would report
 * a colleague invited an hour ago as disabled.
 */
const STATUS_VARIANT: Record<UserStatus, 'success' | 'warning' | 'danger' | 'neutral'> = {
  active: 'success',
  pending_activation: 'warning',
  deactivated: 'danger',
  deleted: 'neutral',
};

const PER_PAGE = 20;

export default function UsersPage(): React.JSX.Element {
  const { t } = useTranslation('users');
  const { t: tc } = useTranslation('common');
  const { user: currentUser } = useAuth();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [rolesTarget, setRolesTarget] = useState<AdminUser | null>(null);
  const [deactivateTarget, setDeactivateTarget] = useState<AdminUser | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<AdminUser | null>(null);
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<AdminUser | null>(null);
  const [showDeleted, setShowDeleted] = useState(false);

  const { data, isLoading, isError, refetch } = useUsers({
    page,
    per_page: PER_PAGE,
    search: search || undefined,
    with_deleted: showDeleted || undefined,
  });

  const activateUser = useActivateUser();
  const deactivateUser = useDeactivateUser();
  const deleteUser = useDeleteUser();
  const restoreUser = useRestoreUser();

  const openCreate = (): void => {
    setEditing(null);
    setIsFormOpen(true);
  };

  const openEdit = (user: AdminUser): void => {
    setEditing(user);
    setIsFormOpen(true);
  };

  const columns: ColumnDef<AdminUser>[] = [
    {
      accessorKey: 'name',
      header: t('column_name'),
      cell: ({ row }) => (
        <div className="flex flex-col">
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.name}</span>
          <span className="text-xs text-slate-500 font-mono">{row.original.email}</span>
        </div>
      ),
    },
    {
      accessorKey: 'type',
      header: t('column_type'),
      cell: ({ row }) => (
        <Badge variant={row.original.type === 'admin' ? 'info' : 'neutral'}>
          {t(`type_${row.original.type}`)}
        </Badge>
      ),
    },
    {
      accessorKey: 'status',
      header: t('column_status'),
      cell: ({ row }) => (
        <div className="flex items-center gap-1.5">
          <Badge variant={STATUS_VARIANT[row.original.status]}>
            {t(`status_${row.original.status}`)}
          </Badge>
          {row.original.status === 'pending_activation' && (
            <Clock className="w-3.5 h-3.5 text-amber-600" aria-label={t('status_pending_activation')} />
          )}
        </div>
      ),
    },
    {
      accessorKey: 'roles',
      header: t('column_roles'),
      cell: ({ row }) =>
        row.original.roles.length === 0 ? (
          <span className="text-xs text-slate-400">{t('no_roles')}</span>
        ) : (
          <div className="flex flex-wrap gap-1">
            {row.original.roles.map((role) => (
              <Badge key={role} variant="neutral">
                <span className="font-mono text-[11px]">{role}</span>
              </Badge>
            ))}
          </div>
        ),
    },
    {
      id: 'actions',
      header: t('column_actions'),
      cell: ({ row }) => {
        const user = row.original;
        /**
         * The one refusal this screen can know on its own. PE-5 — the last
         * active holder of a system role — needs a count across accounts, so
         * that one is left to the server's 409, which names its reason.
         */
        const isSelf = currentUser?.id === user.id;

        const isDeleted = user.status === 'deleted';

        // A deleted account offers exactly one action: bring it back. Showing
        // roles or activation for a row that is gone would be offering work on
        // something the server will refuse to find.
        if (isDeleted) {
          return (
            <PermissionWrapper permission="users.restore">
              <Button
                size="sm"
                variant="outline"
                isLoading={restoreUser.isPending && restoreUser.variables === user.id}
                onClick={() => restoreUser.mutate(user.id)}
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>{t('action_restore')}</span>
              </Button>
            </PermissionWrapper>
          );
        }

        return (
          <div className="flex flex-wrap items-center gap-2">
            <PermissionWrapper permission="users.update">
              <Button size="sm" variant="outline" onClick={() => openEdit(user)}>
                <Pencil className="w-3.5 h-3.5" />
                <span>{tc('edit')}</span>
              </Button>
            </PermissionWrapper>

            <PermissionWrapper permission="users.assign_roles">
              <Button size="sm" variant="outline" onClick={() => setRolesTarget(user)}>
                <ShieldCheck className="w-3.5 h-3.5" />
                <span>{t('action_roles')}</span>
              </Button>
            </PermissionWrapper>

            {user.is_active ? (
              <PermissionWrapper permission="users.deactivate">
                <Button
                  size="sm"
                  variant="danger"
                  disabled={isSelf}
                  title={isSelf ? t('self_deactivate_hint') : undefined}
                  onClick={() => setDeactivateTarget(user)}
                >
                  <XCircle className="w-3.5 h-3.5" />
                  <span>{t('action_deactivate')}</span>
                </Button>
              </PermissionWrapper>
            ) : (
              <PermissionWrapper permission="users.activate">
                <Button
                  size="sm"
                  variant="outline"
                  isLoading={activateUser.isPending && activateUser.variables === user.id}
                  onClick={() => activateUser.mutate(user.id)}
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  <span>{t('action_activate')}</span>
                </Button>
              </PermissionWrapper>
            )}

            <PermissionWrapper permission="users.delete">
              <Button
                size="sm"
                variant="danger"
                disabled={isSelf}
                title={isSelf ? t('self_delete_hint') : undefined}
                onClick={() => setDeleteTarget(user)}
              >
                <Trash2 className="w-3.5 h-3.5" />
                <span>{tc('delete')}</span>
              </Button>
            </PermissionWrapper>
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
        <div className="flex items-center gap-3">
          <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
            <input
              type="checkbox"
              checked={showDeleted}
              onChange={(e) => {
                setShowDeleted(e.target.checked);
                setPage(1);
              }}
              className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            />
            <span>{t('show_deleted')}</span>
          </label>

          <PermissionWrapper permission="users.create">
            <Button onClick={openCreate}>
              <Plus className="w-4 h-4" />
              <span>{t('action_create')}</span>
            </Button>
          </PermissionWrapper>
        </div>
      }
    >
      <DataTable
        columns={columns}
        data={data?.users ?? []}
        loading={isLoading}
        searchable
        onSearchChange={(query) => {
          setSearch(query);
          // A filtered result set is a different list; staying on page four
          // of the old one would show an empty table.
          setPage(1);
        }}
        pagination={{ pageIndex: page - 1, pageSize: PER_PAGE, total: data?.total ?? 0 }}
        onPageChange={(index) => setPage(index + 1)}
        onRefresh={() => void refetch()}
        emptyMessage={t('empty')}
      />

      <UserFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} editing={editing} />

      <UserRolesDialog user={rolesTarget} onClose={() => setRolesTarget(null)} />

      <ConfirmDialog
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        title={t('delete_title')}
        description={t('delete_message', { name: deleteTarget?.name ?? '' })}
        confirmLabel={tc('delete')}
        isLoading={deleteUser.isPending}
        onConfirm={() => {
          if (deleteTarget === null) return;
          deleteUser.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) });
        }}
      />

      <ConfirmDialog
        isOpen={deactivateTarget !== null}
        onClose={() => setDeactivateTarget(null)}
        title={t('deactivate_title')}
        description={t('deactivate_message', { name: deactivateTarget?.name ?? '' })}
        confirmLabel={t('action_deactivate')}
        isLoading={deactivateUser.isPending}
        onConfirm={() => {
          if (deactivateTarget === null) return;
          deactivateUser.mutate(deactivateTarget.id, {
            onSuccess: () => setDeactivateTarget(null),
          });
        }}
      />
    </PageLayout>
  );
}
