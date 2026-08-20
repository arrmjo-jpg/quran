import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * Built with `t` rather than imported as a constant: zod bakes its messages in
 * at construction, so a schema created once at module load would keep the
 * language that happened to be active then.
 */
export function makeCircleSchema(t: TFunction) {
  return z.object({
    name: z.string().trim().min(1, t('error_name_required')).max(255, t('error_name_long')),
    center_id: z.string().uuid(t('error_center_required')),

    // An empty string is how a native select says "nobody", and it must reach
    // the API as null rather than as '' — the endpoint validates the field as
    // a uuid when present, so '' would be a 422 instead of a withdrawal.
    supervisor_user_id: z
      .string()
      .optional()
      .transform((v) => (v === '' || v === undefined ? null : v)),
  });
}

export type CircleFormValues = z.input<ReturnType<typeof makeCircleSchema>>;
export type CircleFormOutput = z.output<ReturnType<typeof makeCircleSchema>>;
