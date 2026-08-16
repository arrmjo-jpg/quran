import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * Mirrors CreateStageRequest/UpdateStageRequest. stage_number is absent on
 * purpose — the server assigns it on create and only the reorder endpoint
 * ever changes it, so there is nothing here for the admin to type.
 *
 * The Arabic name is the only required translation in this form: the API
 * requires at least one, and Stage::assertTranslationsComplete() (which
 * demands all three) only runs when the season freezes, not on every save.
 * Leaving en/es optional lets an admin draft a stage now and finish the
 * translations before opening registration.
 *
 * A factory, like every schema in the project: zod resolves its messages
 * when the schema is built, so a module-level constant would pin them to
 * whatever language was active at import time.
 */
export function makeStageSchema(t: TFunction<'stages'>) {
  return z
    .object({
      type: z.enum(['preliminary', 'semi_final', 'final'], {
        errorMap: () => ({ message: t('v_type_required') }),
      }),
      start_date: z.string().min(1, t('v_start_date_required')),
      end_date: z.string().min(1, t('v_end_date_required')),

      name_ar: z.string().min(1, t('v_name_ar_required')).max(255, t('v_name_max')),
      name_en: z.string().max(255, t('v_name_max')).optional().or(z.literal('')),
      name_es: z.string().max(255, t('v_name_max')).optional().or(z.literal('')),

      public_name_ar: z.string().max(255, t('v_name_max')).optional().or(z.literal('')),
      public_name_en: z.string().max(255, t('v_name_max')).optional().or(z.literal('')),
      public_name_es: z.string().max(255, t('v_name_max')).optional().or(z.literal('')),
    })
    .refine((v) => new Date(v.end_date) > new Date(v.start_date), {
      message: t('v_end_after_start'),
      path: ['end_date'],
    });
}

export type StageFormValues = z.infer<ReturnType<typeof makeStageSchema>>;
