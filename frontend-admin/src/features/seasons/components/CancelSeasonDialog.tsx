import React, { useEffect, useState } from 'react';
import { DangerConfirmationDialog } from '@/ui/dialog/DangerConfirmationDialog';
import { Textarea } from '@/ui/input/Input';
import { useCancelSeason } from '../hooks/useSeasons';
import type { Season } from '../types';

export interface CancelSeasonDialogProps {
  season:  Season | null;
  onClose: () => void;
}

/**
 * Cancelling ends a season permanently — it moves to `archived`, the state
 * machine's terminal state, with no way back. It is also the only route to
 * that state reachable through the API today, since `completed` (and so
 * archiving proper) needs lifecycle steps that have no endpoints yet.
 *
 * That makes this the most dangerous button on the seasons table, sitting
 * in a row where every season looks alike, so it asks for the season's slug
 * rather than a plain confirmation. The reason is mandatory because
 * Season::cancel() rejects a blank one.
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
    <DangerConfirmationDialog
      isOpen
      onClose={onClose}
      onConfirm={submit}
      title={`إلغاء موسم ${season.year}`}
      description="الإلغاء نهائي ولا يمكن التراجع عنه. سينتقل الموسم إلى حالة الأرشفة، ولا توجد طريقة لإعادته إلى المسودة. سيُحفظ السبب في سجل الموسم مع هوية من نفّذ العملية."
      confirmationText={season.slug}
      confirmationLabel="للتأكيد، اكتب معرّف الموسم (Slug) كما هو ظاهر أدناه:"
      confirmLabel="تأكيد الإلغاء"
      isLoading={cancelSeason.isPending}
      extraBlocked={trimmed === ''}
    >
      <Textarea
        label="سبب الإلغاء (إلزامي)"
        placeholder="مثال: عدم اكتمال النصاب المطلوب من المشاركين"
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        onBlur={() => setTouched(true)}
        error={error}
      />
    </DangerConfirmationDialog>
  );
}
