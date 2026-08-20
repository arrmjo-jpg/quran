/**
 * Mirrors CircleResource — ADR-016 D6, D7, D9.
 *
 * A circle has NO location of its own. Country, city, address and coordinates
 * belong to its centre and are read through `center` (Q3), so nothing here
 * duplicates them — and nothing should be added that does, because the copy
 * would go stale the first time a centre moved.
 */
export interface CircleCenter {
  id:         string;
  name:       string;
  city:       string;
  country_id: string;
}

export interface Circle {
  id:        string;
  name:      string;
  center_id: string;
  /**
   * Present only when the server eager loaded it, which the list endpoint
   * always does. Typed as optional because the resource omits the key
   * entirely rather than sending null when the relation was not loaded.
   */
  center?:   CircleCenter | null;
  /**
   * A user id and nothing more. D9 makes a supervisor a User rather than a
   * third kind of identity, and the resource carries no name — see
   * CirclesPage for how the list renders one.
   */
  supervisor_user_id: string | null;
  created_at:         string | null;
}

export interface CircleListFilters {
  page:                number;
  per_page:            number;
  search?:             string;
  center_id?:          string;
  supervisor_user_id?: string;
}

export interface CircleListResult {
  circles: Circle[];
  total:   number;
}

export interface CreateCirclePayload {
  name:                string;
  center_id:           string;
  supervisor_user_id?: string | null;
}

/**
 * No center_id, and its absence is the contract. A circle cannot move between
 * centres — its location IS its centre's under Q3, so moving one would
 * silently relocate every record that reads through it. SaveCircleRequest
 * marks the field `prohibited` on update, so sending it is a 422.
 */
export interface UpdateCirclePayload {
  id:                  string;
  name:                string;
  supervisor_user_id?: string | null;
}
