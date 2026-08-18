import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import {
  useSeasons,
  useOpenRegistration,
  useCloseRegistration,
  useRestoreSeason,
} from '../hooks/useSeasons';
import { SeasonFormDialog } from '../components/SeasonFormDialog';
import { CancelSeasonDialog } from '../components/CancelSeasonDialog';
import { ArchiveSeasonDialog } from '../components/ArchiveSeasonDialog';
import { ReopenRegistrationDialog } from '../components/ReopenRegistrationDialog';
import { SeasonRulesDialog } from '../components/SeasonRulesDialog';
import { StageManagerDialog } from '@/features/stages/components/StageManagerDialog';
import { StageRulesDialog } from '@/features/stages/components/StageRulesDialog';
import type { Season, SeasonStatus } from '../types';
import { formatDate } from '@/core/utils';
import { Plus, Pencil, SlidersHorizontal, ListOrdered, Scale, RotateCcw, CalendarPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Mirrors SeasonStateMachine exactly. The transition table there is
 * strictly linear — draft → registration_open → registration_closed →
 * competition_running → judging → completed → archived — with one early
 * exit, draft → archived (cancellation).
 *
 * This matters because the previous version offered "open registration" on
 * every season that was not already open, including archived and completed
 * ones. The API refused those with a 409, so nothing broke, but the admin
 * was shown a button for an action that could never succeed. The rule is
 * that the UI must only offer transitions the state machine actually
 * allows.
 */
const canOpenRegistration = (s: Season): boolean => s.status === 'draft';
const canCloseRegistration = (s: Season): boolean => s.status === 'registration_open';
/** Season::archive() guards this to `completed` on top of the state machine. */
const canArchive = (s: Season): boolean => s.status === 'completed';
/** Cancellation is the draft-only early exit; there is no cancelling later. */
const canCancel = (s: Season): boolean => s.status === 'draft';
/** Editing is refused by the aggregate once the season is frozen. */
const canEdit = (s: Season): boolean => !s.is_frozen;
/**
 * Restoring undoes an accidental archival, and only for a season that never
 * actually started. The API applies the full rule — it also refuses when
 * rule snapshots, applications, results, judge assignments or streams exist,
 * none of which this row knows about — so the button is offered on the two
 * conditions visible here and the 409 explains the rest.
 */
const canRestore = (s: Season): boolean => s.status === 'archived' && !s.is_frozen;
/**
 * Reopening is legal only from registration_closed. The API additionally
 * refuses when another season holds the single active slot, which this row
 * cannot know, so the 409 names that season rather than the UI guessing.
 */
const canReopenRegistration = (s: Season): boolean => s.status === 'registration_closed';

/**
 * One mutation hook serves every row, so `isPending` alone is true for the
 * whole table the moment any row is acted on — every season's button then
 * shows a spinner at once and it reads as though they were all triggered
 * together, even though exactly one request was sent.
 *
 * react-query keeps the in-flight variables, which for these mutations is
 * the season id, so comparing against it narrows the loading state back to
 * the row that is actually busy.
 */
function isBusy(mutation: { isPending: boolean; variables?: string }, seasonId: string): boolean {
  return mutation.isPending && mutation.variables === seasonId;
}

const STATUS_VARIANT: Record<SeasonStatus, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
  draft: 'neutral',
  registration_open: 'success',
  registration_closed: 'warning',
  competition_running: 'info',
  judging: 'info',
  completed: 'success',
  archived: 'danger',
};

/**
 * Keys, not labels. Keeping the map keyed on SeasonStatus means adding a
 * state to the backend still fails the build here until it is given a
 * translation, which a lookup built from string concatenation would not.
 */
const STATUS_LABEL_KEY: Record<SeasonStatus, string> = {
  draft: 'status_draft',
  registration_open: 'status_registration_open',
  registration_closed: 'status_registration_closed',
  competition_running: 'status_competition_running',
  judging: 'status_judging',
  completed: 'status_completed',
  archived: 'status_archived',
};

type PendingAction =
  | { kind: 'close'; season: Season }
  | { kind: 'archive'; season: Season }
  | { kind: 'cancel'; season: Season };

export default function SeasonsPage(): React.JSX.Element {
  const { t } = useTranslation('seasons');
  const { t: tc } = useTranslation('common');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Season | null>(null);
  const [rulesTarget, setRulesTarget] = useState<Season | null>(null);
  const [stagesTarget, setStagesTarget] = useState<Season | null>(null);
  const [stageRulesTarget, setStageRulesTarget] = useState<Season | null>(null);
  const [pending, setPending] = useState<PendingAction | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<Season | null>(null);
  const [reopenTarget, setReopenTarget] = useState<Season | null>(null);

  const { data: seasons, isLoading, isError, refetch } = useSeasons();
  const openRegistration = useOpenRegistration();
  const closeRegistration = useCloseRegistration();
  const restoreSeason = useRestoreSeason();

  const closePending = () => setPending(null);

  const openCreate = () => {
    setEditing(null);
    setIsFormOpen(true);
  };

  const openEdit = (season: Season) => {
    setEditing(season);
    setIsFormOpen(true);
  };

  const columns: ColumnDef<Season>[] = [
    {
      accessorKey: 'year',
      header: t('year'),
      cell: ({ row }) => (
        <span className="font-semibold text-slate-900 dark:text-white">{row.original.year}</span>
      ),
    },
    {
      accessorKey: 'title',
      header: t('column_title'),
      cell: ({ row }) => (
        <span className="text-slate-700 dark:text-slate-200">{row.original.title}</span>
      ),
    },
    {
      accessorKey: 'slug',
      header: t('slug'),
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.slug}</span>,
    },
    {
      accessorKey: 'registration_start',
      header: t('reg_start'),
      cell: ({ row }) => formatDate(row.original.registration_start),
    },
    {
      accessorKey: 'registration_end',
      header: t('reg_end'),
      cell: ({ row }) => formatDate(row.original.registration_end),
    },
    {
      accessorKey: 'status',
      header: t('status'),
      cell: ({ row }) => (
        <Badge variant={STATUS_VARIANT[row.original.status]}>
          {t(STATUS_LABEL_KEY[row.original.status])}
        </Badge>
      ),
    },
    {
      id: 'actions',
      header: t('actions'),
      cell: ({ row }) => {
        const season = row.original;

        return (
          <PermissionWrapper role="admin">
            <div className="flex flex-wrap items-center gap-2">
              {canEdit(season) && (
                <Button size="sm" variant="outline" onClick={() => openEdit(season)}>
                  <Pencil className="w-3.5 h-3.5" />
                  <span>{tc('edit')}</span>
                </Button>
              )}

              {canEdit(season) && (
                <Button size="sm" variant="outline" onClick={() => setRulesTarget(season)}>
                  <SlidersHorizontal className="w-3.5 h-3.5" />
                  <span>{t('action_rules')}</span>
                </Button>
              )}

              {/* Available on frozen seasons too — the dialog is read-only
                  there, and being unable to look at the stages of a running
                  season would be worse than showing them. */}
              <Button size="sm" variant="outline" onClick={() => setStagesTarget(season)}>
                <ListOrdered className="w-3.5 h-3.5" />
                <span>{t('action_stages')}</span>
              </Button>

              <Button size="sm" variant="outline" onClick={() => setStageRulesTarget(season)}>
                <Scale className="w-3.5 h-3.5" />
                <span>{t('action_stage_rules')}</span>
              </Button>

              {canOpenRegistration(season) && (
                <Button
                  size="sm"
                  variant="outline"
                  isLoading={isBusy(openRegistration, season.id)}
                  onClick={() => openRegistration.mutate(season.id)}
                >
                  {t('open_reg')}
                </Button>
              )}

              {canCloseRegistration(season) && (
                <Button size="sm" variant="danger" onClick={() => setPending({ kind: 'close', season })}>
                  {t('close_reg')}
                </Button>
              )}

              {canReopenRegistration(season) && (
                <Button size="sm" variant="outline" onClick={() => setReopenTarget(season)}>
                  <CalendarPlus className="w-3.5 h-3.5" />
                  <span>{t('action_reopen')}</span>
                </Button>
              )}

              {canArchive(season) && (
                <Button size="sm" variant="secondary" onClick={() => setPending({ kind: 'archive', season })}>
                  {t('action_archive')}
                </Button>
              )}

              {canRestore(season) && (
                <Button
                  size="sm"
                  variant="outline"
                  isLoading={isBusy(restoreSeason, season.id)}
                  onClick={() => setRestoreTarget(season)}
                >
                  <RotateCcw className="w-3.5 h-3.5" />
                  <span>{t('action_restore')}</span>
                </Button>
              )}

              {canCancel(season) && (
                <Button size="sm" variant="danger" onClick={() => setPending({ kind: 'cancel', season })}>
                  {t('action_cancel')}
                </Button>
              )}
            </div>
          </PermissionWrapper>
        );
      },
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <PermissionWrapper role="admin">
          <Button onClick={openCreate}>
            <Plus className="w-4 h-4" />
            <span>{t('create_button')}</span>
          </Button>
        </PermissionWrapper>
      }
    >
      {isError ? (
        <ErrorState
          title={t('load_error_title')}
          message={t('load_error_message')}
          onRetry={() => void refetch()}
        />
      ) : (
        <DataTable<Season>
          columns={columns}
          data={seasons ?? []}
          loading={isLoading}
          onRefresh={refetch}
          exportable
          emptyMessage={t('empty')}
        />
      )}

      <SeasonFormDialog
        isOpen={isFormOpen}
        season={editing}
        onClose={() => {
          setIsFormOpen(false);
          setEditing(null);
        }}
      />

      {pending?.kind === 'close' && (
        <ConfirmDialog
          isOpen
          onClose={closePending}
          title={t('close_confirm_title')}
          description={t('close_confirm_body', { year: pending.season.year })}
          confirmLabel={t('close_reg')}
          isLoading={closeRegistration.isPending}
          onConfirm={() => closeRegistration.mutate(pending.season.id, { onSuccess: closePending })}
        />
      )}

      {/* Archive and cancel both end a season permanently, so both ask for
          the season's slug rather than a plain confirmation — see
          DangerConfirmationDialog for why identity beats intent here. */}
      <ArchiveSeasonDialog
        season={pending?.kind === 'archive' ? pending.season : null}
        onClose={closePending}
      />

      <ReopenRegistrationDialog season={reopenTarget} onClose={() => setReopenTarget(null)} />

      {/* Restoring is recoverable in a way archiving and cancelling are
          not — it only ever produces a draft — so a plain confirmation is
          enough here, without the slug gate those two require. */}
      {restoreTarget && (
        <ConfirmDialog
          isOpen
          onClose={() => setRestoreTarget(null)}
          title={t('restore_title', { year: restoreTarget.year })}
          description={t('restore_body', { slug: restoreTarget.slug })}
          confirmLabel={t('restore_confirm')}
          isLoading={restoreSeason.isPending}
          onConfirm={() =>
            restoreSeason.mutate(restoreTarget.id, { onSuccess: () => setRestoreTarget(null) })
          }
        />
      )}

      <SeasonRulesDialog season={rulesTarget} onClose={() => setRulesTarget(null)} />

      <StageManagerDialog
        seasonId={stagesTarget?.id ?? null}
        seasonYear={stagesTarget?.year ?? null}
        isFrozen={stagesTarget?.is_frozen ?? false}
        onClose={() => setStagesTarget(null)}
      />

      <StageRulesDialog
        seasonId={stageRulesTarget?.id ?? null}
        seasonYear={stageRulesTarget?.year ?? null}
        isFrozen={stageRulesTarget?.is_frozen ?? false}
        onClose={() => setStageRulesTarget(null)}
      />

      {/* Cancellation needs both the slug confirmation and a mandatory
          reason, so it owns its own dialog too. */}
      <CancelSeasonDialog
        season={pending?.kind === 'cancel' ? pending.season : null}
        onClose={closePending}
      />
    </PageLayout>
  );
}
