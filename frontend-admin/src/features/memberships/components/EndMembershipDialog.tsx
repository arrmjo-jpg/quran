import React, { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { zodResolver } from '@hookform/resolvers/zod';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Textarea } from '@/ui/input/Input';
import Button from '@/ui/Button';
import {
  makeEndMembershipSchema,
  type EndMembershipFormValues,
  type EndMembershipFormOutput,
} from '../schemas/membership.schema';
import { useEndMembership } from '../hooks/useMemberships';
import type { Membership } from '../types';

export interface EndMembershipDialogProps {
  membership: Membership | null;
  onClose:    () => void;
}

export function EndMembershipDialog({ membership, onClose }: EndMembershipDialogProps): React.JSX.Element {
  const { t } = useTranslation('memberships');
  const { t: tc } = useTranslation('common');

  const endMembership = useEndMembership();

  const schema = useMemo(() => makeEndMembershipSchema(t), [t]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<EndMembershipFormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    reset({ reason: '', left_at: '' });
  }, [membership, reset]);

  const onSubmit = (values: EndMembershipFormValues): void => {
    if (!membership) return;

    const parsed = values as unknown as EndMembershipFormOutput;

    endMembership.mutate(
      { id: membership.id, reason: parsed.reason, left_at: parsed.left_at },
      { onSuccess: () => onClose() }
    );
  };

  return (
    <Dialog isOpen={membership !== null} onClose={onClose} title={t('end_title')}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {/* Says what ending does rather than warning that it is permanent —
            it is not. The row survives and keeps its dates; that is the
            whole point of Q4 making membership a table. */}
        <p className="text-xs text-slate-600 dark:text-slate-300">
          {t('end_explanation', { circle: membership?.circle?.name ?? '' })}
        </p>

        <Textarea
          label={t('field_reason')}
          placeholder={t('field_reason_placeholder')}
          {...register('reason')}
          error={errors.reason?.message}
        />

        <Input type="date" label={t('field_left_at')} {...register('left_at')} error={errors.left_at?.message} />

        <p className="text-[11px] text-slate-500">{t('left_at_hint')}</p>

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={endMembership.isPending}>
            {tc('cancel')}
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={endMembership.isPending}>
            {t('end_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
