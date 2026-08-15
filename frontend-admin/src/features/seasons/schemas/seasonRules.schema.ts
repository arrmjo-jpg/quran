import { z } from 'zod';

/**
 * Mirrors UpdateSeasonRulesRequest on the backend: ages 1..120 with
 * max >= min, both lookups required, and at least one eligible country —
 * a season nobody can enter is not a valid configuration.
 */
export const seasonRulesSchema = z
  .object({
    min_age: z.coerce
      .number()
      .int('العمر يجب أن يكون رقماً صحيحاً')
      .min(1, 'الحد الأدنى للعمر يجب أن يكون 1 على الأقل')
      .max(120, 'الحد الأدنى للعمر غير منطقي'),
    max_age: z.coerce
      .number()
      .int('العمر يجب أن يكون رقماً صحيحاً')
      .min(1, 'الحد الأعلى للعمر يجب أن يكون 1 على الأقل')
      .max(120, 'الحد الأعلى للعمر غير منطقي'),
    participation_type_id: z.string().min(1, 'يرجى اختيار نوع المشاركة'),
    tajweed_level_id: z.string().min(1, 'يرجى اختيار مستوى التجويد'),
    country_ids: z.array(z.string()).min(1, 'يجب اختيار دولة مؤهلة واحدة على الأقل'),
  })
  .refine((v) => v.max_age >= v.min_age, {
    message: 'الحد الأعلى للعمر يجب أن يكون مساوياً أو أكبر من الحد الأدنى',
    path: ['max_age'],
  });

export type SeasonRulesFormValues = z.infer<typeof seasonRulesSchema>;
