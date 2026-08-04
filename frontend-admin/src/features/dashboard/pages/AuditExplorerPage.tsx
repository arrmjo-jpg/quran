import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import { systemHealthService, type AuditLogEntry } from '../api/systemHealth.service';
import { ShieldCheck, User, Monitor, Globe, Clock, Download } from 'lucide-react';
import { formatDate } from '@/core/utils';
import Button from '@/ui/Button';
import { toast } from 'sonner';

export default function AuditExplorerPage(): React.JSX.Element {
  const [searchTerm, setSearchTerm] = useState('');

  const { data: logs, isLoading, refetch } = useQuery({
    queryKey: ['audit-logs'],
    queryFn: () => systemHealthService.getAuditLogs(),
  });

  const columns: ColumnDef<AuditLogEntry>[] = [
    {
      accessorKey: 'user_name',
      header: 'المستخدم والدور',
      cell: ({ row }) => (
        <div>
          <span className="font-semibold text-slate-900 dark:text-white block">{row.original.user_name}</span>
          <Badge variant="info">{row.original.user_role}</Badge>
        </div>
      ),
    },
    {
      accessorKey: 'action',
      header: 'العملية ونوع الكيان',
      cell: ({ row }) => (
        <div>
          <span className="font-mono text-brand-600 dark:text-brand-400 block font-semibold">{row.original.action}</span>
          <span className="text-[11px] text-slate-400">{row.original.entity_type} #{row.original.entity_id}</span>
        </div>
      ),
    },
    {
      accessorKey: 'ip_address',
      header: 'عنوان IP والمتصفح',
      cell: ({ row }) => (
        <div className="font-mono text-[11px] text-slate-500">
          <p>{row.original.ip_address}</p>
          <p className="text-slate-400 text-[10px] truncate max-w-[150px]">{row.original.user_agent}</p>
        </div>
      ),
    },
    {
      accessorKey: 'created_at',
      header: 'تاريخ الإجراء',
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'result',
      header: 'النتيجة',
      cell: ({ row }) => (
        <Badge variant={row.original.result === 'success' ? 'success' : 'danger'}>
          {row.original.result === 'success' ? 'ناجح ✅' : 'فشل ❌'}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title="مستكشف سجلات التدقيق والأمان (Audit Explorer)"
      subtitle="تتبع وتدقيق جميع حركات المشرفين والحكام والعمليات الحساسة في المنصة"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'سجلات التدقيق' }]}
      actions={
        <Button variant="outline" size="sm" onClick={() => toast.success('بدأ تصدير سجلات التدقيق CSV')}>
          <Download className="w-4 h-4" />
          <span>تصدير السجل CSV</span>
        </Button>
      }
    >
      <DataTable<AuditLogEntry>
        columns={columns}
        data={logs ?? []}
        loading={isLoading}
        onSearchChange={(q) => setSearchTerm(q)}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد سجلات تدقيق مسجلة."
      />
    </PageLayout>
  );
}
