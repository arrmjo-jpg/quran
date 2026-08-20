import React, { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { zodResolver } from '@hookform/resolvers/zod';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Select } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { useAllCountries } from '@/features/seasons/hooks/useLookups';
import { makeCenterSchema, type CenterFormValues, type CenterFormOutput } from '../schemas/center.schema';
import { useCreateCenter, useUpdateCenter } from '../hooks/useCenters';
import type { Center } from '../types';

export interface CenterFormDialogProps {
  isOpen:  boolean;
  onClose: () => void;
  editing: Center | null;
}

export function CenterFormDialog({ isOpen, onClose, editing }: CenterFormDialogProps): React.JSX.Element {
  const { t } = useTranslation('centers');
  const { t: tc } = useTranslation('common');

  /**
   * The seasons lookup, not the countries feature's own hook.
   *
   * Two reasons, both about correctness rather than convenience. Its
   * CountryOption matches what CountryResource actually returns — a single
   * localised `name` — whereas the countries feature's type still declares an
   * optional name_ar/name_en pair that is in no response, so every option here
   * would render blank. And it walks every page of a paginated endpoint that
   * defaults to 20, so the picker offers all countries rather than the first
   * screenful.
   */
  const countries = useAllCountries();
  const createCenter = useCreateCenter();
  const updateCenter = useUpdateCenter();

  const schema = useMemo(() => makeCenterSchema(t), [t]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CenterFormValues>({ resolver: zodResolver(schema) });

  // Refilled whenever the target changes, so opening the dialog on a second
  // centre does not show the first one's address.
  useEffect(() => {
    reset({
      name: editing?.name ?? '',
      country_id: editing?.country_id ?? '',
      city: editing?.city ?? '',
      address: editing?.address ?? '',
      latitude: editing?.coordinates?.latitude ?? '',
      longitude: editing?.coordinates?.longitude ?? '',
    });
  }, [editing, isOpen, reset]);

  const isPending = createCenter.isPending || updateCenter.isPending;

  const onSubmit = (values: CenterFormValues): void => {
    const parsed = values as unknown as CenterFormOutput;

    if (editing) {
      // country_id is deliberately not sent. A centre cannot change country,
      // and the server rejects the field outright rather than ignoring it.
      updateCenter.mutate(
        {
          id: editing.id,
          name: parsed.name,
          city: parsed.city,
          address: parsed.address,
          latitude: parsed.latitude,
          longitude: parsed.longitude,
        },
        { onSuccess: () => onClose() }
      );
      return;
    }

    createCenter.mutate(
      {
        name: parsed.name,
        country_id: parsed.country_id,
        city: parsed.city,
        address: parsed.address,
        latitude: parsed.latitude,
        longitude: parsed.longitude,
      },
      { onSuccess: () => onClose() }
    );
  };

  const countryOptions = [
    { value: '', label: t('field_country_placeholder') },
    ...(countries.data ?? []).map((country) => ({ value: country.id, label: country.name })),
  ];

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title={editing ? t('form_title_edit') : t('form_title_create')}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input label={t('field_name')} {...register('name')} error={errors.name?.message} />

        <div className="grid grid-cols-2 gap-3">
          <Select
            label={t('field_country')}
            options={countryOptions}
            // Disabled rather than hidden when editing: an operator should see
            // which country the centre belongs to, and also see that it is not
            // theirs to change.
            disabled={editing !== null || countries.isLoading}
            {...register('country_id')}
            error={errors.country_id?.message}
          />
          <Input label={t('field_city')} {...register('city')} error={errors.city?.message} />
        </div>

        {editing && <p className="text-[11px] text-slate-500">{t('country_immutable_hint')}</p>}

        <Input label={t('field_address')} {...register('address')} error={errors.address?.message} />

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('field_latitude')}
            placeholder="31.9539"
            {...register('latitude')}
            error={errors.latitude?.message}
          />
          <Input
            label={t('field_longitude')}
            placeholder="35.9106"
            {...register('longitude')}
            error={errors.longitude?.message}
          />
        </div>

        <p className="text-[11px] text-slate-500">{t('coordinates_hint')}</p>

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
