import { z } from 'zod';

/**
 * Mirrors CreateSeasonRequest/UpdateSeasonRequest on the backend, including
 * their date ordering rules — the API enforces
 * registration_start < registration_end <= start_date < end_date, which is
 * the shape the chk_seasons_start_after_registration constraint requires.
 * Checking it here too means the admin sees a field-level message instead
 * of a 422 after a round trip.
 */
export const seasonSchema = z
  .object({
    year: z.coerce
      .number()
      .min(2020, 'سنة الموسم يجب أن تكون 2020 أو أحدث')
      .max(2100, 'سنة غير صالحة'),
    slug: z
      .string()
      .min(3, 'المعرف يجب أن يكون 3 أحرف على الأقل')
      .max(100, 'المعرف طويل جداً')
      .regex(/^[a-z0-9-]+$/, 'المعرف يجب أن يحوي أحرفاً صغيرة وأرقام وخطوط فقط'),
    registration_start: z.string().min(1, 'يرجى تحديد تاريخ بداية التسجيل'),
    registration_end: z.string().min(1, 'يرجى تحديد تاريخ نهاية التسجيل'),
    start_date: z.string().min(1, 'يرجى تحديد تاريخ بداية الموسم'),
    end_date: z.string().min(1, 'يرجى تحديد تاريخ نهاية الموسم'),
    title_ar: z.string().min(1, 'يرجى إدخال العنوان بالعربية').max(255, 'العنوان طويل جداً'),
    title_en: z.string().min(1, 'يرجى إدخال العنوان بالإنجليزية').max(255, 'العنوان طويل جداً'),
    title_es: z.string().min(1, 'يرجى إدخال العنوان بالإسبانية').max(255, 'العنوان طويل جداً'),
    public_name_ar: z.string().min(1, 'يرجى إدخال الاسم المعلن بالعربية').max(255, 'الاسم طويل جداً'),
    public_name_en: z.string().min(1, 'يرجى إدخال الاسم المعلن بالإنجليزية').max(255, 'الاسم طويل جداً'),
    public_name_es: z.string().min(1, 'يرجى إدخال الاسم المعلن بالإسبانية').max(255, 'الاسم طويل جداً'),
  })
  .refine((v) => new Date(v.registration_end) > new Date(v.registration_start), {
    message: 'نهاية التسجيل يجب أن تكون بعد بدايته',
    path: ['registration_end'],
  })
  .refine((v) => new Date(v.start_date) >= new Date(v.registration_end), {
    message: 'بداية الموسم يجب أن تكون بعد نهاية التسجيل أو مساوية لها',
    path: ['start_date'],
  })
  .refine((v) => new Date(v.end_date) > new Date(v.start_date), {
    message: 'نهاية الموسم يجب أن تكون بعد بدايتها',
    path: ['end_date'],
  });

export type SeasonFormValues = z.infer<typeof seasonSchema>;
