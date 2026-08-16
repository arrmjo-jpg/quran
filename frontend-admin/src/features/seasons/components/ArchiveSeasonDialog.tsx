import React, { useEffect, useState } from 'react';
import { DangerConfirmationDialog } from '@/ui/dialog/DangerConfirmationDialog';
import { Textarea } from '@/ui/input/Input';
import { useArchiveSeason } from '../hooks/useSeasons';
import type { Season } from '../types';

export interface ArchiveSeasonDialogProps {
  season:  Season | null;
  onClose: () => void;
}

/**
 * Archiving a season that ran its course. Terminal and irreversible, so it
 * takes the same slug confirmation as cancelling.
 *
 * Note this is currently unreachable in practice: archive is only legal
 * from `completed`, and the transitions that lead there —
 * startCompetition, openJudging, complete — exist on the aggregate but have
 * no Use Cases and no routes, so no season can reach `completed` through
 * the API. Built now so the path is correct the day those endpoints land,
 * rather than being written under time pressure then.
 *
 * The reason is optional here, unlike cancelling: the season finished
 * normally, so there is nothing to justify. It is offered anyway because
 * ArchiveSeasonRequest accepts one and nothing has ever sent it.
 */
export function ArchiveSeasonDialog({ season, onClose }: ArchiveSeasonDialogProps): React.JSX.Element | null {
  const [reason, setReason] = useState('');
  const archiveSeason = useArchiveSeason();

  useEffect(() => {
    if (season) setReason('');
  }, [season]);

  if (!season) return null;

  const trimmed = reason.trim();

  const submit = () => {
    archiveSeason.mutate(
      { id: season.id, payload: trimmed === '' ? {} : { reason: trimmed } },
      { onSuccess: onClose },
    );
  };

  return (
    <DangerConfirmationDialog
      isOpen
      onClose={onClose}
      onConfirm={submit}
      title={`أرشفة موسم ${season.year}`}
      description="الأرشفة هي الحالة الأخيرة في دورة حياة الموسم ولا يمكن التراجع عنها. لن يعود الموسم قابلاً للتعديل أو التفعيل بعدها."
      confirmationText={season.slug}
      confirmationLabel="للتأكيد، اكتب معرّف الموسم (Slug) كما هو ظاهر أدناه:"
      confirmLabel="تأكيد الأرشفة"
      isLoading={archiveSeason.isPending}
    >
      <Textarea
        label="سبب الأرشفة (اختياري)"
        placeholder="مثال: انتهت المسابقة واعتُمدت النتائج النهائية"
        value={reason}
        onChange={(e) => setReason(e.target.value)}
      />
    </DangerConfirmationDialog>
  );
}
