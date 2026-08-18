import React from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import StatCard from '@/ui/StatCard';
import { judgeService } from '../api/judge.service';
import type { Judge } from '../types';
import { Award } from 'lucide-react';

export default function JudgesPage(): React.JSX.Element {
  const { t } = useTranslation('judges');
  const { t: tc } = useTranslation('common');

  const { data: judges, isLoading, refetch } = useQuery({
    queryKey: ['judges'],
    queryFn: () => judgeService.getJudges(),
  });

  const columns: ColumnDef<Judge>[] = [
    {
      accessorKey: 'full_name',
      header: t('col_name'),
      cell: ({ row }) => (
        <div>
          <span className="font-bold text-slate-900 dark:text-white block">{row.original.full_name}</span>
          <span className="text-[11px] text-slate-400">{row.original.title ?? t('default_title')}</span>
        </div>
      ),
    },
    {
      accessorKey: 'specialization',
      header: t('col_specialization'),
      cell: ({ row }) => <Badge variant="info">{row.original.specialization}</Badge>,
    },
    {
      accessorKey: 'is_active',
      header: tc('status'),
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'neutral'}>
          {row.original.is_active ? t('status_active') : t('status_inactive')}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title={t('stat_total')} value={judges?.length ?? 0} icon={<Award className="w-5 h-5" />} color="brand" />
        <StatCard title={t('stat_active')} value={judges?.filter((j) => j.is_active).length ?? 0} icon={<Award className="w-5 h-5" />} color="emerald" />
      </div>

      <DataTable<Judge>
        columns={columns}
        data={judges ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
