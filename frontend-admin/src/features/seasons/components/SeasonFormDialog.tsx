import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { seasonSchema, type SeasonFormValues } from '../schemas/season.schema';
import { useCreateSeason, useUpdateSeason } from '../hooks/useSeasons';
import type { Season } from '../types';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input } from '@/ui/input/Input';
import Button from '@/ui/Button';

export interface SeasonFormDialogProps {
  isOpen:  boolean;
  onClose: () => void;
  /** Omit to create; pass a season to edit it. */
  season?: Season | null;
}

/** Trims an ISO timestamp down to the yyyy-mm-dd an <input type="date"> expects. */
function toDateInput(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 10) : '';
}

function defaultsFor(season?: Season | null): SeasonFormValues {
  if (season) {
    return {
      year: season.year,
      slug: season.slug,
      registration_start: toDateInput(season.registration_start),
      registration_end: toDateInput(season.registration_end),
      start_date: toDateInput(season.start_date),
      end_date: toDateInput(season.end_date),
      title_ar: season.translations.ar?.title ?? '',
      title_en: season.translations.en?.title ?? '',
      title_es: season.translations.es?.title ?? '',
      public_name_ar: season.translations.ar?.public_name ?? '',
      public_name_en: season.translations.en?.public_name ?? '',
      public_name_es: season.translations.es?.public_name ?? '',
    };
  }

  const thisYear = new Date().getFullYear();

  return {
    year: thisYear + 1,
    slug: `season-${thisYear + 1}`,
    registration_start: toDateInput(new Date().toISOString()),
    registration_end: toDateInput(new Date(Date.now() + 30 * 86400000).toISOString()),
    start_date: toDateInput(new Date(Date.now() + 31 * 86400000).toISOString()),
    end_date: toDateInput(new Date(Date.now() + 90 * 86400000).toISOString()),
    title_ar: '',
    title_en: '',
    title_es: '',
    public_name_ar: '',
    public_name_en: '',
    public_name_es: '',
  };
}

export function SeasonFormDialog({ isOpen, onClose, season }: SeasonFormDialogProps): React.JSX.Element {
  const isEditing = Boolean(season);
  const createMutation = useCreateSeason();
  const updateMutation = useUpdateSeason();
  const isPending = createMutation.isPending || updateMutation.isPending;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<SeasonFormValues>({
    resolver: zodResolver(seasonSchema),
    defaultValues: defaultsFor(season),
  });

  // The dialog stays mounted between openings, so react-hook-form keeps
  // whatever was last typed. Without this, opening "edit" on a second
  // season would show the first one's values.
  useEffect(() => {
    if (isOpen) reset(defaultsFor(season));
  }, [isOpen, season, reset]);

  const onSubmit = (values: SeasonFormValues) => {
    const done = {
      onSuccess: () => {
        reset(defaultsFor(null));
        onClose();
      },
    };

    if (season) {
      updateMutation.mutate({ id: season.id, payload: values }, done);
      return;
    }

    createMutation.mutate(values, done);
  };

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? `تعديل موسم ${season?.year}` : 'إضافة موسم مسابقة جديد'}
      className="max-w-2xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {season?.is_frozen && (
          <p className="p-3 text-xs leading-relaxed border rounded-xl bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300">
            هذا الموسم مجمّد لأن التسجيل فُتح فيه. لن يقبل الخادم أي تعديل على بياناته.
          </p>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Input label="سنة الموسم" type="number" {...register('year')} error={errors.year?.message} />
          <Input label="المعرف (Slug)" placeholder="season-2026" {...register('slug')} error={errors.slug?.message} />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input label="بداية فترة التسجيل" type="date" {...register('registration_start')} error={errors.registration_start?.message} />
          <Input label="نهاية فترة التسجيل" type="date" {...register('registration_end')} error={errors.registration_end?.message} />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input label="بداية الموسم" type="date" {...register('start_date')} error={errors.start_date?.message} />
          <Input label="نهاية الموسم" type="date" {...register('end_date')} error={errors.end_date?.message} />
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input label="عنوان الموسم (عربي)" {...register('title_ar')} error={errors.title_ar?.message} />
          <Input label="عنوان الموسم (إنجليزي)" {...register('title_en')} error={errors.title_en?.message} />
          <Input label="عنوان الموسم (إسباني)" {...register('title_es')} error={errors.title_es?.message} />
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input label="الاسم المعلن (عربي)" {...register('public_name_ar')} error={errors.public_name_ar?.message} />
          <Input label="الاسم المعلن (إنجليزي)" {...register('public_name_en')} error={errors.public_name_en?.message} />
          <Input label="الاسم المعلن (إسباني)" {...register('public_name_es')} error={errors.public_name_es?.message} />
        </div>

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={isPending}>
            إلغاء
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={isPending}>
            {isEditing ? 'حفظ التعديلات' : 'حفظ الموسم'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
