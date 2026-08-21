/**
 * The signed-in administrator's own account and profile.
 *
 * Mirrors UserResource, which serves both `/admin/auth/me` and `/me`. The
 * account fields sit at the top level and the `user_profiles` half nests under
 * `profile` — ADR-016 D1.
 */

/** The eight platforms Q5 closed the set to, in the order the form shows them. */
export const SOCIAL_PLATFORMS = [
  'website',
  'x',
  'linkedin',
  'facebook',
  'instagram',
  'youtube',
  'telegram',
  'tiktok',
] as const;

export type SocialPlatform = (typeof SOCIAL_PLATFORMS)[number];

/**
 * Only the platforms that are set carry a key — Q5 again. An absent platform
 * is absent, never `null`, so `Partial` is the honest type rather than a
 * record of eight nullable strings.
 */
export type SocialLinks = Partial<Record<SocialPlatform, string>>;

export interface MyProfileBlock {
  display_name: string | null;
  bio:          string | null;
  social_links: SocialLinks | null;
  /**
   * The raw media id, matching `photo_media_asset_id` on contestants. Read
   * only — Story 3 deliberately ships no way to set an avatar, because
   * uploading one needs `media.create` and self-service does not carry it.
   */
  avatar_media_id: string | null;
}

export interface MyAccount {
  id:               string;
  name:             string;
  email:            string;
  type:             string;
  roles:            string[];
  permissions:      string[];
  preferred_locale: string;
  profile:          MyProfileBlock;
}

/**
 * PATCH is partial at both levels: an omitted field is left alone, and a field
 * sent as null is cleared. Sending `profile` at all is optional, and sending
 * `social_links: {}` clears every link.
 */
export interface UpdateMyProfilePayload {
  name?:             string;
  preferred_locale?: string;
  profile?: {
    display_name?: string | null;
    bio?:          string | null;
    social_links?: SocialLinks;
  };
}
