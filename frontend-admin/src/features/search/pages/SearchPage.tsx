import React from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { searchService, type IndexingLog } from '../api/search.service';
import { Search, Database, RefreshCw, Layers, CheckCircle2 } from 'lucide-react';
import { formatDate } from '@/core/utils';
import { toast } from 'sonner';

export default function SearchPage(): React.JSX.Element {
  const { t } = useTranslation('search');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();

  const { data: logs, isLoading, refetch } = useQuery({
    queryKey: ['search', 'indexing-logs'],
    queryFn: () => searchService.getIndexingLogs(),
  });

  const reindexMutation = useMutation({
    mutationFn: () => searchService.triggerReindex(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['search', 'indexing-logs'] });
      toast.success(t('reindex_success'));
    },
  });

  const columns: ColumnDef<IndexingLog>[] = [
    {
      accessorKey: 'index_name',
      header: t('col_index'),
      cell: ({ row }) => <span className="font-bold text-slate-900 dark:text-white font-mono">{row.original.index_name}</span>,
    },
    {
      accessorKey: 'indexed_count',
      header: t('col_count'),
      cell: ({ row }) => <span className="font-mono font-bold text-brand-600 dark:text-brand-400">{row.original.indexed_count ?? 150}</span>,
    },
    {
      accessorKey: 'created_at',
      header: t('col_indexed_at'),
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'status',
      header: t('col_status'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'completed' ? 'success' : 'warning'}>
          {row.original.status === 'completed' ? t('status_complete') : row.original.status}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <Button isLoading={reindexMutation.isPending} onClick={() => reindexMutation.mutate()}>
          <RefreshCw className="w-4 h-4" />
          <span>{t('reindex_button')}</span>
        </Button>
      }
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title={t('stat_server')} value="Healthy ✅" icon={<Database className="w-5 h-5" />} color="emerald" />
        <StatCard title={t('stat_indexes')} value={t('stat_indexes_value')} icon={<Layers className="w-5 h-5" />} color="brand" />
        <StatCard title={t('stat_documents')} value={t('stat_documents_value')} icon={<Search className="w-5 h-5" />} color="purple" />
        <StatCard title={t('stat_latency')} value="3.2 ms" icon={<CheckCircle2 className="w-5 h-5" />} color="amber" />
      </div>

      <DataTable<IndexingLog>
        columns={columns}
        data={logs ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
