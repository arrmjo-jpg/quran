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

/**
 * A stored photo, resolved to something renderable — ADR-016 D24.
 *
 * `url` and `thumb` are both nullable and for different reasons. A private
 * disk issues presigned URLs on demand and has no permanent address to give;
 * a thumbnail exists only if one was generated. A component must fall back
 * rather than assume either.
 */
export interface ResolvedPhoto {
  id:        string;
  url:       string | null;
  thumb:     string | null;
  mime_type: string;
  is_image:  boolean;
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
 * Identity 360 — Epic 4 Story 2 (ADR-016 D19).
 *
 * A relationship view, which is why `contestant` here is the LIST shape: it
 * carries no `national_id`. An identity document describes a person and
 * links nothing, so it stayed on GET /admin/contestants/{id} where a
 * deliberate act reaches it. `ContestantIdentity` therefore cannot be used
 * to render one, by construction rather than by discipline.
 *
 * The `ContestantProfile` type that used to live here declared optional
 * `applications` and `appeals` arrays that no endpoint has ever filled. They
 * are gone rather than left optional: an optional field that is always
 * absent is an invitation to render a tab that will always be empty.
 */

/** The four fields ADR-016 D18 admits from the account. There is no fifth. */
export interface IdentityAccount {
  id:     string;
  name:   string;
  status: 'active' | 'pending_activation' | 'deactivated' | 'deleted';
  type:   'admin' | 'contestant';
}

export interface IdentityCountry {
  id:   string;
  iso2: string;
  /** Already resolved to the requested language by the server. */
  name: string;
}

export interface IdentityMembership {
  id:          string;
  circle_id:   string;
  /** Null when the circle has since been deleted — the period still happened. */
  circle_name: string | null;
  center_id:   string | null;
  center_name: string | null;
  center_city: string | null;
  joined_at:   string | null;
  left_at:     string | null;
  is_active:   boolean;
  reason:      string | null;
}

/**
 * `withheld` names each branch suppressed because the reader lacks the
 * permission governing it (D20). Without it an empty `memberships` array
 * would tell an operator this person has never belonged to a circle, when
 * the truth is that they may not be told either way.
 */
export type IdentityBranch = 'memberships';

/**
 * The contestant as the identity endpoint returns them — Story 4 widened
 * this beyond the list shape (ADR-016 D24).
 *
 * `photo_media_asset_id` stays alongside the resolved `photo`: it was in the
 * contract before Story 4 and removing it would break a consumer to add a
 * convenience.
 *
 * `age` arrives calculated. It is not derived here, and not in a component —
 * BirthDate owns that arithmetic and the server hands over the answer.
 */
export interface IdentityContestant extends ContestantListItem {
  age:   number;
  photo: ResolvedPhoto | null;
}

export interface ContestantIdentity {
  contestant:  IdentityContestant;
  user:        IdentityAccount | null;
  country:     IdentityCountry | null;
  memberships: IdentityMembership[];
  withheld:    IdentityBranch[];
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
