import React, { useEffect, useMemo, useState } from 'react';
import { Dialog, ConfirmDialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import Badge from '@/ui/Badge';
import Spinner from '@/ui/Spinner';
import { useStages, useDeleteStage, useReorderStages } from '../hooks/useStages';
import { StageFormDialog } from './StageFormDialog';
import type { Stage, StageStatus, StageType } from '../types';
import { formatDate } from '@/core/utils';
import { Plus, Pencil, Trash2, ArrowUp, ArrowDown, Save, RotateCcw } from 'lucide-react';

export interface StageManagerDialogProps {
  seasonId:   string | null;
  seasonYear: number | null;
  /** A frozen season's stages cannot change; the API refuses every write. */
  isFrozen:   boolean;
  onClose:    () => void;
}

const TYPE_LABEL: Record<StageType, string> = {
  preliminary: 'تمهيدية',
  semi_final: 'نصف نهائية',
  final: 'نهائية',
};

const STATUS_LABEL: Record<StageStatus, string> = {
  pending: 'لم تبدأ',
  active: 'جارية',
  completed: 'منتهية',
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
      <Dialog isOpen onClose={onClose} title={`مراحل موسم ${seasonYear ?? ''}`} className="max-w-3xl">
        <div className="space-y-4">
          {isFrozen && (
            <p className="p-3 text-xs leading-relaxed border rounded-xl bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300">
              هذا الموسم مجمّد لأن التسجيل فُتح فيه. المراحل معروضة للاطلاع فقط، وسيرفض الخادم أي
              إضافة أو تعديل أو حذف أو إعادة ترتيب.
            </p>
          )}

          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-500">
              {displayed.length > 0 ? `${displayed.length} مرحلة` : ''}
            </span>

            <div className="flex items-center gap-2">
              {isDirty && (
                <>
                  <Button size="sm" variant="secondary" onClick={() => setDraftOrder(null)} disabled={reorderStages.isPending}>
                    <RotateCcw className="w-3.5 h-3.5" />
                    <span>تراجع</span>
                  </Button>
                  <Button size="sm" variant="primary" isLoading={reorderStages.isPending} onClick={saveOrder}>
                    <Save className="w-3.5 h-3.5" />
                    <span>حفظ الترتيب</span>
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
                  <span>إضافة مرحلة</span>
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
              تعذر تحميل مراحل هذا الموسم. أغلق النافذة وأعد فتحها للمحاولة مرة أخرى.
            </p>
          ) : displayed.length === 0 ? (
            <p className="p-6 text-xs text-center border border-dashed rounded-xl border-slate-200 dark:border-slate-800 text-slate-500">
              لا توجد مراحل لهذا الموسم بعد. لن يقبل الخادم فتح التسجيل قبل إضافة مرحلة واحدة على
              الأقل ووضع قاعدة تحكيم لها.
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
                      {stage.translations.ar?.name ?? stage.name ?? '—'}
                    </p>
                    <p className="text-[11px] text-slate-500">
                      {TYPE_LABEL[stage.type]} · {formatDate(stage.start_date)} — {formatDate(stage.end_date)}
                    </p>
                  </div>

                  <Badge variant={STATUS_VARIANT[stage.status]}>{STATUS_LABEL[stage.status]}</Badge>

                  {!isFrozen && (
                    <div className="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        aria-label="نقل لأعلى"
                        disabled={index === 0}
                        onClick={() => move(index, -1)}
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed"
                      >
                        <ArrowUp className="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        aria-label="نقل لأسفل"
                        disabled={index === displayed.length - 1}
                        onClick={() => move(index, 1)}
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed"
                      >
                        <ArrowDown className="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        aria-label="تعديل"
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
                        aria-label="حذف"
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
              الترتيب المعروض لم يُحفظ بعد. الأرقام النهائية تُحدَّد عند الحفظ.
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
          title="تأكيد حذف المرحلة"
          description={`سيتم حذف المرحلة "${deleteTarget.translations.ar?.name ?? deleteTarget.name ?? ''}" نهائياً. الحذف متاح فقط للمراحل التي لم يرتبط بها أي شيء بعد، ولا يمكن التراجع عنه. ترقيم بقية المراحل لن يتغير تلقائياً.`}
          confirmLabel="حذف المرحلة"
          isLoading={deleteStage.isPending}
          onConfirm={() => deleteStage.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) })}
        />
      )}
    </>
  );
}
