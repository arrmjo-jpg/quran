import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { Pencil, Plus, Trash2, UserCheck } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { useUsers } from '@/features/users/hooks/useUsers';
import { useCircles, useDeleteCircle } from '../hooks/useCircles';
import { CircleFormDialog } from '../components/CircleFormDialog';
import type { Circle } from '../types';

const PER_PAGE = 20;
const SUPERVISOR_LOOKUP_LIMIT = 100;

export default function CirclesPage(): React.JSX.Element {
  const { t } = useTranslation('circles');
  const { t: tc } = useTranslation('common');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Circle | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Circle | null>(null);

  const { data, isLoading, isError, refetch } = useCircles({
    page,
    per_page: PER_PAGE,
    search: search || undefined,
  });

  /**
   * CircleResource carries a supervisor id and no name, so the name is
   * resolved here from the administrators list rather than by a request per
   * row. When an id is not among them the raw id is shown truncated — an
   * honest "there is someone, and this is who" beats an empty cell that reads
   * as unassigned.
   */
  const admins = useUsers({ page: 1, per_page: SUPERVISOR_LOOKUP_LIMIT, type: 'admin' });
  const supervisorName = (id: string): string =>
    admins.data?.users.find((user) => user.id === id)?.name
    ?? t('supervisor_unresolved', { id: id.slice(0, 8) });

  const deleteCircle = useDeleteCircle();

  const openCreate = (): void => {
    setEditing(null);
    setIsFormOpen(true);
  };

  const openEdit = (circle: Circle): void => {
    setEditing(circle);
    setIsFormOpen(true);
  };

  const columns: ColumnDef<Circle>[] = [
    {
      accessorKey: 'name',
      header: t('column_name'),
      cell: ({ row }) => (
        <span className="font-semibold text-slate-900 dark:text-white">{row.original.name}</span>
      ),
    },
    {
      // Read through the centre, never copied onto the circle (Q3). The city
      // comes from the centre for the same reason.
      id: 'center',
      header: t('column_center'),
      cell: ({ row }) =>
        row.original.center ? (
          <div className="flex flex-col">
            <span>{row.original.center.name}</span>
            <span className="text-[11px] text-slate-500">{row.original.center.city}</span>
          </div>
        ) : (
          <span className="text-xs text-slate-400">—</span>
        ),
    },
    {
      id: 'supervisor',
      header: t('column_supervisor'),
      cell: ({ row }) =>
        row.original.supervisor_user_id === null ? (
          <Badge variant="neutral">{t('supervisor_none')}</Badge>
        ) : (
          <span className="inline-flex items-center gap-1.5">
            <UserCheck className="w-3.5 h-3.5 text-emerald-600" />
            <span>{supervisorName(row.original.supervisor_user_id)}</span>
          </span>
        ),
    },
    {
      id: 'actions',
      header: tc('actions'),
      cell: ({ row }) => (
        <div className="flex items-center gap-1">
          <PermissionWrapper permission="circles.update">
            <Button variant="ghost" size="sm" onClick={() => openEdit(row.original)} title={tc('edit')}>
              <Pencil className="w-3.5 h-3.5" />
            </Button>
          </PermissionWrapper>
          <PermissionWrapper permission="circles.delete">
            <Button variant="ghost" size="sm" onClick={() => setDeleteTarget(row.original)} title={tc('delete')}>
              <Trash2 className="w-3.5 h-3.5 text-rose-500" />
            </Button>
          </PermissionWrapper>
        </div>
      ),
    },
  ];

  if (isError) {
    return (
      <PageLayout title={t('title')} subtitle={t('subtitle')}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <PermissionWrapper permission="circles.create">
          <Button onClick={openCreate}>
            <Plus className="w-4 h-4" />
            <span>{t('create')}</span>
          </Button>
        </PermissionWrapper>
      }
    >
      <DataTable<Circle>
        columns={columns}
        data={data?.circles ?? []}
        loading={isLoading}
        searchable
        onSearchChange={(query) => {
          setSearch(query);
          // A filtered result set is a different list; staying on page four of
          // the old one would show an empty table.
          setPage(1);
        }}
        pagination={{ pageIndex: page - 1, pageSize: PER_PAGE, total: data?.total ?? 0 }}
        onPageChange={(index) => setPage(index + 1)}
        onRefresh={() => void refetch()}
        emptyMessage={t('empty')}
      />

      <CircleFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} editing={editing} />

      <ConfirmDialog
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        title={t('delete_title')}
        description={t('delete_message', { name: deleteTarget?.name ?? '' })}
        confirmLabel={tc('delete')}
        isLoading={deleteCircle.isPending}
        onConfirm={() => {
          if (!deleteTarget) return;
          deleteCircle.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) });
        }}
      />
    </PageLayout>
  );
}
