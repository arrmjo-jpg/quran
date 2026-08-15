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

/**
 * GET /admin/seasons/{seasonId}/stage-rules — mirrors StageRuleResource.
 *
 * A rule belongs to a stage by stage_id and nothing else: season_stage_rules
 * has no ordering column, and the API derives the listing order from the
 * stage's stage_number at read time. So stage_number here is presentation,
 * and stage_id is identity. Never pair a rule with a stage by array
 * position — reordering the stages would silently reassign every rule.
 */
export interface StageRule {
  stage_id:                 string;
  stage_number:             number;
  type:                     StageType;
  name:                     Partial<Record<Locale, string>>;
  judge_score_system: {
    id:        string;
    code:      string;
    name:      Partial<Record<Locale, string>>;
    max_score: number;
  };
  qualification_percentage: number | null;
  /** Derived server-side as max_score × percentage, so clients don't recompute it. */
  required_score:           number | null;
}

/** One stage's rule as the write endpoint accepts it. */
export interface StageRuleAssignment {
  stage_id:                 string;
  judge_score_system_id:    string;
  /** null is valid: a final stage may rank without eliminating anyone. */
  qualification_percentage: number | null;
}

/**
 * PATCH /admin/seasons/{seasonId}/stage-rules — a complete replace. The set
 * must cover every stage of the season exactly once; missing, duplicated or
 * foreign stages are rejected with 422. There is no per-rule add or delete
 * endpoint, by design.
 */
export interface UpdateStageRulesPayload {
  rules: StageRuleAssignment[];
}
