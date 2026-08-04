import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { useSeasons, useOpenRegistration, useCloseRegistration } from '../hooks/useSeasons';
import { SeasonFormDialog } from '../components/SeasonFormDialog';
import type { Season } from '../types';
import { formatDate } from '@/core/utils';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function SeasonsPage(): React.JSX.Element {
  const { t } = useTranslation('seasons');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [confirmTarget, setConfirmTarget] = useState<Season | null>(null);

  const { data: seasons, isLoading, isError, refetch } = useSeasons();
  const openRegistration = useOpenRegistration();
  const closeRegistration = useCloseRegistration();

  const columns: ColumnDef<Season>[] = [
    {
      accessorKey: 'year',
      header: t('year'),
      cell: ({ row }) => <span className="font-semibold text-slate-900 dark:text-white">{row.original.year}</span>,
    },
    {
      accessorKey: 'slug',
      header: t('slug'),
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.slug}</span>,
    },
    {
      accessorKey: 'registration_start',
      header: t('reg_start'),
      cell: ({ row }) => formatDate(row.original.registration_start),
    },
    {
      accessorKey: 'registration_end',
      header: t('reg_end'),
      cell: ({ row }) => formatDate(row.original.registration_end),
    },
    {
      accessorKey: 'status',
      header: t('status'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'registration_open' ? 'success' : 'neutral'}>
          {row.original.status}
        </Badge>
      ),
    },
    {
      id: 'actions',
      header: t('actions'),
      cell: ({ row }) => (
        <PermissionWrapper role="admin">
          <div className="flex items-center gap-2">
            {row.original.status !== 'registration_open' ? (
              <Button
                size="sm"
                variant="outline"
                isLoading={openRegistration.isPending}
                onClick={() => openRegistration.mutate(row.original.id)}
              >
                {t('open_reg')}
              </Button>
            ) : (
              <Button
                size="sm"
                variant="danger"
                isLoading={closeRegistration.isPending}
                onClick={() => setConfirmTarget(row.original)}
              >
                {t('close_reg')}
              </Button>
            )}
          </div>
        </PermissionWrapper>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: t('title') }]}
      actions={
        <PermissionWrapper role="admin">
          <Button onClick={() => setIsFormOpen(true)}>
            <Plus className="w-4 h-4" />
            <span>إضافة موسم جديد</span>
          </Button>
        </PermissionWrapper>
      }
    >
      <DataTable<Season>
        columns={columns}
        data={seasons ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد مواسم مسجلة حتى الآن."
      />

      <SeasonFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} />

      {confirmTarget && (
        <ConfirmDialog
          isOpen={Boolean(confirmTarget)}
          onClose={() => setConfirmTarget(null)}
          title="تأكيد إغلاق فترات التسجيل"
          description={`هل أنت متأكد من إغلاق فترة التسجيل لموسم ${confirmTarget.year}؟ سيتم منع تسجيل متسابقين جدد وتسجيل العملية في Audit Logs.`}
          confirmLabel="إغلاق التسجيل"
          isLoading={closeRegistration.isPending}
          onConfirm={() => {
            closeRegistration.mutate(confirmTarget.id, {
              onSuccess: () => setConfirmTarget(null),
            });
          }}
        />
      )}
    </PageLayout>
  );
}
