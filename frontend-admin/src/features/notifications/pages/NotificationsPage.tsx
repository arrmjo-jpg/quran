import React from 'react';
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('notifications');
  const { t: tc } = useTranslation('common');

  const { data: notifications, isLoading, refetch } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationService.getNotifications(),
  });

  const columns: ColumnDef<NotificationItem>[] = [
    {
      accessorKey: 'type',
      header: t('col_type'),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          {row.original.type.includes('email') ? <Mail className="w-4 h-4 text-sky-500" /> : <MessageSquare className="w-4 h-4 text-emerald-500" />}
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.type}</span>
        </div>
      ),
    },
    {
      accessorKey: 'recipient',
      header: t('col_recipient'),
      cell: ({ row }) => <span className="font-mono text-slate-700 dark:text-slate-300">{row.original.recipient}</span>,
    },
    {
      accessorKey: 'created_at',
      header: t('col_sent_at'),
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'status',
      header: t('col_status'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'sent' ? 'success' : 'warning'}>
          {row.original.status === 'sent' ? t('status_delivered') : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'retry',
      header: t('col_retry'),
      cell: ({ row }) => (
        <Button size="sm" variant="ghost" onClick={() => toast.success(t('retry_success'))}>
          <RefreshCw className="w-3.5 h-3.5 text-brand-600" />
          <span>{t('retry_button')}</span>
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
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title={t('stat_total')} value={notifications?.length ?? 0} icon={<Bell className="w-5 h-5" />} color="brand" />
        <StatCard title={t('stat_email_queue')} value={t('stat_pending_value')} icon={<Mail className="w-5 h-5" />} color="emerald" />
        <StatCard title={t('stat_sms_queue')} value={t('stat_pending_value')} icon={<MessageSquare className="w-5 h-5" />} color="purple" />
        <StatCard title={t('stat_failed')} value={t('stat_failed_value')} icon={<AlertCircle className="w-5 h-5" />} color="amber" />
      </div>

      <DataTable<NotificationItem>
        columns={columns}
        data={notifications ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
