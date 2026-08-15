import { z } from 'zod';

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
 */
export const stageSchema = z
  .object({
    type: z.enum(['preliminary', 'semi_final', 'final'], {
      errorMap: () => ({ message: 'يرجى اختيار نوع المرحلة' }),
    }),
    start_date: z.string().min(1, 'يرجى تحديد تاريخ بداية المرحلة'),
    end_date: z.string().min(1, 'يرجى تحديد تاريخ نهاية المرحلة'),

    name_ar: z.string().min(1, 'يرجى إدخال اسم المرحلة بالعربية').max(255, 'الاسم طويل جداً'),
    name_en: z.string().max(255, 'الاسم طويل جداً').optional().or(z.literal('')),
    name_es: z.string().max(255, 'الاسم طويل جداً').optional().or(z.literal('')),

    public_name_ar: z.string().max(255, 'الاسم طويل جداً').optional().or(z.literal('')),
    public_name_en: z.string().max(255, 'الاسم طويل جداً').optional().or(z.literal('')),
    public_name_es: z.string().max(255, 'الاسم طويل جداً').optional().or(z.literal('')),
  })
  .refine((v) => new Date(v.end_date) > new Date(v.start_date), {
    message: 'نهاية المرحلة يجب أن تكون بعد بدايتها',
    path: ['end_date'],
  });

export type StageFormValues = z.infer<typeof stageSchema>;
