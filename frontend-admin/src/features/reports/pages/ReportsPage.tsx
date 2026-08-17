import React from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { reportService, type ExportJobData } from '../api/report.service';
import { FileSpreadsheet, FileText, Download, BarChart3, Globe, Users } from 'lucide-react';
import { formatDate } from '@/core/utils';
import { toast } from 'sonner';

export default function ReportsPage(): React.JSX.Element {
  const { t } = useTranslation('reports');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();

  const { data: exportsList, isLoading, refetch } = useQuery({
    queryKey: ['reports', 'exports'],
    queryFn: () => reportService.getExports(),
  });

  const exportMutation = useMutation({
    mutationFn: (type: string) => reportService.createExport(type),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reports', 'exports'] });
      toast.success(t('export_success'));
    },
  });

  const columns: ColumnDef<ExportJobData>[] = [
    {
      accessorKey: 'type',
      header: t('col_type'),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.type}</span>
        </div>
      ),
    },
    {
      accessorKey: 'created_at',
      header: t('col_created_at'),
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'status',
      header: t('col_status'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'completed' ? 'success' : 'warning'}>
          {row.original.status === 'completed' ? t('status_ready') : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'download',
      header: t('col_download'),
      cell: ({ row }) => (
        <Button size="sm" variant="outline" onClick={() => toast.success(t('download_started'))}>
          <Download className="w-3.5 h-3.5" />
          <span>{t('download_button')}</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" isLoading={exportMutation.isPending} onClick={() => exportMutation.mutate('contestants_csv')}>
            <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
            <span>{t('action_export_contestants')}</span>
          </Button>
          <Button variant="primary" size="sm" isLoading={exportMutation.isPending} onClick={() => exportMutation.mutate('results_pdf')}>
            <FileText className="w-4 h-4" />
            <span>{t('action_export_results')}</span>
          </Button>
        </div>
      }
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title={t('stat_contestant_reports')} value={t('stat_contestant_reports_value')} icon={<Users className="w-5 h-5" />} color="brand" />
        <StatCard title={t('stat_by_country')} value={t('stat_by_country_value')} icon={<Globe className="w-5 h-5" />} color="emerald" />
        <StatCard title={t('stat_completion')} value="98.2%" icon={<BarChart3 className="w-5 h-5" />} color="purple" />
        <StatCard title={t('stat_completed_requests')} value="200/200" icon={<Download className="w-5 h-5" />} color="amber" />
      </div>

      <DataTable<ExportJobData>
        columns={columns}
        data={exportsList ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
