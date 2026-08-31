import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { useNotifications, useRetryNotification } from '../hooks/useNotifications';
import type { NotificationItem } from '../api/notification.service';
import { Mail, MessageSquare, Bell, RefreshCw, AlertCircle, Smartphone } from 'lucide-react';
import { formatDate } from '@/core/utils';

/**
 * Three states, one badge — ADR-020 D6.
 *
 * `retrying` is deliberately absent. The controller used to write it by
 * reaching past its own repository, and the aggregate has never modelled it: a
 * retry returns the row to `queued`, which is what a retry is.
 */
const STATUS_VARIANT: Record<NotificationItem['status'], 'success' | 'warning' | 'danger'> = {
  sent:   'success',
  queued: 'warning',
  failed: 'danger',
};

const CHANNEL_ICON: Record<NotificationItem['channel'], React.ReactNode> = {
  email: <Mail className="w-4 h-4 text-sky-500" />,
  sms:   <MessageSquare className="w-4 h-4 text-emerald-500" />,
  push:  <Smartphone className="w-4 h-4 text-purple-500" />,
};

export default function NotificationsPage(): React.JSX.Element {
  const { t } = useTranslation('notifications');
  const { t: tc } = useTranslation('common');

  const { data: notifications, isLoading, refetch } = useNotifications();
  const retry = useRetryNotification();

  const columns: ColumnDef<NotificationItem>[] = [
    {
      accessorKey: 'template_key',
      header: t('col_type'),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          {CHANNEL_ICON[row.original.channel]}
          <span className="font-semibold text-slate-900 dark:text-white">{row.original.template_key}</span>
        </div>
      ),
    },
    {
      accessorKey: 'user_id',
      // The account, not an address. The log stores user_id and never an
      // email — administrators read other people's rows — so the honest
      // column is a link to the account it is about.
      header: t('col_account'),
      cell: ({ row }) => (
        <Link
          to={`/users/${row.original.user_id}`}
          className="font-mono text-xs text-brand-600 hover:underline"
        >
          {row.original.user_id.slice(0, 8)}…
        </Link>
      ),
    },
    {
      accessorKey: 'created_at',
      header: t('col_created_at'),
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'sent_at',
      header: t('col_sent_at'),
      // Empty until delivery actually happens. Until ADR-020 D2 the
      // repository dropped this column on every write, so it was empty for a
      // different and much worse reason.
      cell: ({ row }) => (row.original.sent_at ? formatDate(row.original.sent_at) : '—'),
    },
    {
      accessorKey: 'status',
      header: t('col_status'),
      cell: ({ row }) => (
        <div className="flex flex-col gap-1">
          <Badge variant={STATUS_VARIANT[row.original.status]}>
            {t(`status_${row.original.status}`)}
          </Badge>
          {/* D10 ends a failed notification here and announces it to nobody,
              so the reason has to be readable on the screen someone came to
              look at. `failed` on its own says something broke and nothing
              about what. */}
          {row.original.error && (
            <span className="text-xs text-rose-600 dark:text-rose-400 max-w-xs truncate" title={row.original.error}>
              {row.original.error}
            </span>
          )}
        </div>
      ),
    },
    {
      id: 'retry',
      header: t('col_retry'),
      cell: ({ row }) => {
        // Only a failed notification can be retried (D5). The server refuses
        // anything else with a 409, and a button that exists only to be
        // refused is worse than no button: it fails after the operator has
        // decided to act.
        if (row.original.status !== 'failed') {
          return <span className="text-slate-400">—</span>;
        }

        return (
          <PermissionWrapper permission="notifications.retry">
            <Button
              size="sm"
              variant="ghost"
              disabled={retry.isPending}
              onClick={() => retry.mutate(row.original.id)}
            >
              <RefreshCw className={`w-3.5 h-3.5 text-brand-600 ${retry.isPending ? 'animate-spin' : ''}`} />
              <span>{t('retry_button')}</span>
            </Button>
          </PermissionWrapper>
        );
      },
    },
  ];

  const rows = notifications ?? [];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
    >
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <StatCard title={t('stat_total')} value={rows.length} icon={<Bell className="w-5 h-5" />} color="brand" />
        <StatCard title={t('stat_queued')} value={rows.filter((n) => n.status === 'queued').length} icon={<Mail className="w-5 h-5" />} color="emerald" />
        <StatCard title={t('stat_failed')} value={rows.filter((n) => n.status === 'failed').length} icon={<AlertCircle className="w-5 h-5" />} color="amber" />
      </div>

      <DataTable<NotificationItem>
        columns={columns}
        data={rows}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
