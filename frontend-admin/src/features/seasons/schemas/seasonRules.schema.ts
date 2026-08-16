import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * Mirrors UpdateSeasonRulesRequest on the backend: ages 1..120 with
 * max >= min, both lookups required, and at least one eligible country —
 * a season nobody can enter is not a valid configuration.
 *
 * A factory for the same reason as makeSeasonSchema: zod bakes its messages
 * in at build time, so a module-level constant would pin them to the boot
 * language.
 */
export function makeSeasonRulesSchema(t: TFunction<'seasons'>) {
  return z
    .object({
      min_age: z.coerce
        .number()
        .int(t('v_age_int'))
        .min(1, t('v_min_age_min'))
        .max(120, t('v_min_age_max')),
      max_age: z.coerce
        .number()
        .int(t('v_age_int'))
        .min(1, t('v_max_age_min'))
        .max(120, t('v_max_age_max')),
      participation_type_id: z.string().min(1, t('v_participation_type_required')),
      tajweed_level_id: z.string().min(1, t('v_tajweed_level_required')),
      country_ids: z.array(z.string()).min(1, t('v_countries_min')),
    })
    .refine((v) => v.max_age >= v.min_age, {
      message: t('v_max_age_gte_min'),
      path: ['max_age'],
    });
}

export type SeasonRulesFormValues = z.infer<ReturnType<typeof makeSeasonRulesSchema>>;
