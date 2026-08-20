import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';
import { ArrowLeftRight, LogOut, UserPlus } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { Select } from '@/ui/input/Input';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { useCircles } from '@/features/circles/hooks/useCircles';
import { useContestants } from '@/features/contestants/hooks/useContestants';
import { useMemberships } from '../hooks/useMemberships';
import { EnrolMembershipDialog } from '../components/EnrolMembershipDialog';
import { EndMembershipDialog } from '../components/EndMembershipDialog';
import { TransferMembershipDialog } from '../components/TransferMembershipDialog';
import type { Membership } from '../types';

const PER_PAGE = 20;
const CIRCLE_LIMIT = 100;

type StatusFilter = 'all' | 'active' | 'ended';

export default function MembershipsPage(): React.JSX.Element {
  const { t } = useTranslation('memberships');
  const { t: tc } = useTranslation('common');

  const [page, setPage] = useState(1);
  const [circleId, setCircleId] = useState('');
  const [status, setStatus] = useState<StatusFilter>('all');
  const [isEnrolOpen, setIsEnrolOpen] = useState(false);
  const [endTarget, setEndTarget] = useState<Membership | null>(null);
  const [transferTarget, setTransferTarget] = useState<Membership | null>(null);

  const { data, isLoading, isError, refetch } = useMemberships({
    page,
    per_page: PER_PAGE,
    circle_id: circleId || undefined,
    active: status === 'all' ? undefined : status === 'active',
  });

  const circles = useCircles({ page: 1, per_page: CIRCLE_LIMIT });

  /**
   * MembershipResource nests the circle but carries the contestant as a bare
   * id, so the name is resolved here from the contestants list rather than by
   * a request per row. A row showing two opaque ids is a row nobody can read —
   * which is the resource's own stated reason for nesting the circle.
   */
  const contestants = useContestants();
  const contestantName = (id: string): string =>
    contestants.data?.find((c) => c.id === id)?.full_name
    ?? t('contestant_unresolved', { id: id.slice(0, 8) });

  const formatDate = (iso: string | null): string =>
    iso === null ? '—' : new Date(iso).toLocaleDateString();

  const columns: ColumnDef<Membership>[] = [
    {
      id: 'contestant',
      header: t('column_contestant'),
      cell: ({ row }) => (
        <span className="font-semibold text-slate-900 dark:text-white">
          {contestantName(row.original.contestant_id)}
        </span>
      ),
    },
    {
      id: 'circle',
      header: t('column_circle'),
      cell: ({ row }) =>
        row.original.circle ? row.original.circle.name : <span className="text-slate-400">—</span>,
    },
    {
      id: 'period',
      header: t('column_period'),
      cell: ({ row }) => (
        <span className="font-mono text-[11px] dir-ltr">
          {formatDate(row.original.joined_at)} → {formatDate(row.original.left_at)}
        </span>
      ),
    },
    {
      id: 'status',
      header: tc('status'),
      // Read from the server's derived is_active, not recomputed from left_at.
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'neutral'}>
          {row.original.is_active ? t('status_active') : t('status_ended')}
        </Badge>
      ),
    },
    {
      accessorKey: 'reason',
      header: t('column_reason'),
      cell: ({ row }) =>
        row.original.reason ? (
          <span className="text-xs">{row.original.reason}</span>
        ) : (
          <span className="text-xs text-slate-400">—</span>
        ),
    },
    {
      id: 'actions',
      header: tc('actions'),
      // Only an open membership can be transferred or ended. Rendering the
      // buttons on a closed row would offer an action the server refuses.
      cell: ({ row }) =>
        row.original.is_active ? (
          <div className="flex items-center gap-1">
            <PermissionWrapper permission="memberships.transfer">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setTransferTarget(row.original)}
                title={t('transfer_title')}
              >
                <ArrowLeftRight className="w-3.5 h-3.5" />
              </Button>
            </PermissionWrapper>
            <PermissionWrapper permission="memberships.end">
              <Button variant="ghost" size="sm" onClick={() => setEndTarget(row.original)} title={t('end_title')}>
                <LogOut className="w-3.5 h-3.5 text-amber-600" />
              </Button>
            </PermissionWrapper>
          </div>
        ) : (
          <span className="text-xs text-slate-400">—</span>
        ),
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
      actions={
        <PermissionWrapper permission="memberships.create">
          <Button onClick={() => setIsEnrolOpen(true)}>
            <UserPlus className="w-4 h-4" />
            <span>{t('enrol')}</span>
          </Button>
        </PermissionWrapper>
      }
    >
      {/* Filters rather than a search box: GET /admin/memberships takes
          contestant_id, circle_id and active, and no free-text query. A search
          field wired to nothing would be worse than none at all. */}
      <div className="flex flex-wrap items-end gap-3 mb-4">
        <div className="min-w-[220px]">
          <Select
            label={t('filter_circle')}
            options={[
              { value: '', label: t('filter_circle_all') },
              ...(circles.data?.circles ?? []).map((circle) => ({ value: circle.id, label: circle.name })),
            ]}
            value={circleId}
            onChange={(e) => {
              setCircleId(e.target.value);
              setPage(1);
            }}
          />
        </div>

        <div className="min-w-[180px]">
          <Select
            label={t('filter_status')}
            options={[
              { value: 'all', label: t('filter_status_all') },
              { value: 'active', label: t('status_active') },
              { value: 'ended', label: t('status_ended') },
            ]}
            value={status}
            onChange={(e) => {
              setStatus(e.target.value as StatusFilter);
              setPage(1);
            }}
          />
        </div>
      </div>

      <DataTable<Membership>
        columns={columns}
        data={data?.memberships ?? []}
        loading={isLoading}
        searchable={false}
        pagination={{ pageIndex: page - 1, pageSize: PER_PAGE, total: data?.total ?? 0 }}
        onPageChange={(index) => setPage(index + 1)}
        onRefresh={() => void refetch()}
        emptyMessage={t('empty')}
      />

      <EnrolMembershipDialog isOpen={isEnrolOpen} onClose={() => setIsEnrolOpen(false)} />
      <EndMembershipDialog membership={endTarget} onClose={() => setEndTarget(null)} />
      <TransferMembershipDialog membership={transferTarget} onClose={() => setTransferTarget(null)} />
    </PageLayout>
  );
}
