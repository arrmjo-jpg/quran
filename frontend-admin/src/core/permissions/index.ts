/**
 * Every permission the server defines — ADR-015 §4.3.
 *
 * A verbatim mirror of PermissionCatalog.php. Not a translation layer and
 * not a set of aliases: a name here that the server does not define is a
 * check that can never pass, and the previous version of this file was
 * exactly that — it used a `manage` vocabulary (seasons.manage,
 * streaming.manage, media.upload) that the catalogue had removed, so not
 * one of its nineteen constants matched a real permission.
 *
 * Kept in catalogue order and grouped by resource so a diff against the
 * PHP file is readable.
 */
export const PERMISSIONS = [
  // users
  'users.view',
  'users.create',
  'users.update',
  'users.delete',
  'users.restore',
  // roles
  'roles.view',
  'roles.create',
  'roles.update',
  'roles.delete',
  // permissions
  'permissions.view',
  // seasons
  'seasons.view',
  'seasons.create',
  'seasons.update',
  'seasons.archive',
  'seasons.restore',
  'seasons.cancel',
  'seasons.open_registration',
  'seasons.close_registration',
  'seasons.reopen_registration',
  // season_rules
  'season_rules.view',
  'season_rules.update',
  // stages
  'stages.view',
  'stages.create',
  'stages.update',
  'stages.delete',
  'stages.preview_results',
  'stages.calculate_results',
  'stages.publish_results',
  'stages.reopen_results',
  'stages.simulate_ranking',
  // countries
  'countries.view',
  'countries.create',
  'countries.activate',
  'countries.deactivate',
  // lookups
  'lookups.view',
  // contestants
  'contestants.view',
  'contestants.update',
  // judges
  'judges.view',
  'judges.create',
  // judge_assignments
  'judge_assignments.view',
  'judge_assignments.create',
  'judge_assignments.delete',
  // applications
  'applications.view',
  'applications.ready_for_judging',
  'applications.request_reupload',
  // evaluations
  'evaluations.view',
  'evaluations.start',
  'evaluations.save_draft',
  'evaluations.submit',
  // appeals
  'appeals.view',
  'appeals.accept',
  'appeals.reject',
  // media
  'media.view',
  'media.create',
  'media.update',
  'media.delete',
  'media.reprocess',
  // videos
  'videos.view',
  'videos.delete',
  'videos.reprocess',
  // streaming
  'streaming.view',
  'streaming.create',
  'streaming.start',
  'streaming.stop',
  'streaming.delete',
  // content
  'content.view',
  'content.create',
  'content.delete',
  'content.publish',
  // sponsors
  'sponsors.view',
  'sponsors.create',
  'sponsors.delete',
  // notifications
  'notifications.view',
  'notifications.retry',
  // reports
  'reports.view',
  'reports.export',
  // search
  'search.view',
  'search.reindex',
  // audit
  'audit.view',
  // settings
  'settings.view',
  'settings.update',
] as const;

/** A permission name the server would recognise. */
export type PermissionKey = (typeof PERMISSIONS)[number];
