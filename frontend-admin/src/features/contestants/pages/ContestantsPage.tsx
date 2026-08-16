import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Button from '@/ui/Button';
import { useContestants } from '../hooks/useContestants';
import { Contestant360Drawer } from '../components/Contestant360Drawer';
import type { ContestantProfile } from '../types';
import { Eye } from 'lucide-react';

export default function ContestantsPage(): React.JSX.Element {
  const { t } = useTranslation('contestants');
  const { t: tc } = useTranslation('common');
  const [query, setQuery] = useState('');
  const [selectedContestant, setSelectedContestant] = useState<ContestantProfile | null>(null);

  const { data: contestants, isLoading, refetch } = useContestants({ query });

  const columns: ColumnDef<ContestantProfile>[] = [
    {
      accessorKey: 'id',
      header: t('col_id'),
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'full_name',
      header: t('col_full_name'),
      cell: ({ row }) => <span className="font-semibold text-slate-900 dark:text-white">{row.original.full_name}</span>,
    },
    {
      accessorKey: 'phone_number',
      header: t('col_phone'),
      cell: ({ row }) => row.original.phone_number ?? '—',
    },
    {
      id: 'actions',
      header: t('col_profile'),
      cell: ({ row }) => (
        <Button size="sm" variant="ghost" onClick={() => setSelectedContestant(row.original)}>
          <Eye className="w-4 h-4 text-brand-600" />
          <span>{t('view_full_profile')}</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
    >
      <DataTable<ContestantProfile>
        columns={columns}
        data={contestants ?? []}
        loading={isLoading}
        onSearchChange={(q) => setQuery(q)}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />

      <Contestant360Drawer
        contestant={selectedContestant}
        isOpen={Boolean(selectedContestant)}
        onClose={() => setSelectedContestant(null)}
      />
    </PageLayout>
  );
}
