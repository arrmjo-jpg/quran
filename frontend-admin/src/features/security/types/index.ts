/**
 * Account security — ADR-018.
 *
 * The login history is a read over `audit_logs` (D2), not a store of its own,
 * so these types describe rows that were written as a side effect of requests
 * the platform has been serving all along.
 */

/**
 * Derived from the HTTP status, not stored (D2).
 *
 * `failed` is the catch-all: an unrecognised status is still an attempt that
 * did not succeed, and a security screen that quietly hides attempts has the
 * one failure mode it cannot afford.
 */
export type LoginOutcome =
  | 'success'
  | 'invalid_credentials'
  | 'account_inactive'
  | 'invalid_request'
  | 'rate_limited'
  | 'failed';

export interface LoginAttempt {
  id:      string;
  /** Null when the address matched no account. The address itself is never sent. */
  user_id: string | null;
  user_type: string | null;
  outcome: LoginOutcome;
  /** Kept beside `outcome` so a reader can correlate with the API logs. */
  status:  number;
  ip:         string | null;
  user_agent: string | null;
  /** Null on every row written before the client began sending the header (D7). */
  device_id:  string | null;
  correlation_id: string | null;
  attempted_at:   string | null;
}

export interface LoginHistoryFilters {
  page:     number;
  per_page: number;
  user_id?: string;
  outcome?: LoginOutcome | '';
  ip?:      string;
  from?:    string;
  to?:      string;
}

export interface LoginHistoryResult {
  attempts: LoginAttempt[];
  /** id → name, resolved once for the page rather than repeated on every row. */
  users:    Record<string, string>;
  total:    number;
  lastPage: number;
}

/**
 * A trusted device — ADR-018 D1, D5.
 *
 * `trust_token` is deliberately absent: it is returned exactly once, by the
 * call that creates the grant, and only its hash is stored. Nothing that lists
 * devices can hand it back.
 */
export interface TrustedDevice {
  id:          string;
  user_id:     string;
  device_id:   string | null;
  ip:          string | null;
  user_agent:  string | null;
  trusted_at:  string;
  expires_at:  string;
  /** Null until the grant has actually been used to skip a challenge. */
  last_used_at: string | null;
}

/** Only ever seen in the response that creates the grant. */
export interface TrustedDeviceGrant extends TrustedDevice {
  trust_token: string;
}
