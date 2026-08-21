import { z } from 'zod';
import type { TFunction } from 'i18next';
import { SOCIAL_PLATFORMS } from '../types';

/**
 * Built with `t` rather than imported as a constant: zod bakes its messages in
 * at construction, so a schema created once at module load would keep the
 * language that happened to be active then.
 *
 * WHAT THIS DELIBERATELY DOES NOT CHECK: which host each platform's link must
 * point at. Those rules live in the SocialLinks value object on the server,
 * which is the single place that knows the set — restating them here would
 * create a second copy, and the copy in a form is the one that goes stale when
 * a platform is added. The server refuses with a message naming the platform
 * and the expected host, and the mutation surfaces it.
 *
 * What it does check is the shape a client can be sure of: a non-empty entry
 * has to look like a URL at all, so an obvious typo is caught before a round
 * trip rather than after one.
 */
export function makeProfileSchema(t: TFunction) {
  const link = z
    .string()
    .trim()
    .optional()
    .refine(
      (value) => value === undefined || value === '' || /^https?:\/\/.+/i.test(value),
      t('error_link_shape')
    );

  return z.object({
    name: z.string().trim().min(1, t('error_name_required')).max(255, t('error_name_long')),
    display_name: z.string().trim().max(255, t('error_display_name_long')).optional(),
    bio: z.string().trim().max(1000, t('error_bio_long')).optional(),
    social_links: z.object(
      Object.fromEntries(SOCIAL_PLATFORMS.map((platform) => [platform, link])) as Record<
        (typeof SOCIAL_PLATFORMS)[number],
        typeof link
      >
    ),
  });
}

export type ProfileFormValues = z.input<ReturnType<typeof makeProfileSchema>>;
