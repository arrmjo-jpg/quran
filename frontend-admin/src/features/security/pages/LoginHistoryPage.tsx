import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { Globe, Monitor, ShieldAlert, ShieldCheck } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { Input, Select } from '@/ui/input/Input';
import Badge from '@/ui/Badge';
import { useLoginHistory } from '../hooks/useSecurity';
import type { LoginAttempt, LoginOutcome } from '../types';

/**
 * Login history — ADR-018 D2, D3.
 *
 * A read over `audit_logs`, which has recorded every login attempt since the
 * platform booted. Nothing here writes; the rows arrive as a side effect of
 * the requests being served.
 *
 * Behind `security.view`, deliberately not `audit.view` — see D3.
 */
const PER_PAGE = 25;

const OUTCOMES: LoginOutcome[] = [
  'success',
  'invalid_credentials',
  'account_inactive',
  'rate_limited',
  'invalid_request',
  'failed',
];

/** Success is the only quiet one. Everything else is worth the reader's eye. */
function outcomeVariant(outcome: LoginOutcome): 'success' | 'danger' | 'warning' {
  if (outcome === 'success') return 'success';
  if (outcome === 'rate_limited') return 'warning';
  return 'danger';
}

export default function LoginHistoryPage(): React.JSX.Element {
  const { t } = useTranslation('security');
  const { t: tc } = useTranslation('common');

  const [page, setPage] = useState(1);
  const [outcome, setOutcome] = useState<LoginOutcome | ''>('');
  const [ip, setIp] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const { data, isLoading, isError, refetch } = useLoginHistory({
    page,
    per_page: PER_PAGE,
    outcome: outcome || undefined,
    ip: ip || undefined,
    from: from || undefined,
    to: to || undefined,
  });

  const resetToFirstPage = (): void => setPage(1);

  const columns: ColumnDef<LoginAttempt>[] = [
    {
      id: 'outcome',
      header: t('col_outcome'),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          {row.original.outcome === 'success' ? (
            <ShieldCheck className="h-4 w-4 text-emerald-600" />
          ) : (
            <ShieldAlert className="h-4 w-4 text-rose-600" />
          )}
          <Badge variant={outcomeVariant(row.original.outcome)}>
            {t(`outcome_${row.original.outcome}`)}
          </Badge>
          {/* The number is kept beside the name so a reader can line this up
              with the API logs without knowing our vocabulary. */}
          <span className="font-mono text-[10px] text-slate-400">{row.original.status}</span>
        </div>
      ),
    },
    {
      id: 'account',
      header: t('col_account'),
      cell: ({ row }) => {
        const id = row.original.user_id;

        // Null means the address matched no account. The address itself is
        // never sent, so this screen cannot become a list of guessed emails.
        if (!id) {
          return <span className="text-slate-400 italic">{t('account_unknown')}</span>;
        }

        return (
          <div className="flex flex-col">
            <span>{data?.users[id] ?? t('account_unresolved', { id: id.slice(0, 8) })}</span>
            {row.original.user_type && (
              <span className="text-[11px] text-slate-500">{row.original.user_type}</span>
            )}
          </div>
        );
      },
    },
    {
      id: 'origin',
      header: t('col_origin'),
      cell: ({ row }) => (
        <div className="flex flex-col gap-0.5">
          <span className="flex items-center gap-1.5 font-mono text-xs">
            <Globe className="h-3 w-3 text-slate-400" />
            {row.original.ip ?? '—'}
          </span>
          <span className="flex items-center gap-1.5 text-[11px] text-slate-500">
            <Monitor className="h-3 w-3 text-slate-400" />
            {/* Null on every row written before the client began sending the
                header (D7) — not an error, just older than the change. */}
            {row.original.device_id
              ? row.original.device_id.slice(0, 8)
              : t('device_not_recorded')}
          </span>
        </div>
      ),
    },
    {
      id: 'user_agent',
      header: t('col_user_agent'),
      cell: ({ row }) => (
        <span className="block max-w-xs truncate text-[11px] text-slate-500" title={row.original.user_agent ?? ''}>
          {row.original.user_agent ?? '—'}
        </span>
      ),
    },
    {
      id: 'attempted_at',
      header: t('col_when'),
      cell: ({ row }) => (
        <span className="text-xs">
          {row.original.attempted_at ? new Date(row.original.attempted_at).toLocaleString() : '—'}
        </span>
      ),
    },
  ];

  if (isError) {
    return (
      <PageLayout title={t('history_title')} subtitle={t('history_subtitle')}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  return (
    <PageLayout
      title={t('history_title')}
      subtitle={t('history_subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('history_title') }]}
    >
      <div className="flex flex-wrap items-end gap-3 mb-4">
        <div className="min-w-[220px]">
          <Select
            label={t('filter_outcome')}
            value={outcome}
            onChange={(e) => {
              setOutcome(e.target.value as LoginOutcome | '');
              resetToFirstPage();
            }}
            options={[
              { value: '', label: t('filter_outcome_all') },
              ...OUTCOMES.map((name) => ({ value: name, label: t(`outcome_${name}`) })),
            ]}
          />
        </div>

        <div className="min-w-[160px]">
          <Input
            label={t('filter_ip')}
            value={ip}
            onChange={(e) => {
              setIp(e.target.value);
              resetToFirstPage();
            }}
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

      <p className="mb-3 text-[11px] text-slate-500">{t('history_hint')}</p>

      <DataTable<LoginAttempt>
        columns={columns}
        data={data?.attempts ?? []}
        loading={isLoading}
        searchable={false}
        pagination={{ pageIndex: page - 1, pageSize: PER_PAGE, total: data?.total ?? 0 }}
        onPageChange={(index) => setPage(index + 1)}
        onRefresh={() => void refetch()}
        emptyMessage={t('history_empty')}
      />
    </PageLayout>
  );
}
