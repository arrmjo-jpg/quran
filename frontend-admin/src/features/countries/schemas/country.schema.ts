import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * A factory, like every schema in the project: zod resolves its messages when
 * the schema is built, so a module-level constant would pin them to whatever
 * language was active at import time.
 */
export function makeCountrySchema(t: TFunction<'countries'>) {
  return z.object({
    iso2: z.string().length(2, t('v_iso2_length')).toUpperCase(),
    iso3: z.string().length(3, t('v_iso3_length')).toUpperCase(),
    phone_code: z
      .string()
      .min(2, t('v_phone_code_prefix'))
      .startsWith('+', t('v_phone_code_prefix')),
    name_ar: z.string().min(2, t('v_name_ar_required')),
    name_en: z.string().min(2, t('v_name_en_required')),
    flag_url: z.string().url(t('v_flag_url_invalid')).optional().or(z.literal('')),
  });
}

export type CountryFormValues = z.infer<ReturnType<typeof makeCountrySchema>>;
