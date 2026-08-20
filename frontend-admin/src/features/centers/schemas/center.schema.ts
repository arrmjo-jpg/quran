import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * Built with `t` rather than imported as a constant: zod bakes its messages in
 * at construction, so a schema created once at module load would keep the
 * language that happened to be active then.
 */
export function makeCenterSchema(t: TFunction) {
  return z
    .object({
      name: z.string().trim().min(1, t('error_name_required')).max(255, t('error_name_long')),
      country_id: z.string().uuid(t('error_country_required')),
      city: z.string().trim().min(1, t('error_city_required')).max(255, t('error_city_long')),
      address: z.string().trim().min(1, t('error_address_required')).max(500, t('error_address_long')),

      // Text in, number or null out. An emptied field must become null rather
      // than NaN, which is what `Number('')` would otherwise produce.
      latitude: z
        .union([z.string(), z.number()])
        .optional()
        .transform((v) => (v === '' || v === undefined ? null : Number(v)))
        .refine((v) => v === null || (!Number.isNaN(v) && v >= -90 && v <= 90), t('error_latitude_range')),
      longitude: z
        .union([z.string(), z.number()])
        .optional()
        .transform((v) => (v === '' || v === undefined ? null : Number(v)))
        .refine((v) => v === null || (!Number.isNaN(v) && v >= -180 && v <= 180), t('error_longitude_range')),
    })
    // Both or neither, matching the Coordinates value object on the server. A
    // latitude alone is not half a location; storing it would let a map plot
    // the centre on the prime meridian.
    .refine((v) => (v.latitude === null) === (v.longitude === null), {
      message: t('error_coordinates_pair'),
      path: ['longitude'],
    });
}

export type CenterFormValues = z.input<ReturnType<typeof makeCenterSchema>>;
export type CenterFormOutput = z.output<ReturnType<typeof makeCenterSchema>>;
