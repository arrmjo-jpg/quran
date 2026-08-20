import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Button from '@/ui/Button';
import { useCenters, useDeleteCenter } from '../hooks/useCenters';
import { CenterFormDialog } from '../components/CenterFormDialog';
import type { Center } from '../types';

const PER_PAGE = 20;

export default function CentersPage(): React.JSX.Element {
  const { t } = useTranslation('centers');
  const { t: tc } = useTranslation('common');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Center | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Center | null>(null);

  const { data, isLoading, isError, refetch } = useCenters({
    page,
    per_page: PER_PAGE,
    search: search || undefined,
  });

  const deleteCenter = useDeleteCenter();

  const openCreate = (): void => {
    setEditing(null);
    setIsFormOpen(true);
  };

  const openEdit = (center: Center): void => {
    setEditing(center);
    setIsFormOpen(true);
  };

  const columns: ColumnDef<Center>[] = [
    {
      accessorKey: 'name',
      header: t('column_name'),
      cell: ({ row }) => (
        <span className="font-semibold text-slate-900 dark:text-white">{row.original.name}</span>
      ),
    },
    {
      accessorKey: 'city',
      header: t('column_city'),
      cell: ({ row }) => (
        <div className="flex flex-col">
          <span>{row.original.city}</span>
          <span className="text-[11px] text-slate-500">{row.original.address}</span>
        </div>
      ),
    },
    {
      // A centre either has both coordinates or neither, so one column rather
      // than two: rendering them apart would suggest half a location is a
      // state this system can be in.
      id: 'coordinates',
      header: t('column_coordinates'),
      cell: ({ row }) =>
        row.original.coordinates === null ? (
          <span className="text-xs text-slate-400">{t('no_coordinates')}</span>
        ) : (
          <span className="inline-flex items-center gap-1 font-mono text-[11px] dir-ltr">
            <MapPin className="w-3.5 h-3.5 text-brand-600" />
            {row.original.coordinates.latitude}, {row.original.coordinates.longitude}
          </span>
        ),
    },
    {
      id: 'actions',
      header: tc('actions'),
      cell: ({ row }) => (
        <div className="flex items-center gap-1">
          <PermissionWrapper permission="centers.update">
            <Button variant="ghost" size="sm" onClick={() => openEdit(row.original)} title={tc('edit')}>
              <Pencil className="w-3.5 h-3.5" />
            </Button>
          </PermissionWrapper>
          <PermissionWrapper permission="centers.delete">
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
        <PermissionWrapper permission="centers.create">
          <Button onClick={openCreate}>
            <Plus className="w-4 h-4" />
            <span>{t('create')}</span>
          </Button>
        </PermissionWrapper>
      }
    >
      <DataTable<Center>
        columns={columns}
        data={data?.centers ?? []}
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

      <CenterFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} editing={editing} />

      <ConfirmDialog
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        title={t('delete_title')}
        // Says what closing does, not just that it is permanent — it is not.
        // The refusal for a populated centre comes from the server, because
        // this screen never loads the circles it would have to count.
        description={t('delete_message', { name: deleteTarget?.name ?? '' })}
        confirmLabel={tc('delete')}
        isLoading={deleteCenter.isPending}
        onConfirm={() => {
          if (!deleteTarget) return;
          deleteCenter.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) });
        }}
      />
    </PageLayout>
  );
}
