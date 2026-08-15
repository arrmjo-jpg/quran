import React, { useEffect, useMemo, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { seasonRulesSchema, type SeasonRulesFormValues } from '../schemas/seasonRules.schema';
import { useUpdateSeasonRules } from '../hooks/useSeasons';
import { useParticipationTypes, useTajweedLevels, useAllCountries } from '../hooks/useLookups';
import type { Season, LookupOption } from '../types';
import { Dialog } from '@/ui/dialog/Dialog';
import { Input, Select } from '@/ui/input/Input';
import Button from '@/ui/Button';
import Spinner from '@/ui/Spinner';
import { AlertCircle, Search } from 'lucide-react';

export interface SeasonRulesDialogProps {
  season:  Season | null;
  onClose: () => void;
}

/** Catalog names arrive per locale; prefer Arabic, fall back to English, then the code. */
function labelOf(option: LookupOption): string {
  return option.name.ar ?? option.name.en ?? option.code;
}

export function SeasonRulesDialog({ season, onClose }: SeasonRulesDialogProps): React.JSX.Element | null {
  const [countrySearch, setCountrySearch] = useState('');

  const participationTypes = useParticipationTypes();
  const tajweedLevels = useTajweedLevels();
  const countries = useAllCountries();
  const updateRules = useUpdateSeasonRules();

  const {
    register,
    handleSubmit,
    control,
    reset,
    formState: { errors },
  } = useForm<SeasonRulesFormValues>({
    resolver: zodResolver(seasonRulesSchema),
    defaultValues: {
      min_age: 10,
      max_age: 18,
      participation_type_id: '',
      tajweed_level_id: '',
      country_ids: [],
    },
  });

  // Prefill from the season every time the dialog opens. country_ids is the
  // reason the API had to expose the current set at all: PATCH replaces it
  // wholesale, so anything not sent back is deleted.
  useEffect(() => {
    if (!season) return;

    reset({
      min_age: season.min_age ?? 10,
      max_age: season.max_age ?? 18,
      participation_type_id: season.participation_type_id ?? '',
      tajweed_level_id: season.tajweed_level_id ?? '',
      country_ids: season.country_ids,
    });
    setCountrySearch('');
  }, [season, reset]);

  const filteredCountries = useMemo(() => {
    const list = countries.data ?? [];
    const q = countrySearch.trim().toLowerCase();

    if (!q) return list;

    return list.filter(
      (c) =>
        c.name.toLowerCase().includes(q) ||
        c.iso2.toLowerCase().includes(q) ||
        c.iso3.toLowerCase().includes(q),
    );
  }, [countries.data, countrySearch]);

  if (!season) return null;

  const catalogsLoading = participationTypes.isLoading || tajweedLevels.isLoading || countries.isLoading;
  const catalogsFailed = participationTypes.isError || tajweedLevels.isError || countries.isError;

  const onSubmit = (values: SeasonRulesFormValues) => {
    updateRules.mutate({ id: season.id, payload: values }, { onSuccess: onClose });
  };

  return (
    <Dialog isOpen onClose={onClose} title={`قواعد موسم ${season.year}`} className="max-w-3xl">
      {catalogsLoading ? (
        <div className="flex items-center justify-center py-10">
          <Spinner />
        </div>
      ) : catalogsFailed ? (
        <p className="p-3 text-xs border rounded-xl bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-300">
          تعذر تحميل قوائم الخيارات (أنواع المشاركة، مستويات التجويد، الدول). لا يمكن ضبط القواعد قبل
          تحميلها — أعد فتح النافذة للمحاولة مرة أخرى.
        </p>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {season.is_frozen && (
            <div className="flex items-start gap-3 p-3 text-xs leading-relaxed border rounded-xl bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300">
              <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
              <p>
                هذا الموسم مجمّد لأن التسجيل فُتح فيه. القواعد التي سجّل المتسابقون تحتها لا يمكن
                تغييرها، وسيرفض الخادم أي حفظ.
              </p>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3">
            <Input label="الحد الأدنى للعمر" type="number" {...register('min_age')} error={errors.min_age?.message} />
            <Input label="الحد الأعلى للعمر" type="number" {...register('max_age')} error={errors.max_age?.message} />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <Select
              label="نوع المشاركة"
              options={[
                { value: '', label: '— اختر —' },
                ...(participationTypes.data ?? []).map((o) => ({ value: o.id, label: labelOf(o) })),
              ]}
              {...register('participation_type_id')}
              error={errors.participation_type_id?.message}
            />
            <Select
              label="مستوى التجويد"
              options={[
                { value: '', label: '— اختر —' },
                ...(tajweedLevels.data ?? []).map((o) => ({ value: o.id, label: labelOf(o) })),
              ]}
              {...register('tajweed_level_id')}
              error={errors.tajweed_level_id?.message}
            />
          </div>

          <Controller
            control={control}
            name="country_ids"
            render={({ field }) => {
              const selected = new Set(field.value);

              const toggle = (id: string) => {
                const next = new Set(selected);
                if (next.has(id)) {
                  next.delete(id);
                } else {
                  next.add(id);
                }
                field.onChange([...next]);
              };

              return (
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                      الدول المؤهلة
                    </label>
                    <span className="text-[11px] text-slate-500">
                      {selected.size} من {countries.data?.length ?? 0}
                    </span>
                  </div>

                  <div className="relative">
                    <Search className="absolute w-3.5 h-3.5 -translate-y-1/2 right-3 top-1/2 text-slate-400" />
                    <Input
                      placeholder="ابحث باسم الدولة أو رمزها"
                      value={countrySearch}
                      onChange={(e) => setCountrySearch(e.target.value)}
                      className="pr-9"
                    />
                  </div>

                  <div className="p-2 space-y-1 overflow-y-auto border max-h-56 rounded-xl border-slate-200 dark:border-slate-800">
                    {filteredCountries.length === 0 ? (
                      <p className="p-3 text-xs text-center text-slate-500">
                        {(countries.data?.length ?? 0) === 0
                          ? 'لا توجد دول مسجلة في النظام بعد.'
                          : 'لا توجد نتائج مطابقة للبحث.'}
                      </p>
                    ) : (
                      filteredCountries.map((country) => (
                        <label
                          key={country.id}
                          className="flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                          <input
                            type="checkbox"
                            checked={selected.has(country.id)}
                            onChange={() => toggle(country.id)}
                            className="w-3.5 h-3.5 accent-brand-500"
                          />
                          <span className="text-xs text-slate-700 dark:text-slate-200">{country.name}</span>
                          <span className="font-mono text-[11px] text-slate-400">{country.iso2}</span>
                        </label>
                      ))
                    )}
                  </div>

                  {errors.country_ids?.message && (
                    <p className="text-[11px] text-rose-500">{errors.country_ids.message}</p>
                  )}
                </div>
              );
            }}
          />

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <Button variant="secondary" size="sm" type="button" onClick={onClose} disabled={updateRules.isPending}>
              إلغاء
            </Button>
            <Button variant="primary" size="sm" type="submit" isLoading={updateRules.isPending}>
              حفظ القواعد
            </Button>
          </div>
        </form>
      )}
    </Dialog>
  );
}
