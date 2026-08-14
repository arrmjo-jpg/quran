import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { seasonSchema, type SeasonFormValues } from '../schemas/season.schema';
import { useCreateSeason } from '../hooks/useSeasons';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input } from '@/ui/input/Input';
import Button from '@/ui/Button';

export interface SeasonFormDialogProps {
  isOpen:  boolean;
  onClose: () => void;
}

export function SeasonFormDialog({ isOpen, onClose }: SeasonFormDialogProps): React.JSX.Element {
  const createMutation = useCreateSeason();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<SeasonFormValues>({
    resolver: zodResolver(seasonSchema),
    defaultValues: {
      year: 2026,
      slug: 'season-2026',
      registration_start: new Date().toISOString().split('T')[0],
      registration_end: new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
      start_date: new Date(Date.now() + 31 * 86400000).toISOString().split('T')[0],
      end_date: new Date(Date.now() + 90 * 86400000).toISOString().split('T')[0],
      title_ar: '',
      title_en: '',
      title_es: '',
      public_name_ar: '',
      public_name_en: '',
      public_name_es: '',
    },
  });

  const onSubmit = (values: SeasonFormValues) => {
    createMutation.mutate(values, {
      onSuccess: () => {
        reset();
        onClose();
      },
    });
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="إضافة موسم مسابقة جديد">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <Input
            label="سنة الموسم"
            type="number"
            {...register('year')}
            error={errors.year?.message}
          />
          <Input
            label="المعرف (Slug)"
            placeholder="season-2026"
            {...register('slug')}
            error={errors.slug?.message}
          />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label="بداية فترات التسجيل"
            type="date"
            {...register('registration_start')}
            error={errors.registration_start?.message}
          />
          <Input
            label="نهاية فترات التسجيل"
            type="date"
            {...register('registration_end')}
            error={errors.registration_end?.message}
          />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label="بداية الموسم"
            type="date"
            {...register('start_date')}
            error={errors.start_date?.message}
          />
          <Input
            label="نهاية الموسم"
            type="date"
            {...register('end_date')}
            error={errors.end_date?.message}
          />
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input
            label="عنوان الموسم (عربي)"
            {...register('title_ar')}
            error={errors.title_ar?.message}
          />
          <Input
            label="عنوان الموسم (إنجليزي)"
            {...register('title_en')}
            error={errors.title_en?.message}
          />
          <Input
            label="عنوان الموسم (إسباني)"
            {...register('title_es')}
            error={errors.title_es?.message}
          />
        </div>

        <div className="grid grid-cols-3 gap-3">
          <Input
            label="الاسم المعلن (عربي)"
            {...register('public_name_ar')}
            error={errors.public_name_ar?.message}
          />
          <Input
            label="الاسم المعلن (إنجليزي)"
            {...register('public_name_en')}
            error={errors.public_name_en?.message}
          />
          <Input
            label="الاسم المعلن (إسباني)"
            {...register('public_name_es')}
            error={errors.public_name_es?.message}
          />
        </div>

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={createMutation.isPending}>
            إلغاء
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={createMutation.isPending}>
            حفظ الموسم
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
