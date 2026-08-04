import React from 'react';
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
  const queryClient = useQueryClient();

  const { data: exportsList, isLoading, refetch } = useQuery({
    queryKey: ['reports', 'exports'],
    queryFn: () => reportService.getExports(),
  });

  const exportMutation = useMutation({
    mutationFn: (type: string) => reportService.createExport(type),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reports', 'exports'] });
      toast.success('تم إنشاء مهمة تصدير التقارير في الخلفية بنجاح');
    },
  });

  const columns: ColumnDef<ExportJobData>[] = [
    {
      accessorKey: 'type',
      header: 'نوع التقرير وصيغة الملف',
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.type}</span>
        </div>
      ),
    },
    {
      accessorKey: 'created_at',
      header: 'تاريخ الاستخراج',
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'status',
      header: 'حالة التوليد',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'completed' ? 'success' : 'warning'}>
          {row.original.status === 'completed' ? 'جاهز للتحميل' : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'download',
      header: 'تحميل الملف',
      cell: ({ row }) => (
        <Button size="sm" variant="outline" onClick={() => toast.success('بدأ تحميل تقرير التصدير')}>
          <Download className="w-3.5 h-3.5" />
          <span>تحميل CSV / PDF</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title="مركز التقارير والاستخراجات الشاملة (Reports & Analytics Center)"
      subtitle="توليد التقارير التجميعية للمتسابقين والنتائج حسب الدول والمواسم والمراحل بصيغ PDF/Excel/CSV"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'مركز التقارير' }]}
      actions={
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" isLoading={exportMutation.isPending} onClick={() => exportMutation.mutate('contestants_csv')}>
            <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
            <span>تصدير المتسابقين CSV</span>
          </Button>
          <Button variant="primary" size="sm" isLoading={exportMutation.isPending} onClick={() => exportMutation.mutate('results_pdf')}>
            <FileText className="w-4 h-4" />
            <span>تصدير النتائج الرسمية PDF</span>
          </Button>
        </div>
      }
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title="تقارير المتسابقين" value="12 تقرير" icon={<Users className="w-5 h-5" />} color="brand" />
        <StatCard title="التصنيف حسب الدول" value="24 دولة" icon={<Globe className="w-5 h-5" />} color="emerald" />
        <StatCard title="نسب الإنجاز" value="98.2%" icon={<BarChart3 className="w-5 h-5" />} color="purple" />
        <StatCard title="الطلبات المكتملة" value="200/200" icon={<Download className="w-5 h-5" />} color="amber" />
      </div>

      <DataTable<ExportJobData>
        columns={columns}
        data={exportsList ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد مهام تصدير سابقة."
      />
    </PageLayout>
  );
}
