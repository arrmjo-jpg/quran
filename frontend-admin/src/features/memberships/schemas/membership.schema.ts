import { z } from 'zod';
import type { TFunction } from 'i18next';

/**
 * Built with `t` rather than imported as a constant: zod bakes its messages in
 * at construction, so a schema created once at module load would keep the
 * language that happened to be active then.
 *
 * Dates are checked against `now` here as well as by the server's
 * `before_or_equal:now`. A membership that began tomorrow is not a record of
 * anything, and a field-level error is a better answer than a 422 the form
 * cannot attach to an input.
 */
const notFuture = (t: TFunction) => (value: string): boolean =>
  value === '' || new Date(value).getTime() <= Date.now();

export function makeStartMembershipSchema(t: TFunction) {
  return z.object({
    contestant_id: z.string().uuid(t('error_contestant_required')),
    circle_id: z.string().uuid(t('error_circle_required')),
    joined_at: z
      .string()
      .optional()
      .default('')
      .refine(notFuture(t), t('error_date_future'))
      .transform((v) => (v === '' ? null : v)),
  });
}

export function makeEndMembershipSchema(t: TFunction) {
  return z.object({
    // Required, and deliberately so: a membership that ended for no recorded
    // reason tells a later reader nothing about why the roster changed.
    reason: z.string().trim().min(1, t('error_reason_required')).max(500, t('error_reason_long')),
    left_at: z
      .string()
      .optional()
      .default('')
      .refine(notFuture(t), t('error_date_future'))
      .transform((v) => (v === '' ? null : v)),
  });
}

export function makeTransferMembershipSchema(t: TFunction) {
  return z.object({
    to_circle_id: z.string().uuid(t('error_circle_required')),
    reason: z.string().trim().min(1, t('error_reason_required')).max(500, t('error_reason_long')),
    at: z
      .string()
      .optional()
      .default('')
      .refine(notFuture(t), t('error_date_future'))
      .transform((v) => (v === '' ? null : v)),
  });
}

export type StartMembershipFormValues = z.input<ReturnType<typeof makeStartMembershipSchema>>;
export type StartMembershipFormOutput = z.output<ReturnType<typeof makeStartMembershipSchema>>;
export type EndMembershipFormValues = z.input<ReturnType<typeof makeEndMembershipSchema>>;
export type EndMembershipFormOutput = z.output<ReturnType<typeof makeEndMembershipSchema>>;
export type TransferMembershipFormValues = z.input<ReturnType<typeof makeTransferMembershipSchema>>;
export type TransferMembershipFormOutput = z.output<ReturnType<typeof makeTransferMembershipSchema>>;
