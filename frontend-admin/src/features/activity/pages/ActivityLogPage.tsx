import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { Bot, Clock, User } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { Input, Select } from '@/ui/input/Input';
import Badge from '@/ui/Badge';
import { useActivityLogs } from '../hooks/useActivityLogs';
import type { ActivityLogEntry } from '../types';

/**
 * The activity feed — ADR-017.
 *
 * Replaces `AuditExplorerPage`, which called an endpoint that had never
 * existed and rendered columns nobody had implemented. Its shape is not
 * preserved: `result` and `user_role` are gone with reasons recorded in the
 * ADR, and both timestamps are shown because they are different facts.
 *
 * Filters rather than a search box. The endpoint takes exact values — an
 * entity, an actor, an action, a window — and a free-text field wired to
 * nothing would be worse than none, which is the mistake the memberships
 * screen already documents.
 */
const PER_PAGE = 25;

/** The actions worth offering as a filter, rather than all 46. */
const COMMON_ACTIONS = [
  'contestant_created',
  'contestant_updated',
  'contestant_deleted',
  'contestant_restored',
  'membership_started',
  'membership_ended',
  'contestant_transferred',
  'user_roles_changed',
  'role_permissions_changed',
  'season_created',
  'application_submitted',
];

export default function ActivityLogPage(): React.JSX.Element {
  const { t } = useTranslation('activity');
  const { t: tc } = useTranslation('common');

  const [page, setPage] = useState(1);
  const [action, setAction] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const { data, isLoading, isError, refetch } = useActivityLogs({
    page,
    per_page: PER_PAGE,
    action: action || undefined,
    from: from || undefined,
    to: to || undefined,
  });

  const resetToFirstPage = (): void => setPage(1);

  const formatMoment = (iso: string): string => new Date(iso).toLocaleString();

  const columns: ColumnDef<ActivityLogEntry>[] = [
    {
      id: 'action',
      header: t('col_action'),
      cell: ({ row }) => (
        <div className="flex flex-col">
          {/* Named through the namespace when we have a translation, and shown
              raw when we do not — a new event should read as an unfamiliar
              action, never as a blank cell. */}
          <span className="font-semibold text-slate-900 dark:text-white">
            {t(`action_${row.original.action}`, { defaultValue: row.original.action })}
          </span>
          <span className="font-mono text-[10px] text-slate-400">{row.original.action}</span>
        </div>
      ),
    },
    {
      id: 'entity',
      header: t('col_entity'),
      cell: ({ row }) => (
        <div className="flex flex-col">
          <span className="text-slate-900 dark:text-white">
            {t(`entity_${row.original.entity_type}`, { defaultValue: row.original.entity_type })}
          </span>
          <span className="font-mono text-[10px] text-slate-400 break-all">
            {row.original.entity_id.slice(0, 8)}
          </span>
        </div>
      ),
    },
    {
      id: 'actor',
      header: t('col_actor'),
      cell: ({ row }) => {
        const { actor_id: actorId, actor_type: actorType } = row.original;

        // `system` is a statement, not a missing value: a scheduled job or a
        // console command did this, and nobody was signed in.
        if (actorType === 'system' || actorId === null) {
          return (
            <Badge variant="neutral">
              <Bot className="w-3 h-3" />
              <span>{t('actor_system')}</span>
            </Badge>
          );
        }

        return (
          <div className="flex items-center gap-1.5">
            <User className="w-3.5 h-3.5 text-slate-400" />
            <span className="text-slate-900 dark:text-white">
              {data?.actors[actorId] ?? t('actor_unresolved', { id: actorId.slice(0, 8) })}
            </span>
          </div>
        );
      },
    },
    {
      id: 'occurred_at',
      header: t('col_occurred'),
      cell: ({ row }) => {
        const { occurred_at: occurred, recorded_at: recorded } = row.original;
        const backdated = new Date(recorded).getTime() - new Date(occurred).getTime() > 60_000;

        return (
          <div className="flex flex-col">
            <span className="font-mono text-[11px] text-slate-900 dark:text-white">
              {formatMoment(occurred)}
            </span>

            {/* Only shown when the two genuinely differ. An entry recorded
                well after it happened is worth seeing; one recorded the same
                second is noise. */}
            {backdated && (
              <span className="flex items-center gap-1 text-[10px] text-amber-600">
                <Clock className="w-3 h-3" />
                {t('recorded_later', { at: formatMoment(recorded) })}
              </span>
            )}
          </div>
        );
      },
    },
    {
      id: 'payload',
      header: t('col_details'),
      cell: ({ row }) => {
        // The event's own payload, which is why nothing sensitive appears:
        // events carry field names rather than values by design.
        const entries = Object.entries(row.original.payload).filter(
          ([key]) => !['occurred_at', 'by_user_id'].includes(key)
        );

        if (entries.length === 0) {
          return <span className="text-slate-400">—</span>;
        }

        return (
          <div className="flex flex-wrap gap-1 max-w-md">
            {entries.slice(0, 4).map(([key, value]) => (
              <span
                key={key}
                className="font-mono text-[10px] rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-slate-600 dark:text-slate-300"
              >
                {key}: {typeof value === 'object' ? JSON.stringify(value) : String(value)}
              </span>
            ))}
          </div>
        );
      },
    },
  ];

  if (isError) {
    return (
      <PageLayout title={t('title')} subtitle={t('subtitle')}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
    >
      <div className="flex flex-wrap items-end gap-3 mb-4">
        <div className="min-w-[220px]">
          <Select
            label={t('filter_action')}
            value={action}
            onChange={(e) => {
              setAction(e.target.value);
              resetToFirstPage();
            }}
            options={[
              { value: '', label: t('filter_action_all') },
              ...COMMON_ACTIONS.map((name) => ({
                value: name,
                label: t(`action_${name}`, { defaultValue: name }),
              })),
            ]}
          />
        </div>

        <div className="min-w-[160px]">
          <Input
            type="date"
            label={t('filter_from')}
            value={from}
            onChange={(e) => {
              setFrom(e.target.value);
              resetToFirstPage();
            }}
          />
        </div>

        <div className="min-w-[160px]">
          <Input
            type="date"
            label={t('filter_to')}
            value={to}
            onChange={(e) => {
              setTo(e.target.value);
              resetToFirstPage();
            }}
          />
        </div>
      </div>

      {/* The window filters on when things HAPPENED, not when they were
          written — a backdated entry belongs in the period it describes. */}
      <p className="mb-3 text-[11px] text-slate-500">{t('window_hint')}</p>

      <DataTable<ActivityLogEntry>
        columns={columns}
        data={data?.entries ?? []}
        loading={isLoading}
        searchable={false}
        pagination={{ pageIndex: page - 1, pageSize: PER_PAGE, total: data?.total ?? 0 }}
        onPageChange={(index) => setPage(index + 1)}
        onRefresh={() => void refetch()}
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
