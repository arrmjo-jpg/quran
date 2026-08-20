import React, { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { zodResolver } from '@hookform/resolvers/zod';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Select, Textarea } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { useCircles } from '@/features/circles/hooks/useCircles';
import {
  makeTransferMembershipSchema,
  type TransferMembershipFormValues,
  type TransferMembershipFormOutput,
} from '../schemas/membership.schema';
import { useTransferMembership } from '../hooks/useMemberships';
import type { Membership } from '../types';

const CIRCLE_LIMIT = 100;

export interface TransferMembershipDialogProps {
  membership: Membership | null;
  onClose:    () => void;
}

export function TransferMembershipDialog({
  membership,
  onClose,
}: TransferMembershipDialogProps): React.JSX.Element {
  const { t } = useTranslation('memberships');
  const { t: tc } = useTranslation('common');

  const circles = useCircles({ page: 1, per_page: CIRCLE_LIMIT });
  const transferMembership = useTransferMembership();

  const schema = useMemo(() => makeTransferMembershipSchema(t), [t]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<TransferMembershipFormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    reset({ to_circle_id: '', reason: '', at: '' });
  }, [membership, reset]);

  const onSubmit = (values: TransferMembershipFormValues): void => {
    if (!membership) return;

    const parsed = values as unknown as TransferMembershipFormOutput;

    // The contestant, not the membership: the endpoint closes whichever
    // membership is currently open and opens the new one in one transaction,
    // so it is addressed by who is moving rather than by which row is ending.
    transferMembership.mutate(
      {
        contestant_id: membership.contestant_id,
        to_circle_id: parsed.to_circle_id,
        reason: parsed.reason,
        at: parsed.at,
      },
      { onSuccess: () => onClose() }
    );
  };

  const circleOptions = [
    { value: '', label: t('field_circle_placeholder') },
    ...(circles.data?.circles ?? [])
      // The circle they are already in is not a destination. Offering it would
      // invite a transfer that the server refuses and the operator cannot read
      // as anything but a bug.
      .filter((circle) => circle.id !== membership?.circle_id)
      .map((circle) => ({
        value: circle.id,
        label: circle.center ? `${circle.name} — ${circle.center.name}` : circle.name,
      })),
  ];

  const circlesTruncated = (circles.data?.total ?? 0) > CIRCLE_LIMIT;

  return (
    <Dialog isOpen={membership !== null} onClose={onClose} title={t('transfer_title')}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <p className="text-xs text-slate-600 dark:text-slate-300">
          {t('transfer_explanation', { circle: membership?.circle?.name ?? '' })}
        </p>

        <Select
          label={t('field_to_circle')}
          options={circleOptions}
          disabled={circles.isLoading}
          {...register('to_circle_id')}
          error={errors.to_circle_id?.message}
        />

        {circlesTruncated && <p className="text-[11px] text-amber-600">{t('circles_truncated')}</p>}

        <Textarea
          label={t('field_reason')}
          placeholder={t('field_transfer_reason_placeholder')}
          {...register('reason')}
          error={errors.reason?.message}
        />

        <Input type="date" label={t('field_transfer_at')} {...register('at')} error={errors.at?.message} />

        <p className="text-[11px] text-slate-500">{t('transfer_at_hint')}</p>

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            onClick={onClose}
            disabled={transferMembership.isPending}
          >
            {tc('cancel')}
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={transferMembership.isPending}>
            {t('transfer_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
