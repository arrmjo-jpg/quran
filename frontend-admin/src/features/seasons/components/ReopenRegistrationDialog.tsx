import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/ui/dialog/Dialog';
import { Textarea } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { useReopenRegistration } from '../hooks/useSeasons';
import type { Season } from '../types';
import { Info } from 'lucide-react';

export interface ReopenRegistrationDialogProps {
  season:  Season | null;
  onClose: () => void;
}

/**
 * Reopening asks for a reason but not for the slug, unlike cancelling and
 * archiving. It is reversible — the window can simply be closed again — and
 * friction should match how irreversible an action is.
 *
 * The reason is mandatory because nothing else records it: the season row
 * only carries the resulting status, which the next close overwrites, so
 * the SeasonRegistrationReopened event is the sole trace of why the window
 * was extended.
 */
export function ReopenRegistrationDialog({ season, onClose }: ReopenRegistrationDialogProps): React.JSX.Element | null {
  const { t } = useTranslation('seasons');
  const { t: tc } = useTranslation('common');
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);
  const reopen = useReopenRegistration();

  useEffect(() => {
    if (season) {
      setReason('');
      setTouched(false);
    }
  }, [season]);

  if (!season) return null;

  const trimmed = reason.trim();
  const error = touched && trimmed === '' ? t('reopen_reason_required') : undefined;

  const submit = () => {
    setTouched(true);
    if (trimmed === '') return;

    reopen.mutate({ id: season.id, payload: { reason: trimmed } }, { onSuccess: onClose });
  };

  return (
    <Dialog isOpen onClose={onClose} title={t('reopen_title', { year: season.year })}>
      <div className="space-y-4">
        <div className="flex items-start gap-3 p-3 text-xs leading-relaxed border rounded-xl bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-900/50 text-sky-800 dark:text-sky-300">
          <Info className="w-5 h-5 shrink-0 mt-0.5" />
          <p>{t('reopen_info')}</p>
        </div>

        <Textarea
          label={t('reopen_reason_label')}
          placeholder={t('reopen_reason_placeholder')}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          onBlur={() => setTouched(true)}
          error={error}
        />

        <p className="text-[11px] leading-relaxed text-slate-500">
          {t('reopen_single_active_note')}
        </p>

        <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" onClick={onClose} disabled={reopen.isPending}>
            {tc('back')}
          </Button>
          <Button variant="primary" size="sm" isLoading={reopen.isPending} onClick={submit}>
            {t('action_reopen')}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
