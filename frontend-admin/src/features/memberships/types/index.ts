/**
 * Mirrors MembershipResource — ADR-016 Q4.
 *
 * Membership is a table, not a column on the contestant. A `contestants.circle_id`
 * would hold only the present and lose every transfer anyone ever made, and D8
 * exists precisely because that history matters.
 */
export interface MembershipCircle {
  id:        string;
  name:      string;
  center_id: string;
}

export interface Membership {
  id:            string;
  contestant_id: string;
  circle_id:     string;
  /** Present only when the server eager loaded it, which the list always does. */
  circle?:       MembershipCircle | null;
  joined_at:     string | null;
  left_at:       string | null;
  /**
   * Derived on the server rather than recomputed here from `left_at === null`.
   * The same condition the unique index uses, named once — three client-side
   * copies of it would eventually disagree.
   */
  is_active:     boolean;
  reason:        string | null;
  created_at:    string | null;
}

export interface MembershipListFilters {
  page:           number;
  per_page:       number;
  contestant_id?: string;
  circle_id?:     string;
  /**
   * Omitted means every row, open and closed. The endpoint defaults to the
   * full history on purpose: a history that hides its closed rows by default
   * is a history nobody sees.
   */
  active?:        boolean;
}

export interface MembershipListResult {
  memberships: Membership[];
  total:       number;
}

export interface StartMembershipPayload {
  contestant_id: string;
  circle_id:     string;
  /** Defaults to now on the server. Cannot be in the future. */
  joined_at?:    string | null;
}

export interface EndMembershipPayload {
  id:       string;
  /** Required — a membership that ended for no recorded reason is not history. */
  reason:   string;
  left_at?: string | null;
}

/**
 * A transfer is one act, not an end followed by a start.
 *
 * It carries its own permission for that reason: an operator trusted to enrol
 * a newcomer is not automatically trusted to move someone out of another
 * supervisor's circle.
 */
export interface TransferMembershipPayload {
  contestant_id: string;
  to_circle_id:  string;
  reason:        string;
  at?:           string | null;
}
