import React from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { notificationService, type NotificationItem } from '../api/notification.service';
import { Mail, MessageSquare, Bell, RefreshCw, AlertCircle, CheckCircle } from 'lucide-react';
import { formatDate } from '@/core/utils';
import { toast } from 'sonner';

export default function NotificationsPage(): React.JSX.Element {
  const { data: notifications, isLoading, refetch } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationService.getNotifications(),
  });

  const columns: ColumnDef<NotificationItem>[] = [
    {
      accessorKey: 'type',
      header: 'نوع الإشعار والقناة',
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          {row.original.type.includes('email') ? <Mail className="w-4 h-4 text-sky-500" /> : <MessageSquare className="w-4 h-4 text-emerald-500" />}
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.type}</span>
        </div>
      ),
    },
    {
      accessorKey: 'recipient',
      header: 'المستلم والوجهة',
      cell: ({ row }) => <span className="font-mono text-slate-700 dark:text-slate-300">{row.original.recipient}</span>,
    },
    {
      accessorKey: 'created_at',
      header: 'تاريخ الإرسال',
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'status',
      header: 'حالة التسليم',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'sent' ? 'success' : 'warning'}>
          {row.original.status === 'sent' ? 'تم التسليم بنجاح' : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'retry',
      header: 'إعادة المحاولة',
      cell: ({ row }) => (
        <Button size="sm" variant="ghost" onClick={() => toast.success('تمت إعادة إرسال الإشعار لـ Queue')}>
          <RefreshCw className="w-3.5 h-3.5 text-brand-600" />
          <span>إعادة إرسال</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title="مركز الرسائل والإشعارات (Notification & Message Center)"
      subtitle="تتبع طوابير إرسال البريد الإلكتروني والـ SMS والإشعارات الفورية Firebase OTP"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'مركز الإشعارات' }]}
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title="إجمالي الإشعارات المرسلة" value={notifications?.length ?? 0} icon={<Bell className="w-5 h-5" />} color="brand" />
        <StatCard title="طابور البريد Email Queue" value="0 معلق" icon={<Mail className="w-5 h-5" />} color="emerald" />
        <StatCard title="طابور SMS Firebase" value="0 معلق" icon={<MessageSquare className="w-5 h-5" />} color="purple" />
        <StatCard title="الرسائل الفاشلة Failed Jobs" value="0 فشل" icon={<AlertCircle className="w-5 h-5" />} color="amber" />
      </div>

      <DataTable<NotificationItem>
        columns={columns}
        data={notifications ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد إشعارات في سجل الرسائل حتى الآن."
      />
    </PageLayout>
  );
}
