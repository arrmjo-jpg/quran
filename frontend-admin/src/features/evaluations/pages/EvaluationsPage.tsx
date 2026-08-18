import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { evaluationService, type EvaluationItem } from '../api/evaluation.service';
import { Award, Calculator, CheckCircle2, RotateCcw, Users, TrendingUp } from 'lucide-react';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { toast } from 'sonner';

export default function EvaluationsPage(): React.JSX.Element {
  const { t } = useTranslation('evaluations');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const [confirmReopen, setConfirmReopen] = useState(false);
  const [stageId, setStageId] = useState('');

  const { data: evaluations, isLoading, refetch } = useQuery({
    queryKey: ['evaluations'],
    queryFn: () => evaluationService.getEvaluations(),
  });

  const calculateMutation = useMutation({
    mutationFn: () => evaluationService.calculateResults(stageId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['evaluations'] });
      toast.success(t('calculate_success'));
    },
    onError: () => toast.error(t('calculate_error')),
  });

  const publishMutation = useMutation({
    mutationFn: () => evaluationService.publishResults(stageId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['evaluations'] });
      toast.success(t('publish_success'));
    },
    onError: () => toast.error(t('publish_error')),
  });

  const reopenMutation = useMutation({
    mutationFn: () => evaluationService.reopenResults(stageId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['evaluations'] });
      toast.success(t('reopen_success'));
      setConfirmReopen(false);
    },
    onError: () => {
      toast.error(t('reopen_error'));
      setConfirmReopen(false);
    },
  });

  const stageActionsDisabled = stageId.trim().length === 0;

  const columns: ColumnDef<EvaluationItem>[] = [
    {
      accessorKey: 'id',
      header: t('col_id'),
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'application_id',
      header: t('col_application'),
      cell: ({ row }) => <span className="font-mono text-slate-700 dark:text-slate-300">{row.original.application_id}</span>,
    },
    {
      accessorKey: 'judge_id',
      header: t('col_judge'),
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.judge_id}</span>,
    },
    {
      accessorKey: 'total_score',
      header: t('col_score'),
      cell: ({ row }) => (
        <span className="text-sm font-bold text-brand-600 dark:text-brand-400">
          {t('score_out_of', { score: row.original.total_score })}
        </span>
      ),
    },
    {
      accessorKey: 'status',
      header: t('col_status'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'submitted' ? 'success' : 'warning'}>
          {row.original.status === 'submitted' ? t('status_submitted') : row.original.status}
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
        <div className="flex items-center gap-2">
          <input
            type="text"
            value={stageId}
            onChange={(e) => setStageId(e.target.value)}
            placeholder={t('stage_id_placeholder')}
            className="w-56 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-brand-500"
          />
          <PermissionWrapper permission="stages.calculate_results">
            <Button
              variant="outline"
              size="sm"
              disabled={stageActionsDisabled}
              isLoading={calculateMutation.isPending}
              onClick={() => calculateMutation.mutate()}
            >
              <Calculator className="w-4 h-4 text-brand-600" />
              <span>{t('action_calculate')}</span>
            </Button>
          </PermissionWrapper>
          <PermissionWrapper permission="stages.publish_results">
            <Button
              variant="primary"
              size="sm"
              disabled={stageActionsDisabled}
              isLoading={publishMutation.isPending}
              onClick={() => publishMutation.mutate()}
            >
              <CheckCircle2 className="w-4 h-4" />
              <span>{t('action_publish')}</span>
            </Button>
          </PermissionWrapper>
          <PermissionWrapper permission="stages.reopen_results">
            <Button
              variant="danger"
              size="sm"
              disabled={stageActionsDisabled}
              onClick={() => setConfirmReopen(true)}
            >
              <RotateCcw className="w-4 h-4" />
              <span>{t('action_reopen')}</span>
            </Button>
          </PermissionWrapper>
        </div>
      }
    >
      {/* Metrics Row */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title={t('stat_total')} value={evaluations?.length ?? 0} icon={<Award className="w-5 h-5" />} color="brand" />
        <StatCard title={t('stat_completed')} value={evaluations?.filter((e) => e.status === 'submitted').length ?? 0} icon={<Users className="w-5 h-5" />} color="emerald" />
        <StatCard
          title={t('stat_average')}
          value={(() => {
            const submitted = evaluations?.filter((e) => e.status === 'submitted') ?? [];
            if (submitted.length === 0) return '—';
            const avg = submitted.reduce((sum, e) => sum + e.total_score, 0) / submitted.length;
            return t('score_out_of', { score: avg.toFixed(1) });
          })()}
          icon={<TrendingUp className="w-5 h-5" />}
          color="amber"
        />
      </div>

      <DataTable<EvaluationItem>
        columns={columns}
        data={evaluations ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />

      {confirmReopen && (
        <ConfirmDialog
          isOpen={confirmReopen}
          onClose={() => setConfirmReopen(false)}
          title={t('reopen_title')}
          description={t('reopen_body')}
          confirmLabel={t('reopen_confirm')}
          isLoading={reopenMutation.isPending}
          onConfirm={() => reopenMutation.mutate()}
        />
      )}
    </PageLayout>
  );
}
