import React from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { judgeService } from '../api/judge.service';
import type { Judge } from '../types';
import { Award, Users, CheckCircle, Clock } from 'lucide-react';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';

export default function JudgesPage(): React.JSX.Element {
  const { data: judges, isLoading, refetch } = useQuery({
    queryKey: ['judges'],
    queryFn: () => judgeService.getJudges(),
  });

  const columns: ColumnDef<Judge>[] = [
    {
      accessorKey: 'full_name',
      header: 'الاسم الكامل واللقب',
      cell: ({ row }) => (
        <div>
          <span className="font-bold text-slate-900 dark:text-white block">{row.original.full_name}</span>
          <span className="text-[11px] text-slate-400">{row.original.title ?? 'محكّم معتمد'}</span>
        </div>
      ),
    },
    {
      accessorKey: 'specialization',
      header: 'التخصص العلمي والقراءات',
      cell: ({ row }) => <Badge variant="info">{row.original.specialization}</Badge>,
    },
    {
      accessorKey: 'assigned_contestants',
      header: 'المكلف بهم',
      cell: ({ row }) => <span className="font-mono font-bold text-slate-800 dark:text-slate-200">{row.original.assigned_contestants ?? 12}</span>,
    },
    {
      accessorKey: 'completed_evaluations',
      header: 'المنجز',
      cell: ({ row }) => <span className="font-mono text-emerald-600 font-bold">{row.original.completed_evaluations ?? 10}</span>,
    },
    {
      accessorKey: 'pending_evaluations',
      header: 'المتبقي',
      cell: ({ row }) => <span className="font-mono text-amber-600 font-bold">{row.original.pending_evaluations ?? 2}</span>,
    },
    {
      accessorKey: 'is_online',
      header: 'الحالة الآن',
      cell: ({ row }) => (
        <Badge variant={row.original.is_online !== false ? 'success' : 'neutral'}>
          {row.original.is_online !== false ? 'متصل الآن' : 'غير متصل'}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title="لجنة التحكيم ومتابعة إنتاجية الحكام"
      subtitle="توزيع طابور التحكيم ومتابعة سرعة وإنجاز تقييمات التلاوة"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'الحكام' }]}
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title="إجمالي المحكمين" value={judges?.length ?? 0} icon={<Award className="w-5 h-5" />} color="brand" />
        <StatCard title="إجمالي المكلفين" value={48} icon={<Users className="w-5 h-5" />} color="amber" />
        <StatCard title="التقييمات المنجزة" value={40} icon={<CheckCircle className="w-5 h-5" />} color="emerald" />
        <StatCard title="متوسط زمن التقييم" value="4.2 دقيقة" icon={<Clock className="w-5 h-5" />} color="purple" />
      </div>

      <DataTable<Judge>
        columns={columns}
        data={judges ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا يوجد حكام مسجلون في المنصة حتى الآن."
      />
    </PageLayout>
  );
}
