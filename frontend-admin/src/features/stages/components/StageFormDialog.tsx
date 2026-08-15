import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { stageSchema, type StageFormValues } from '../schemas/stage.schema';
import { useCreateStage, useUpdateStage } from '../hooks/useStages';
import type { Stage, StageTranslationPayload, Locale } from '../types';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Select } from '@/ui/input/Input';
import Button from '@/ui/Button';

export interface StageFormDialogProps {
  isOpen:   boolean;
  seasonId: string;
  /** Omit to create; pass a stage to edit it. */
  stage?:   Stage | null;
  onClose:  () => void;
}

const TYPE_OPTIONS = [
  { value: 'preliminary', label: 'تمهيدية' },
  { value: 'semi_final', label: 'نصف نهائية' },
  { value: 'final', label: 'نهائية' },
];

function toDateInput(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 10) : '';
}

function defaultsFor(stage?: Stage | null): StageFormValues {
  if (stage) {
    return {
      type: stage.type,
      start_date: toDateInput(stage.start_date),
      end_date: toDateInput(stage.end_date),
      name_ar: stage.translations.ar?.name ?? '',
      name_en: stage.translations.en?.name ?? '',
      name_es: stage.translations.es?.name ?? '',
      public_name_ar: stage.translations.ar?.public_name ?? '',
      public_name_en: stage.translations.en?.public_name ?? '',
      public_name_es: stage.translations.es?.public_name ?? '',
    };
  }

  return {
    type: 'preliminary',
    start_date: '',
    end_date: '',
    name_ar: '',
    name_en: '',
    name_es: '',
    public_name_ar: '',
    public_name_es: '',
    public_name_en: '',
  };
}

/**
 * Only locales the admin actually filled in are sent. The update endpoint
 * merges translations rather than replacing them, so omitting a locale
 * leaves whatever is stored for it untouched — sending an empty string
 * instead would overwrite a real name with nothing.
 */
function buildTranslations(values: StageFormValues): Partial<Record<Locale, StageTranslationPayload>> {
  const entries: Array<[Locale, string, string]> = [
    ['ar', values.name_ar, values.public_name_ar ?? ''],
    ['en', values.name_en ?? '', values.public_name_en ?? ''],
    ['es', values.name_es ?? '', values.public_name_es ?? ''],
  ];

  const translations: Partial<Record<Locale, StageTranslationPayload>> = {};

  for (const [locale, name, publicName] of entries) {
    if (name.trim() === '') continue;

    translations[locale] = {
      name: name.trim(),
      public_name: publicName.trim() === '' ? null : publicName.trim(),
    };
  }

  return translations;
}

export function StageFormDialog({ isOpen, seasonId, stage, onClose }: StageFormDialogProps): React.JSX.Element {
  const isEditing = Boolean(stage);
  const createStage = useCreateStage(seasonId);
  const updateStage = useUpdateStage(seasonId);
  const isPending = createStage.isPending || updateStage.isPending;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<StageFormValues>({
    resolver: zodResolver(stageSchema),
    defaultValues: defaultsFor(stage),
  });

  useEffect(() => {
    if (isOpen) reset(defaultsFor(stage));
  }, [isOpen, stage, reset]);

  const onSubmit = (values: StageFormValues) => {
    const done = { onSuccess: () => onClose() };

    const shared = {
      type: values.type,
      start_date: values.start_date,
      end_date: values.end_date,
      translations: buildTranslations(values),
    };

    if (stage) {
      updateStage.mutate({ id: stage.id, payload: shared }, done);
      return;
    }

    createStage.mutate(shared, done);
  };

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? `تعديل المرحلة ${stage?.stage_number}` : 'إضافة مرحلة جديدة'}
      className="max-w-2xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        {!isEditing && (
          <p className="p-3 text-xs leading-relaxed border rounded-xl bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-900/50 text-sky-800 dark:text-sky-300">
            ستُضاف المرحلة في نهاية الترتيب. لتغيير موضعها استخدم أسهم الترتيب في قائمة المراحل.
          </p>
        )}

        <div className="grid grid-cols-3 gap-3">
          <Select label="نوع المرحلة" options={TYPE_OPTIONS} {...register('type')} error={errors.type?.message} />
          <Input label="بداية المرحلة" type="date" {...register('start_date')} error={errors.start_date?.message} />
          <Input label="نهاية المرحلة" type="date" {...register('end_date')} error={errors.end_date?.message} />
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input label="الاسم (عربي) *" {...register('name_ar')} error={errors.name_ar?.message} />
          <Input label="الاسم (إنجليزي)" {...register('name_en')} error={errors.name_en?.message} />
          <Input label="الاسم (إسباني)" {...register('name_es')} error={errors.name_es?.message} />
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input label="الاسم المعلن (عربي)" {...register('public_name_ar')} error={errors.public_name_ar?.message} />
          <Input label="الاسم المعلن (إنجليزي)" {...register('public_name_en')} error={errors.public_name_en?.message} />
          <Input label="الاسم المعلن (إسباني)" {...register('public_name_es')} error={errors.public_name_es?.message} />
        </div>

        <p className="text-[11px] leading-relaxed text-slate-500">
          الاسم بالعربية مطلوب الآن. أما اكتمال اللغات الثلاث والأسماء المعلنة فيتحقق منه الخادم عند فتح
          التسجيل، فيمكن استكمالها لاحقاً.
        </p>

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={isPending}>
            إلغاء
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={isPending}>
            {isEditing ? 'حفظ التعديلات' : 'إضافة المرحلة'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
