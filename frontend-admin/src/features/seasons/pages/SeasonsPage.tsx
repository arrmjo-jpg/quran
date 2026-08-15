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
  useArchiveSeason,
} from '../hooks/useSeasons';
import { SeasonFormDialog } from '../components/SeasonFormDialog';
import { CancelSeasonDialog } from '../components/CancelSeasonDialog';
import { SeasonRulesDialog } from '../components/SeasonRulesDialog';
import { StageManagerDialog } from '@/features/stages/components/StageManagerDialog';
import { StageRulesDialog } from '@/features/stages/components/StageRulesDialog';
import type { Season, SeasonStatus } from '../types';
import { formatDate } from '@/core/utils';
import { Plus, Pencil, SlidersHorizontal, ListOrdered, Scale } from 'lucide-react';
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

const STATUS_VARIANT: Record<SeasonStatus, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
  draft: 'neutral',
  registration_open: 'success',
  registration_closed: 'warning',
  competition_running: 'info',
  judging: 'info',
  completed: 'success',
  archived: 'danger',
};

const STATUS_LABEL: Record<SeasonStatus, string> = {
  draft: 'مسودة',
  registration_open: 'التسجيل مفتوح',
  registration_closed: 'التسجيل مغلق',
  competition_running: 'المسابقة جارية',
  judging: 'قيد التحكيم',
  completed: 'مكتمل',
  archived: 'مؤرشف',
};

type PendingAction =
  | { kind: 'close'; season: Season }
  | { kind: 'archive'; season: Season }
  | { kind: 'cancel'; season: Season };

export default function SeasonsPage(): React.JSX.Element {
  const { t } = useTranslation('seasons');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Season | null>(null);
  const [rulesTarget, setRulesTarget] = useState<Season | null>(null);
  const [stagesTarget, setStagesTarget] = useState<Season | null>(null);
  const [stageRulesTarget, setStageRulesTarget] = useState<Season | null>(null);
  const [pending, setPending] = useState<PendingAction | null>(null);

  const { data: seasons, isLoading, isError, refetch } = useSeasons();
  const openRegistration = useOpenRegistration();
  const closeRegistration = useCloseRegistration();
  const archiveSeason = useArchiveSeason();

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
      header: 'العنوان',
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
          {STATUS_LABEL[row.original.status]}
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
                  <span>تعديل</span>
                </Button>
              )}

              {canEdit(season) && (
                <Button size="sm" variant="outline" onClick={() => setRulesTarget(season)}>
                  <SlidersHorizontal className="w-3.5 h-3.5" />
                  <span>القواعد</span>
                </Button>
              )}

              {/* Available on frozen seasons too — the dialog is read-only
                  there, and being unable to look at the stages of a running
                  season would be worse than showing them. */}
              <Button size="sm" variant="outline" onClick={() => setStagesTarget(season)}>
                <ListOrdered className="w-3.5 h-3.5" />
                <span>المراحل</span>
              </Button>

              <Button size="sm" variant="outline" onClick={() => setStageRulesTarget(season)}>
                <Scale className="w-3.5 h-3.5" />
                <span>قواعد التحكيم</span>
              </Button>

              {canOpenRegistration(season) && (
                <Button
                  size="sm"
                  variant="outline"
                  isLoading={openRegistration.isPending}
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

              {canArchive(season) && (
                <Button size="sm" variant="secondary" onClick={() => setPending({ kind: 'archive', season })}>
                  أرشفة
                </Button>
              )}

              {canCancel(season) && (
                <Button size="sm" variant="danger" onClick={() => setPending({ kind: 'cancel', season })}>
                  إلغاء الموسم
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
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: t('title') }]}
      actions={
        <PermissionWrapper role="admin">
          <Button onClick={openCreate}>
            <Plus className="w-4 h-4" />
            <span>إضافة موسم جديد</span>
          </Button>
        </PermissionWrapper>
      }
    >
      {isError ? (
        <ErrorState
          title="تعذر تحميل المواسم"
          message="لم نتمكن من جلب قائمة المواسم من الخادم. تحقق من الاتصال وأعد المحاولة."
          onRetry={() => void refetch()}
        />
      ) : (
        <DataTable<Season>
          columns={columns}
          data={seasons ?? []}
          loading={isLoading}
          onRefresh={refetch}
          exportable
          emptyMessage="لا توجد مواسم مسجلة حتى الآن."
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
          title="تأكيد إغلاق فترة التسجيل"
          description={`هل أنت متأكد من إغلاق فترة التسجيل لموسم ${pending.season.year}؟ سيتم منع تسجيل متسابقين جدد وتسجيل العملية في Audit Logs.`}
          confirmLabel="إغلاق التسجيل"
          isLoading={closeRegistration.isPending}
          onConfirm={() => closeRegistration.mutate(pending.season.id, { onSuccess: closePending })}
        />
      )}

      {pending?.kind === 'archive' && (
        <ConfirmDialog
          isOpen
          onClose={closePending}
          title="تأكيد أرشفة الموسم"
          description={`سيتم أرشفة موسم ${pending.season.year} نهائياً. الأرشفة هي الحالة الأخيرة في دورة حياة الموسم ولا يمكن التراجع عنها.`}
          confirmLabel="أرشفة الموسم"
          isLoading={archiveSeason.isPending}
          onConfirm={() =>
            archiveSeason.mutate({ id: pending.season.id, payload: {} }, { onSuccess: closePending })
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

      {/* Cancellation has its own dialog because the reason is mandatory
          and ConfirmDialog has nowhere to type it. */}
      <CancelSeasonDialog
        season={pending?.kind === 'cancel' ? pending.season : null}
        onClose={closePending}
      />
    </PageLayout>
  );
}
