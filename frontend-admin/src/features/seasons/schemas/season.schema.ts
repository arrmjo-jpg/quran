import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * Mirrors CreateSeasonRequest/UpdateSeasonRequest on the backend, including
 * their date ordering rules — the API enforces
 * registration_start < registration_end <= start_date < end_date, which is
 * the shape the chk_seasons_start_after_registration constraint requires.
 * Checking it here too means the admin sees a field-level message instead
 * of a 422 after a round trip.
 *
 * A factory rather than a module-level constant: zod resolves its messages
 * when the schema is built, so a constant would freeze them at whatever
 * language was active on import and keep showing it after a switch. Taking
 * `t` and rebuilding under useMemo keeps validation in step with the rest
 * of the interface. This is the pattern for every schema in the project.
 */
export function makeSeasonSchema(t: TFunction<'seasons'>) {
  return z
    .object({
      year: z.coerce
        .number()
        .min(2020, t('v_year_min'))
        .max(2100, t('v_year_max')),
      slug: z
        .string()
        .min(3, t('v_slug_min'))
        .max(100, t('v_slug_max'))
        .regex(/^[a-z0-9-]+$/, t('v_slug_pattern')),
      registration_start: z.string().min(1, t('v_reg_start_required')),
      registration_end: z.string().min(1, t('v_reg_end_required')),
      start_date: z.string().min(1, t('v_start_date_required')),
      end_date: z.string().min(1, t('v_end_date_required')),
      title_ar: z.string().min(1, t('v_title_ar_required')).max(255, t('v_title_max')),
      title_en: z.string().min(1, t('v_title_en_required')).max(255, t('v_title_max')),
      title_es: z.string().min(1, t('v_title_es_required')).max(255, t('v_title_max')),
      public_name_ar: z.string().min(1, t('v_public_name_ar_required')).max(255, t('v_public_name_max')),
      public_name_en: z.string().min(1, t('v_public_name_en_required')).max(255, t('v_public_name_max')),
      public_name_es: z.string().min(1, t('v_public_name_es_required')).max(255, t('v_public_name_max')),
    })
    .refine((v) => new Date(v.registration_end) > new Date(v.registration_start), {
      message: t('v_reg_end_after_start'),
      path: ['registration_end'],
    })
    .refine((v) => new Date(v.start_date) >= new Date(v.registration_end), {
      message: t('v_start_after_reg_end'),
      path: ['start_date'],
    })
    .refine((v) => new Date(v.end_date) > new Date(v.start_date), {
      message: t('v_end_after_start'),
      path: ['end_date'],
    });
}

export type SeasonFormValues = z.infer<ReturnType<typeof makeSeasonSchema>>;
