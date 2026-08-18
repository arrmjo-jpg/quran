import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog, ConfirmDialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import Badge from '@/ui/Badge';
import Spinner from '@/ui/Spinner';
import { useStages, useDeleteStage, useReorderStages } from '../hooks/useStages';
import { StageFormDialog } from './StageFormDialog';
import type { Stage, StageStatus, StageType } from '../types';
import { stageNameIn } from '../utils/stageName';
import { formatDate } from '@/core/utils';
import { Plus, Pencil, Trash2, ArrowUp, ArrowDown, Save, RotateCcw } from 'lucide-react';

export interface StageManagerDialogProps {
  seasonId:   string | null;
  seasonYear: number | null;
  /** A frozen season's stages cannot change; the API refuses every write. */
  isFrozen:   boolean;
  onClose:    () => void;
}

/** Keys, so adding a type or status to the backend breaks the build here. */
const TYPE_LABEL_KEY: Record<StageType, string> = {
  preliminary: 'type_preliminary',
  semi_final: 'type_semi_final',
  final: 'type_final',
};

const STATUS_LABEL_KEY: Record<StageStatus, string> = {
  pending: 'status_pending',
  active: 'status_active',
  completed: 'status_completed',
};

const STATUS_VARIANT: Record<StageStatus, 'neutral' | 'info' | 'success'> = {
  pending: 'neutral',
  active: 'info',
  completed: 'success',
};

export function StageManagerDialog({
  seasonId,
  seasonYear,
  isFrozen,
  onClose,
}: StageManagerDialogProps): React.JSX.Element | null {
  const { t, i18n } = useTranslation('stages');
  const { t: tc } = useTranslation('common');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Stage | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Stage | null>(null);

  /**
   * Reordering is staged locally and saved in one call, because the API
   * takes the season's complete ordering rather than a single move. Saving
   * on every arrow press would fire a request per click and leave the list
   * half-reordered if one failed.
   */
  const [draftOrder, setDraftOrder] = useState<Stage[] | null>(null);

  const stages = useStages(seasonId);
  const deleteStage = useDeleteStage(seasonId ?? '');
  const reorderStages = useReorderStages(seasonId ?? '');

  useEffect(() => {
    setDraftOrder(null);
  }, [seasonId, stages.data]);

  const displayed = draftOrder ?? stages.data ?? [];

  const isDirty = useMemo(() => {
    if (!draftOrder || !stages.data) return false;
    return draftOrder.some((stage, index) => stage.id !== stages.data[index]?.id);
  }, [draftOrder, stages.data]);

  if (!seasonId) return null;

  const move = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= displayed.length) return;

    const next = [...displayed];
    [next[index], next[target]] = [next[target], next[index]];
    setDraftOrder(next);
  };

  const saveOrder = () => {
    reorderStages.mutate(
      displayed.map((s) => s.id),
      { onSuccess: () => setDraftOrder(null) },
    );
  };

  return (
    <>
      <Dialog isOpen onClose={onClose} title={t('manager_title', { year: seasonYear ?? '' })} className="max-w-3xl">
        <div className="space-y-4">
          {isFrozen && (
            <p className="p-3 text-xs leading-relaxed border rounded-xl bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300">
              {t('manager_frozen_notice')}
            </p>
          )}

          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-500">
              {displayed.length > 0 ? t('stage_count', { count: displayed.length }) : ''}
            </span>

            <div className="flex items-center gap-2">
              {isDirty && (
                <>
                  <Button size="sm" variant="secondary" onClick={() => setDraftOrder(null)} disabled={reorderStages.isPending}>
                    <RotateCcw className="w-3.5 h-3.5" />
                    <span>{tc('back')}</span>
                  </Button>
                  <Button size="sm" variant="primary" isLoading={reorderStages.isPending} onClick={saveOrder}>
                    <Save className="w-3.5 h-3.5" />
                    <span>{t('save_order')}</span>
                  </Button>
                </>
              )}

              {!isFrozen && !isDirty && (
                <Button
                  size="sm"
                  onClick={() => {
                    setEditing(null);
                    setIsFormOpen(true);
                  }}
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>{t('add_stage')}</span>
                </Button>
              )}
            </div>
          </div>

          {stages.isLoading ? (
            <div className="flex items-center justify-center py-10">
              <Spinner />
            </div>
          ) : stages.isError ? (
            <p className="p-3 text-xs border rounded-xl bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-300">
              {t('load_error')}
            </p>
          ) : displayed.length === 0 ? (
            <p className="p-6 text-xs text-center border border-dashed rounded-xl border-slate-200 dark:border-slate-800 text-slate-500">
              {t('empty')}
            </p>
          ) : (
            <ul className="space-y-2">
              {displayed.map((stage, index) => (
                <li
                  key={stage.id}
                  className="flex items-center gap-3 p-3 border rounded-xl border-slate-200 dark:border-slate-800"
                >
                  <span className="flex items-center justify-center w-7 h-7 text-xs font-semibold rounded-lg shrink-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    {index + 1}
                  </span>

                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-semibold truncate text-slate-900 dark:text-white">
                      {stageNameIn(stage, i18n.language)}
                    </p>
                    <p className="text-[11px] text-slate-500">
                      {t(TYPE_LABEL_KEY[stage.type])} · {formatDate(stage.start_date)} — {formatDate(stage.end_date)}
                    </p>
                  </div>

                  <Badge variant={STATUS_VARIANT[stage.status]}>{t(STATUS_LABEL_KEY[stage.status])}</Badge>

                  {!isFrozen && (
                    <div className="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        aria-label={t('move_up')}
                        disabled={index === 0}
                        onClick={() => move(index, -1)}
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed"
                      >
                        <ArrowUp className="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        aria-label={t('move_down')}
                        disabled={index === displayed.length - 1}
                        onClick={() => move(index, 1)}
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed"
                      >
                        <ArrowDown className="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        aria-label={tc('edit')}
                        onClick={() => {
                          setEditing(stage);
                          setIsFormOpen(true);
                        }}
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                      >
                        <Pencil className="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        aria-label={tc('delete')}
                        onClick={() => setDeleteTarget(stage)}
                        className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )}

          {isDirty && (
            <p className="text-[11px] text-amber-600 dark:text-amber-400">
              {t('order_unsaved_note')}
            </p>
          )}
        </div>
      </Dialog>

      <StageFormDialog
        isOpen={isFormOpen}
        seasonId={seasonId}
        stage={editing}
        onClose={() => {
          setIsFormOpen(false);
          setEditing(null);
        }}
      />

      {deleteTarget && (
        <ConfirmDialog
          isOpen
          onClose={() => setDeleteTarget(null)}
          title={t('delete_title')}
          description={t('delete_body', { name: stageNameIn(deleteTarget, i18n.language) })}
          confirmLabel={t('delete_confirm')}
          isLoading={deleteStage.isPending}
          onConfirm={() => deleteStage.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) })}
        />
      )}
    </>
  );
}
