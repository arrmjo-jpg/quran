export const PERMISSIONS = {
  SEASONS_VIEW:      'seasons.view',
  SEASONS_MANAGE:    'seasons.manage',
  CONTESTANTS_VIEW:  'contestants.view',
  CONTESTANTS_MANAGE:'contestants.manage',
  JUDGES_VIEW:       'judges.view',
  JUDGES_MANAGE:     'judges.manage',
  APPLICATIONS_VIEW: 'applications.view',
  APPLICATIONS_JUDGE:'applications.judge',
  EVALUATIONS_VIEW:  'evaluations.view',
  EVALUATIONS_MANAGE:'evaluations.manage',
  MEDIA_UPLOAD:      'media.upload',
  MEDIA_MANAGE:      'media.manage',
  VIDEOS_VIEW:       'videos.view',
  STREAMING_MANAGE:  'streaming.manage',
  SPONSORS_MANAGE:   'sponsors.manage',
  CONTENT_MANAGE:    'content.manage',
  REPORTS_VIEW:      'reports.view',
  REPORTS_EXPORT:    'reports.export',
  SEARCH_REINDEX:    'search.reindex',
} as const;

export type PermissionKey = keyof typeof PERMISSIONS;
