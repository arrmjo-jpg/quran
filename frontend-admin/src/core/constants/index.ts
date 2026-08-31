export const STORAGE_KEYS = {
  token:      'qp_admin_token',
  user:       'qp_admin_user',
  theme:      'qp_theme',
  language:   'qp_language',
  /** ADR-018 D7. Identifies this browser to the audit log. NOT a credential. */
  deviceId:   'qp_device_id',
  /** ADR-018 D5. The trust grant. This one IS a credential. */
  trustToken: 'qp_device_trust_token',
} as const;

/** Type-safe TanStack Query Keys Factory */
export const queryKeys = {
  auth: {
    me: () => ['auth', 'me'] as const,
  },
  seasons: {
    all:    () => ['seasons'] as const,
    detail: (id: string) => ['seasons', id] as const,
  },
  countries: {
    all: () => ['countries'] as const,
  },
  centers: {
    all:  () => ['centers'] as const,
    list: (filters: unknown) => ['centers', 'list', filters] as const,
  },
  circles: {
    all:  () => ['circles'] as const,
    list: (filters: unknown) => ['circles', 'list', filters] as const,
  },
  memberships: {
    all:  () => ['memberships'] as const,
    list: (filters: unknown) => ['memberships', 'list', filters] as const,
  },
  roles: {
    all: () => ['roles'] as const,
  },
  users: {
    all: () => ['users'] as const,
    list: (filters: unknown) => ['users', 'list', filters] as const,
    detail: (id: string) => ['users', 'detail', id] as const,
  },
  activity: {
    all:  () => ['activity'] as const,
    list: (filters: unknown) => ['activity', 'list', filters] as const,
  },
  security: {
    all:          () => ['security'] as const,
    loginHistory: (filters: unknown) => ['security', 'login-history', filters] as const,
    devices:      () => ['security', 'devices'] as const,
  },
  permissions: {
    all: () => ['permissions'] as const,
  },
  contestants: {
    all:    () => ['contestants'] as const,
    // `list` replaced `search` when the endpoint gained pagination and
    // filters: the key has to vary with every criterion, not just the query
    // string, or two different filters would share a cache entry.
    list:   (filters: unknown) => ['contestants', 'list', filters] as const,
    detail: (id: string) => ['contestants', id] as const,
    // Identity 360 is a different question about the same person and caches
    // separately: the detail response carries a national_id this one does
    // not, and one key for both would let either answer overwrite the other.
    identity: (id: string) => ['contestants', id, 'identity'] as const,
  },
  judges: {
    all:    () => ['judges'] as const,
    detail: (id: string) => ['judges', id] as const,
  },
  applications: {
    all:    () => ['applications'] as const,
    detail: (id: string) => ['applications', id] as const,
  },
  evaluations: {
    all: () => ['evaluations'] as const,
  },
  media: {
    all: () => ['media'] as const,
  },
  videos: {
    all: () => ['videos'] as const,
  },
  streams: {
    all: () => ['streams'] as const,
  },
  sponsors: {
    all: () => ['sponsors'] as const,
  },
  content: {
    announcements: () => ['content', 'announcements'] as const,
  },
  notifications: {
    all:  () => ['notifications'] as const,
    list: (filters: unknown) => ['notifications', 'list', filters] as const,
  },
  reports: {
    summary: () => ['reports', 'summary'] as const,
    exports: () => ['reports', 'exports'] as const,
  },
  search: {
    indexingLogs: () => ['search', 'indexing-logs'] as const,
    global:       (q: string) => ['search', 'global', q] as const,
  },
} as const;
