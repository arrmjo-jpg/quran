import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, ShieldCheck, XCircle } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { useAuth } from '@/core/auth/AuthContext';
import { useActivateUser, useDeactivateUser, useUsers } from '../hooks/useUsers';
import { UserRolesDialog } from '../components/UserRolesDialog';
import type { AdminUser } from '../types';

const PER_PAGE = 20;

export default function UsersPage(): React.JSX.Element {
  const { t } = useTranslation('users');
  const { t: tc } = useTranslation('common');
  const { user: currentUser } = useAuth();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [rolesTarget, setRolesTarget] = useState<AdminUser | null>(null);
  const [deactivateTarget, setDeactivateTarget] = useState<AdminUser | null>(null);

  const { data, isLoading, isError, refetch } = useUsers({
    page,
    per_page: PER_PAGE,
    search: search || undefined,
  });

  const activateUser = useActivateUser();
  const deactivateUser = useDeactivateUser();

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
      accessorKey: 'is_active',
      header: t('column_status'),
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'danger'}>
          {row.original.is_active ? t('status_active') : t('status_inactive')}
        </Badge>
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

        return (
          <div className="flex flex-wrap items-center gap-2">
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
    <PageLayout title={t('page_title')} subtitle={t('page_subtitle')}>
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

      <UserRolesDialog user={rolesTarget} onClose={() => setRolesTarget(null)} />

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
