/**
 * Epic 4 Story 1 gave contestants a management API. These types follow the
 * server's two resources exactly:
 *
 *   ContestantListResource    the list — no national_id
 *   ContestantPrivateResource the detail — national_id included
 *
 * The split is deliberate and is asserted on both sides. A list is a
 * browsing surface and paging through it would otherwise collect every
 * identity document the platform holds; opening one contestant is a
 * deliberate act. `ContestantListItem` therefore has no `national_id` field
 * at all, so reaching for one in a table cell is a compile error rather than
 * an `undefined`.
 */

export interface ProfileCompleteness {
  completeness_percent: number;
  is_complete:          boolean;
  missing_fields:       string[];
}

/** A row in the contestants table. */
export interface ContestantListItem {
  id:                    string;
  user_id:               string;
  country_id:            string;
  full_name:             string;
  date_of_birth:         string;
  gender:                'male' | 'female';
  phone_number:          string;
  photo_media_asset_id:  string | null;
  is_deleted:            boolean;
  profile_completeness:  ProfileCompleteness;
}

/** One contestant, opened deliberately. */
export interface ContestantDetail extends ContestantListItem {
  national_id: string | null;
}

/**
 * Retained for the 360 drawer, which still renders applications and appeals
 * tabs the server does not populate. That gap belongs to Identity 360
 * (Epic 4 Story 2) and was deliberately not fabricated here — the fields
 * stay optional so the drawer compiles while they are absent.
 */
export interface ContestantProfile extends ContestantDetail {
  created_at?: string;
  applications?: {
    id:             string;
    season_id:      string;
    stage_id:       string;
    status:         string;
    video_hls_url?: string;
    total_score?:   number;
  }[];
  appeals?: {
    id:              string;
    reason:          string;
    status:          string;
    admin_response?: string;
  }[];
}

export interface ContestantListFilters {
  page:          number;
  per_page:      number;
  search?:       string;
  country_id?:   string;
  gender?:       'male' | 'female';
  with_deleted?: boolean;
}

export interface ContestantListResult {
  contestants: ContestantListItem[];
  total:       number;
  lastPage:    number;
}

export interface CreateContestantPayload {
  user_id:           string;
  country_id:        string;
  full_name:         string;
  date_of_birth:     string;
  gender:            'male' | 'female';
  phone_number:      string;
  national_id?:      string | null;
  photo_media_id?:   string | null;
}

/**
 * user_id and country_id are absent by design — the server ignores them on
 * update, because re-pointing a contestant at another account would move a
 * person's whole history and country is frozen onto their applications
 * (ADR-016 D8). Leaving them out of the type stops a form offering an edit
 * that would silently do nothing.
 */
export interface UpdateContestantPayload {
  full_name?:      string;
  date_of_birth?:  string;
  gender?:         'male' | 'female';
  phone_number?:   string;
  national_id?:    string | null;
  photo_media_id?: string | null;
}

/** Kept for the existing drawer's props. */
export interface ContestantSearchFilters {
  query?: string;
}
