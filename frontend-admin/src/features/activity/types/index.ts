/**
 * The activity log — ADR-017 D3.
 *
 * These types follow the ADR's contract, not the shape the old
 * `AuditExplorerPage` invented. That screen declared `user_name`, `user_role`
 * and `result` for an endpoint that never existed, and two of the three are
 * deliberately absent from the real one:
 *
 *   `result` belongs to HTTP, where a request can fail. A domain event that
 *   has been dispatched has already happened — there is no unsuccessful one.
 *   Failures live in `audit_logs`, joined by `correlation_id`.
 *
 *   `user_role` would be a role captured at write time, which stops being true
 *   the moment the role changes.
 */

/** `system` means a console command or scheduled job, not a missing record. */
export type ActorType = 'user' | 'system';

export interface ActivityLogEntry {
  id:          string;
  /** The event's own TYPE, e.g. `contestant_updated`. */
  action:      string;
  entity_type: string;
  entity_id:   string;
  /** Null whenever `actor_type` is `system`. */
  actor_id:    string | null;
  actor_type:  ActorType;
  /** The domain event's payload, verbatim. */
  payload:     Record<string, unknown>;
  /** Joins this row to the `audit_logs` row for the same request. Null off-request. */
  correlation_id: string | null;
  /** When the thing happened. */
  occurred_at: string;
  /** When it was written down — not the same fact. */
  recorded_at: string;
}

export interface ActivityLogFilters {
  page:            number;
  per_page:        number;
  entity_type?:    string;
  entity_id?:      string;
  actor_id?:       string;
  action?:         string;
  correlation_id?: string;
  from?:           string;
  to?:             string;
}

export interface ActivityLogResult {
  entries:  ActivityLogEntry[];
  /**
   * actor id => display name, resolved live for the page.
   *
   * A sibling of the rows rather than a field on each, because the name is not
   * stored: keeping it out of the table is what stops it going stale, and
   * twenty rows by one person would otherwise repeat it twenty times.
   */
  actors:   Record<string, string>;
  total:    number;
  lastPage: number;
}
