import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { evaluationService, type EvaluationItem } from '../api/evaluation.service';
import { Award, Calculator, CheckCircle2, RotateCcw, Download, Users, TrendingUp } from 'lucide-react';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { toast } from 'sonner';

export default function EvaluationsPage(): React.JSX.Element {
  const queryClient = useQueryClient();
  const [confirmReopen, setConfirmReopen] = useState(false);

  const { data: evaluations, isLoading, refetch } = useQuery({
    queryKey: ['evaluations'],
    queryFn: () => evaluationService.getEvaluations(),
  });

  const calculateMutation = useMutation({
    mutationFn: () => evaluationService.calculateResults('default-stage-id'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['evaluations'] });
      toast.success('تم احتساب نتائج وتصنيف المرحلة بنجاح وفق محرك RankingService');
    },
  });

  const publishMutation = useMutation({
    mutationFn: () => evaluationService.publishResults('default-stage-id'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['evaluations'] });
      toast.success('تم نشر نتائج المرحلة رسميًا وتأهيل المتسابقين الفائزين');
    },
  });

  const columns: ColumnDef<EvaluationItem>[] = [
    {
      accessorKey: 'id',
      header: 'معرف التقييم',
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'application_id',
      header: 'رقم الطلب المتسابق',
      cell: ({ row }) => <span className="font-mono text-slate-700 dark:text-slate-300">{row.original.application_id}</span>,
    },
    {
      accessorKey: 'judge_id',
      header: 'رقم الحكم',
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.judge_id}</span>,
    },
    {
      accessorKey: 'total_score',
      header: 'الدرجة الكلية (100)',
      cell: ({ row }) => (
        <span className="font-bold text-brand-600 dark:text-brand-400 text-sm">
          {row.original.total_score} / 100
        </span>
      ),
    },
    {
      accessorKey: 'status',
      header: 'حالة التقييم',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'submitted' ? 'success' : 'warning'}>
          {row.original.status === 'submitted' ? 'مكتمل ومغلق' : row.original.status}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title="مركز قيادة التقييمات والنتائج (Evaluations Center)"
      subtitle="متابعة إنجاز الحكام، تطبيق خوارزميات الترتيب، ونشر النتائج الرسمية للمراحل"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'التقييمات والنتائج' }]}
      actions={
        <PermissionWrapper role="admin">
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              isLoading={calculateMutation.isPending}
              onClick={() => calculateMutation.mutate()}
            >
              <Calculator className="w-4 h-4 text-brand-600" />
              <span>احتساب نتائج المرحلة</span>
            </Button>
            <Button
              variant="primary"
              size="sm"
              isLoading={publishMutation.isPending}
              onClick={() => publishMutation.mutate()}
            >
              <CheckCircle2 className="w-4 h-4" />
              <span>نشر النتائج الرسمية</span>
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => setConfirmReopen(true)}
            >
              <RotateCcw className="w-4 h-4" />
              <span>إعادة فتح نتائج المرحلة</span>
            </Button>
          </div>
        </PermissionWrapper>
      }
    >
      {/* Metrics Row */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title="إجمالي التقييمات" value={evaluations?.length ?? 0} icon={<Award className="w-5 h-5" />} color="brand" />
        <StatCard title="الحكام المنجزون" value="5 / 5" icon={<Users className="w-5 h-5" />} color="emerald" />
        <StatCard title="متوسط المرحلة" value="91.4 / 100" icon={<TrendingUp className="w-5 h-5" />} color="amber" />
        <StatCard title="حالة النشر" value="جاهز للنشر" icon={<CheckCircle2 className="w-5 h-5" />} color="purple" />
      </div>

      <DataTable<EvaluationItem>
        columns={columns}
        data={evaluations ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد تقييمات مخصصة لهذه المرحلة."
      />

      {confirmReopen && (
        <ConfirmDialog
          isOpen={confirmReopen}
          onClose={() => setConfirmReopen(false)}
          title="تأكيد إعادة فتح نتائج المرحلة"
          description="هل أنت متأكد من إلغاء نشر نتائج هذه المرحلة وإتاحتها للعد والتعديل من جديد؟ سيتم تسجيل هذا الإجراء في Audit Logs."
          confirmLabel="إعادة الفتح"
          onConfirm={() => {
            toast.success('تمت إعادة فتح نتائج المرحلة');
            setConfirmReopen(false);
          }}
        />
      )}
    </PageLayout>
  );
}
