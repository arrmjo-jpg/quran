import React, { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { zodResolver } from '@hookform/resolvers/zod';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Select } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { useCenters } from '@/features/centers/hooks/useCenters';
import { useUsers } from '@/features/users/hooks/useUsers';
import { makeCircleSchema, type CircleFormValues, type CircleFormOutput } from '../schemas/circle.schema';
import { useCreateCircle, useUpdateCircle } from '../hooks/useCircles';
import type { Circle } from '../types';

/**
 * Enough to cover every centre and every administrator in practice, and the
 * count is checked below rather than assumed: a picker that silently omitted
 * the row someone was looking for would look like the record did not exist.
 */
const PICKER_LIMIT = 100;

export interface CircleFormDialogProps {
  isOpen:  boolean;
  onClose: () => void;
  editing: Circle | null;
}

export function CircleFormDialog({ isOpen, onClose, editing }: CircleFormDialogProps): React.JSX.Element {
  const { t } = useTranslation('circles');
  const { t: tc } = useTranslation('common');

  const centers = useCenters({ page: 1, per_page: PICKER_LIMIT });
  // Contestants are users too, so the supervisor list is narrowed to admins:
  // a supervisor is someone who signs in to administer a circle (D9).
  const admins = useUsers({ page: 1, per_page: PICKER_LIMIT, type: 'admin' });

  const createCircle = useCreateCircle();
  const updateCircle = useUpdateCircle();

  const schema = useMemo(() => makeCircleSchema(t), [t]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CircleFormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    reset({
      name: editing?.name ?? '',
      center_id: editing?.center_id ?? '',
      supervisor_user_id: editing?.supervisor_user_id ?? '',
    });
  }, [editing, isOpen, reset]);

  const isPending = createCircle.isPending || updateCircle.isPending;

  const onSubmit = (values: CircleFormValues): void => {
    const parsed = values as unknown as CircleFormOutput;

    if (editing) {
      // center_id is deliberately not sent — a circle cannot move between
      // centres, and the server rejects the field rather than ignoring it.
      updateCircle.mutate(
        { id: editing.id, name: parsed.name, supervisor_user_id: parsed.supervisor_user_id },
        { onSuccess: () => onClose() }
      );
      return;
    }

    createCircle.mutate(
      {
        name: parsed.name,
        center_id: parsed.center_id,
        supervisor_user_id: parsed.supervisor_user_id,
      },
      { onSuccess: () => onClose() }
    );
  };

  const centerOptions = [
    { value: '', label: t('field_center_placeholder') },
    ...(centers.data?.centers ?? []).map((center) => ({
      // The city disambiguates: centre names are unique per city, so two
      // "Central Centre" entries one town apart are both legitimate.
      value: center.id,
      label: `${center.name} — ${center.city}`,
    })),
  ];

  const loadedAdmins = admins.data?.users ?? [];

  const supervisorOptions = [
    { value: '', label: t('field_supervisor_none') },
    ...loadedAdmins.map((user) => ({ value: user.id, label: `${user.name} — ${user.email}` })),
  ];

  // A supervisor appointed before this page was loaded — or beyond the first
  // hundred administrators — must still appear, or saving the form would
  // quietly withdraw an appointment nobody meant to touch.
  if (
    editing?.supervisor_user_id &&
    !loadedAdmins.some((user) => user.id === editing.supervisor_user_id)
  ) {
    supervisorOptions.push({
      value: editing.supervisor_user_id,
      label: t('supervisor_unresolved', { id: editing.supervisor_user_id.slice(0, 8) }),
    });
  }

  // Each list is checked against its own total. Silently showing the first
  // hundred of more would look exactly like the missing row never existed.
  const centersTruncated = (centers.data?.total ?? 0) > PICKER_LIMIT;
  const adminsTruncated = (admins.data?.total ?? 0) > PICKER_LIMIT;

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title={editing ? t('form_title_edit') : t('form_title_create')}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input label={t('field_name')} {...register('name')} error={errors.name?.message} />

        <Select
          label={t('field_center')}
          options={centerOptions}
          // Disabled rather than hidden when editing: an operator should see
          // which centre the circle belongs to, and see that it is not theirs
          // to change.
          disabled={editing !== null || centers.isLoading}
          {...register('center_id')}
          error={errors.center_id?.message}
        />

        {editing ? (
          <p className="text-[11px] text-slate-500">{t('center_immutable_hint')}</p>
        ) : (
          <p className="text-[11px] text-slate-500">{t('location_hint')}</p>
        )}

        {centersTruncated && <p className="text-[11px] text-amber-600">{t('centers_truncated')}</p>}

        <Select
          label={t('field_supervisor')}
          options={supervisorOptions}
          disabled={admins.isLoading}
          {...register('supervisor_user_id')}
          error={errors.supervisor_user_id?.message}
        />

        <p className="text-[11px] text-slate-500">{t('supervisor_hint')}</p>

        {adminsTruncated && <p className="text-[11px] text-amber-600">{t('admins_truncated')}</p>}

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={isPending}>
            {tc('cancel')}
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={isPending}>
            {t('form_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
