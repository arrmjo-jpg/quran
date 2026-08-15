/**
 * Derived from the API, not guessed: these mirror
 * Modules/Competition/Presentation/HTTP/Resources/SeasonResource.php
 * field for field. Competition API v2 is frozen, so it is the contract —
 * when something here disagrees with it, this file is what changes.
 *
 * Note these are the *admin* shapes. The public routes return
 * PublicSeasonResource, which deliberately withholds the judging
 * configuration and every archive field, so this type must not be used
 * for anything read from /seasons.
 */

export type SeasonStatus =
  | 'draft'
  | 'registration_open'
  | 'registration_closed'
  | 'competition_running'
  | 'judging'
  | 'completed'
  | 'archived';

export type Locale = 'ar' | 'en' | 'es';

export interface SeasonTranslation {
  title:             string;
  public_name:       string | null;
  public_short_name: string | null;
  description:       string | null;
}

export interface Season {
  id:                    string;
  slug:                  string;
  year:                  number;
  /** Display title for the active locale, falling back to en, then the slug. */
  title:                 string;
  status:                SeasonStatus;
  is_active:             boolean;
  /** Once frozen, the API rejects edits to rules, translations and stages with a 409. */
  is_frozen:             boolean;
  min_age:               number | null;
  max_age:               number | null;
  participation_type_id: string | null;
  tajweed_level_id:      string | null;
  /**
   * Ids only — the names come from the countries catalog. Always present,
   * so [] genuinely means "no eligible countries" rather than "not loaded".
   * PATCH .../rules replaces the whole set, so this is what an edit form
   * must send back for anything it wants kept.
   */
  country_ids:           string[];
  registration_start:    string;
  registration_end:      string;
  start_date:            string;
  end_date:              string;
  frozen_at:             string | null;
  archived_at:           string | null;
  archived_by_user_id:   string | null;
  archive_reason:        string | null;
  translations:          Partial<Record<Locale, SeasonTranslation>>;
}

/** POST /admin/seasons — every locale's title and public_name are required. */
export interface CreateSeasonPayload {
  slug:               string;
  year:               number;
  registration_start: string;
  registration_end:   string;
  start_date:         string;
  end_date:           string;
  title_ar:           string;
  title_en:           string;
  title_es:           string;
  public_name_ar:     string;
  public_name_en:     string;
  public_name_es:     string;
}

/**
 * PATCH /admin/seasons/{id} — same field set as create; the endpoint backs
 * a fully populated edit form rather than a partial patch. The season's
 * rules (age range, participation type, tajweed level, countries) are a
 * separate endpoint and deliberately not part of this payload.
 */
export type UpdateSeasonPayload = CreateSeasonPayload;

/** POST /admin/seasons/{id}/archive — reason is optional. */
export interface ArchiveSeasonPayload {
  reason?: string;
}

/** POST /admin/seasons/{id}/cancel — reason is required by the API. */
export interface CancelSeasonPayload {
  reason: string;
}

/** PATCH /admin/seasons/{id}/rules — a full replace, every field required. */
export interface UpdateSeasonRulesPayload {
  min_age:               number;
  max_age:               number;
  participation_type_id: string;
  tajweed_level_id:      string;
  country_ids:           string[];
}

/**
 * One entry of an admin catalog — participation types, tajweed levels,
 * judge score systems. Mirrors LookupOptionResource; max_score is only
 * present on judge score systems.
 */
export interface LookupOption {
  id:            string;
  code:          string;
  name:          Partial<Record<Locale, string>>;
  display_order: number | null;
  max_score?:    number;
}

/**
 * Mirrors CountryResource, which returns a single already-localised `name`
 * chosen from the Accept-Language header — not a per-locale map, and not
 * the name_ar/name_en pair the shared countries feature's type claims.
 * Declared locally so this screen reads the real contract without
 * refactoring that module.
 */
export interface CountryOption {
  id:        string;
  iso2:      string;
  iso3:      string;
  name:      string;
  is_active: boolean;
}

export interface SeasonFilters {
  status?: SeasonStatus;
  year?:   number;
  search?: string;
}
