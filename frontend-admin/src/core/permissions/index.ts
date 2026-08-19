/**
 * Every permission the server defines — ADR-015 §4.3.
 *
 * GENERATED FILE. Do not edit by hand.
 *
 *     npm run generate:permissions
 *
 * Source of truth is the server's PermissionCatalog.php. This mirror exists
 * so that a permission name the server does not define is a compile error
 * rather than a control that silently never renders. It was hand-maintained
 * once and drifted completely, which is why it is generated now.
 *
 * Kept in catalogue order and grouped by resource so a diff against the PHP
 * file is readable.
 */
export const PERMISSIONS = [
  // users
  'users.view',
  'users.create',
  'users.update',
  'users.assign_roles',
  'users.activate',
  'users.deactivate',
  'users.delete',
  'users.restore',
  // roles
  'roles.view',
  'roles.create',
  'roles.update',
  'roles.grant_permissions',
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
  // centers
  'centers.view',
  'centers.create',
  'centers.update',
  'centers.delete',
  // circles
  'circles.view',
  'circles.create',
  'circles.update',
  'circles.delete',
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
