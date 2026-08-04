import { z } from 'zod';

export const countrySchema = z.object({
  iso2: z.string().length(2, 'رمز ISO2 يجب أن يتكون من حرفين بالضبط').toUpperCase(),
  iso3: z.string().length(3, 'رمز ISO3 يجب أن يتكون من 3 أحرف بالضبط').toUpperCase(),
  phone_code: z.string().min(2, 'رمز الهاتف يجب أن يبدأ بـ +').startsWith('+', 'رمز الهاتف يجب أن يبدأ بـ +'),
  name_ar: z.string().min(2, 'الاسم بالعربية مطلوب'),
  name_en: z.string().min(2, 'الاسم بالإنجليزية مطلوب'),
  flag_url: z.string().url('رابط علم غير صالح').optional().or(z.literal('')),
});

export type CountryFormValues = z.infer<typeof countrySchema>;
