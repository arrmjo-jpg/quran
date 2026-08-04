import { z } from 'zod';

export const seasonSchema = z.object({
  year: z.coerce.number().min(2020, 'سنة الموسم يجب أن تكون 2020 أو أحدث').max(2100, 'سنة غير صالحة'),
  slug: z.string().min(3, 'المعرف يجب أن يكون 3 أحرف على الأقل').regex(/^[a-z0-9-]+$/, 'المعرف يجب أن يحوي أحرفاً صغيرة وأرقام وخطوط فقط'),
  registration_start: z.string().min(1, 'يرجى تحديد تاريخ بداية التسجيل'),
  registration_end: z.string().min(1, 'يرجى تحديد تاريخ نهاية التسجيل'),
  start_date: z.string().optional(),
  end_date: z.string().optional(),
});

export type SeasonFormValues = z.infer<typeof seasonSchema>;
