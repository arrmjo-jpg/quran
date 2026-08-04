import React from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import { Monitor, Trash2, LogOut } from 'lucide-react';
import { formatDate } from '@/core/utils';
import { toast } from 'sonner';

export interface ActiveSession {
  id:           string;
  name:         string;
  is_current:   boolean;
  last_used_at: string | null;
  created_at:   string;
}

export default function ActiveSessionsPage(): React.JSX.Element {
  const queryClient = useQueryClient();

  const { data: sessions, isLoading, refetch } = useQuery({
    queryKey: ['active-sessions'],
    queryFn: async () => {
      const { data } = await http.get<ApiSuccess<ActiveSession[]>>('/admin/auth/sessions');
      return data.data;
    },
  });

  const revokeMutation = useMutation({
    mutationFn: async (id: string) => {
      await http.delete(`/admin/auth/sessions/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['active-sessions'] });
      toast.success('تمت إنهاء وإسقاط الجلسة بنجاح');
    },
  });

  const revokeOtherMutation = useMutation({
    mutationFn: async () => {
      await http.delete('/admin/auth/sessions/other');
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['active-sessions'] });
      toast.success('تمت إنهاء جميع الجلسات الأخرى للحساب بنجاح');
    },
  });

  const columns: ColumnDef<ActiveSession>[] = [
    {
      accessorKey: 'name',
      header: 'اسم الجهاز والجلسة',
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <Monitor className="w-4 h-4 text-brand-600" />
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.name}</span>
        </div>
      ),
    },
    {
      accessorKey: 'is_current',
      header: 'الجلسة الحالية',
      cell: ({ row }) => (
        <Badge variant={row.original.is_current ? 'success' : 'neutral'}>
          {row.original.is_current ? 'هذا الجهاز (الحالي)' : 'جهاز آخر'}
        </Badge>
      ),
    },
    {
      accessorKey: 'created_at',
      header: 'تاريخ الإنشاء',
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      id: 'actions',
      header: 'إنهاء الجلسة',
      cell: ({ row }) =>
        !row.original.is_current ? (
          <Button
            size="sm"
            variant="ghost"
            isLoading={revokeMutation.isPending}
            onClick={() => revokeMutation.mutate(row.original.id)}
          >
            <Trash2 className="w-3.5 h-3.5 text-rose-600" />
            <span>إسقاط الجلسة</span>
          </Button>
        ) : (
          <span className="text-slate-400 text-[10px]">نشط الآن</span>
        ),
    },
  ];

  return (
    <PageLayout
      title="إدارة الأجهزة والجلسات النشطة (Session Revocation Desk)"
      subtitle="تتبع الحسابات المفتوحة على أجهزة المشرفين وطرد الجلسات غير المصرح بها"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'الأجهزة والجلسات' }]}
      actions={
        <Button
          variant="danger"
          size="sm"
          isLoading={revokeOtherMutation.isPending}
          onClick={() => revokeOtherMutation.mutate()}
        >
          <LogOut className="w-4 h-4" />
          <span>إنهاء كافة الجلسات الأخرى</span>
        </Button>
      }
    >
      <DataTable<ActiveSession>
        columns={columns}
        data={sessions ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد جلسات أخرى مسجلة."
      />
    </PageLayout>
  );
}
