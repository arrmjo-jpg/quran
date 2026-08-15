/**
 * Derived from the API, not guessed: mirrors
 * Modules/Competition/Presentation/HTTP/Resources/StageResource.php and the
 * Create/Update/Reorder form requests. Competition API v2 is frozen, so it
 * is the contract — when something here disagrees with it, this file is
 * what changes.
 */

/** Stage::TYPES on the aggregate; the API rejects anything else. */
export type StageType = 'preliminary' | 'semi_final' | 'final';

/** Set by the aggregate as the competition progresses, not by this UI. */
export type StageStatus = 'pending' | 'active' | 'completed';

export type Locale = 'ar' | 'en' | 'es';

export interface StageTranslation {
  name:         string;
  public_name:  string | null;
  description:  string | null;
}

export interface Stage {
  id:                     string;
  season_id:              string;
  /**
   * Server-assigned on create (max + 1) and never accepted from a client.
   * Only the reorder endpoint changes it.
   */
  stage_number:           number;
  type:                   StageType;
  /** Display name for the active locale, falling back to en. */
  name:                   string | null;
  status:                 StageStatus;
  start_date:             string;
  end_date:               string;
  evaluation_template_id: string | null;
  translations:           Partial<Record<Locale, StageTranslation>>;
}

/** One locale's content as the create/update endpoints accept it. */
export interface StageTranslationPayload {
  name:         string;
  public_name?: string | null;
  description?: string | null;
}

/**
 * POST /admin/seasons/{seasonId}/stages — note the absence of
 * stage_number: it is assigned by the server, and sending it would be
 * ignored. Translations are required on create.
 */
export interface CreateStagePayload {
  type:                    StageType;
  start_date:              string;
  end_date:                string;
  evaluation_template_id?: string | null;
  translations:            Partial<Record<Locale, StageTranslationPayload>>;
}

/**
 * PATCH /admin/stages/{id} — cannot move a stage's position; that is the
 * reorder endpoint's sole job. Translations are optional and merged, so
 * omitting a locale leaves it untouched.
 */
export interface UpdateStagePayload {
  type:                    StageType;
  start_date:              string;
  end_date:                string;
  evaluation_template_id?: string | null;
  translations?:           Partial<Record<Locale, StageTranslationPayload>>;
}

/**
 * PUT /admin/seasons/{seasonId}/stages/order — must list every stage of
 * the season exactly once. A partial ordering is rejected with 422
 * INVALID_STAGE_ORDER, because there is no single correct reading of where
 * everything else should land.
 */
export interface ReorderStagesPayload {
  stage_ids: string[];
}
