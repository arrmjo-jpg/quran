export const STORAGE_KEYS = {
  token:     'qp_admin_token',
  user:      'qp_admin_user',
  theme:     'qp_theme',
  language:  'qp_language',
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
  contestants: {
    all:    () => ['contestants'] as const,
    search: (q: string) => ['contestants', 'search', q] as const,
    detail: (id: string) => ['contestants', id] as const,
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
    all: () => ['notifications'] as const,
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
