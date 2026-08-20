import React, { useEffect, useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { zodResolver } from '@hookform/resolvers/zod';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Select } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { useCircles } from '@/features/circles/hooks/useCircles';
import { useContestants } from '@/features/contestants/hooks/useContestants';
import {
  makeStartMembershipSchema,
  type StartMembershipFormValues,
  type StartMembershipFormOutput,
} from '../schemas/membership.schema';
import { useStartMembership } from '../hooks/useMemberships';

const CIRCLE_LIMIT = 100;

export interface EnrolMembershipDialogProps {
  isOpen:  boolean;
  onClose: () => void;
}

export function EnrolMembershipDialog({ isOpen, onClose }: EnrolMembershipDialogProps): React.JSX.Element {
  const { t } = useTranslation('memberships');
  const { t: tc } = useTranslation('common');

  const [contestantQuery, setContestantQuery] = useState('');

  // Searched on the server rather than filtered here: there is no bound on how
  // many contestants exist, so loading them all to filter locally is the one
  // approach that gets slower the more successful the platform is.
  const contestants = useContestants({ query: contestantQuery });
  const circles = useCircles({ page: 1, per_page: CIRCLE_LIMIT });

  const startMembership = useStartMembership();

  const schema = useMemo(() => makeStartMembershipSchema(t), [t]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<StartMembershipFormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    reset({ contestant_id: '', circle_id: '', joined_at: '' });
    setContestantQuery('');
  }, [isOpen, reset]);

  const onSubmit = (values: StartMembershipFormValues): void => {
    const parsed = values as unknown as StartMembershipFormOutput;

    startMembership.mutate(
      {
        contestant_id: parsed.contestant_id,
        circle_id: parsed.circle_id,
        joined_at: parsed.joined_at,
      },
      { onSuccess: () => onClose() }
    );
  };

  const contestantOptions = [
    { value: '', label: t('field_contestant_placeholder') },
    ...(contestants.data ?? []).map((c) => ({ value: c.id, label: c.full_name })),
  ];

  const circleOptions = [
    { value: '', label: t('field_circle_placeholder') },
    ...(circles.data?.circles ?? []).map((circle) => ({
      // The centre disambiguates: circle names are unique per centre, so two
      // "Morning Circle" entries at different centres are both legitimate.
      value: circle.id,
      label: circle.center ? `${circle.name} — ${circle.center.name}` : circle.name,
    })),
  ];

  const circlesTruncated = (circles.data?.total ?? 0) > CIRCLE_LIMIT;

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title={t('enrol_title')}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label={t('field_contestant_search')}
          placeholder={t('field_contestant_search_placeholder')}
          value={contestantQuery}
          onChange={(e) => setContestantQuery(e.target.value)}
        />

        <Select
          label={t('field_contestant')}
          options={contestantOptions}
          disabled={contestants.isLoading}
          {...register('contestant_id')}
          error={errors.contestant_id?.message}
        />

        <Select
          label={t('field_circle')}
          options={circleOptions}
          disabled={circles.isLoading}
          {...register('circle_id')}
          error={errors.circle_id?.message}
        />

        {circlesTruncated && <p className="text-[11px] text-amber-600">{t('circles_truncated')}</p>}

        <Input
          type="date"
          label={t('field_joined_at')}
          {...register('joined_at')}
          error={errors.joined_at?.message}
        />

        <p className="text-[11px] text-slate-500">{t('joined_at_hint')}</p>
        <p className="text-[11px] text-slate-500">{t('one_open_membership_hint')}</p>

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={startMembership.isPending}>
            {tc('cancel')}
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={startMembership.isPending}>
            {t('enrol_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
