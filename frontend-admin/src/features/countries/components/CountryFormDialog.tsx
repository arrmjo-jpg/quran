import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { countrySchema, type CountryFormValues } from '../schemas/country.schema';
import { useCreateCountry } from '../hooks/useCountries';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input } from '@/ui/input/Input';
import Button from '@/ui/Button';

export interface CountryFormDialogProps {
  isOpen:  boolean;
  onClose: () => void;
}

export function CountryFormDialog({ isOpen, onClose }: CountryFormDialogProps): React.JSX.Element {
  const createMutation = useCreateCountry();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CountryFormValues>({
    resolver: zodResolver(countrySchema),
    defaultValues: {
      iso2: 'OM',
      iso3: 'OMN',
      phone_code: '+968',
      name_ar: 'سلطنة عمان',
      name_en: 'Sultanate of Oman',
    },
  });

  const onSubmit = (values: CountryFormValues) => {
    createMutation.mutate(values, {
      onSuccess: () => {
        reset();
        onClose();
      },
    });
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="إضافة دولة جديدة في المنصة">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <div className="grid grid-cols-3 gap-3">
          <Input label="ISO 2 (حرفان)" placeholder="OM" {...register('iso2')} error={errors.iso2?.message} />
          <Input label="ISO 3 (3 أحرف)" placeholder="OMN" {...register('iso3')} error={errors.iso3?.message} />
          <Input label="مفتاح الهاتف" placeholder="+968" {...register('phone_code')} error={errors.phone_code?.message} />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input label="الاسم بالعربية" placeholder="سلطنة عمان" {...register('name_ar')} error={errors.name_ar?.message} />
          <Input label="الاسم بالإنجليزية" placeholder="Oman" {...register('name_en')} error={errors.name_en?.message} />
        </div>

        <Input label="رابط الشعار / العلم (اختياري)" placeholder="https://..." {...register('flag_url')} error={errors.flag_url?.message} />

        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={createMutation.isPending}>
            إلغاء
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={createMutation.isPending}>
            حفظ الدولة
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
