import React, { useEffect, useState } from 'react';
import { Dialog } from '@/ui/dialog/Dialog';
import { Textarea } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { useCancelSeason } from '../hooks/useSeasons';
import type { Season } from '../types';
import { AlertCircle } from 'lucide-react';

export interface CancelSeasonDialogProps {
  season:  Season | null;
  onClose: () => void;
}

/**
 * Cancelling needs a mandatory reason — Season::cancel() rejects a blank
 * one, and CancelSeasonRequest requires the field — so this cannot reuse
 * ConfirmDialog, which has nowhere to type it. Requiring it here means the
 * admin is stopped before the request rather than by a 422 afterwards.
 */
export function CancelSeasonDialog({ season, onClose }: CancelSeasonDialogProps): React.JSX.Element | null {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const cancelSeason = useCancelSeason();

  useEffect(() => {
    if (season) {
      setReason('');
      setTouched(false);
    }
  }, [season]);

  if (!season) return null;

  const trimmed = reason.trim();
  const error = touched && trimmed === '' ? 'سبب الإلغاء إلزامي' : undefined;

  const submit = () => {
    setTouched(true);
    if (trimmed === '') return;

    cancelSeason.mutate({ id: season.id, payload: { reason: trimmed } }, { onSuccess: onClose });
  };

  return (
    <Dialog isOpen onClose={onClose} title={`إلغاء موسم ${season.year}`}>
      <div className="space-y-4">
        <div className="flex items-start gap-3 p-3 text-xs leading-relaxed border rounded-xl bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300">
          <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
          <p>
            الإلغاء متاح فقط قبل فتح التسجيل، ولا يمكن التراجع عنه. سيُحفظ السبب في سجل الموسم مع
            هوية من نفّذ العملية.
          </p>
        </div>

        <Textarea
          label="سبب الإلغاء"
          placeholder="مثال: عدم اكتمال النصاب المطلوب من المشاركين"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          onBlur={() => setTouched(true)}
          error={error}
        />

        <div className="flex items-center justify-end gap-2 pt-2">
          <Button variant="secondary" size="sm" onClick={onClose} disabled={cancelSeason.isPending}>
            تراجع
          </Button>
          <Button variant="danger" size="sm" isLoading={cancelSeason.isPending} onClick={submit}>
            تأكيد الإلغاء
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
