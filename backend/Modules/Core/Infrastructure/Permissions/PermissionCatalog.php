<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Permissions;

/**
 * The permission catalogue — ADR-015 §4.3.
 *
 * Permissions are code that happens to be persisted, not admin-managed
 * data. This file is the source of truth; the seeder copies it into the
 * `permissions` table and never the reverse. There is no CRUD, no
 * endpoint and no UI for managing permissions, by decision.
 *
 * NAMING (ADR-015 §4.4): `resource.action`, both snake_case. The group
 * is the resource segment and is derived, never stored — there is no
 * PermissionGroup entity and no `permission_group_id` column.
 *
 * NO WILDCARDS AND NO COARSE VERBS: there is no `*` permission and no
 * `manage` verb. super_admin is powerful because it holds every entry
 * below by enumeration, not because it holds a magic one.
 *
 * ON THE VERB SET: ADR-015 §4.4 fixed a closed set of ten CRUD-shaped
 * verbs. Building this catalogue against the real admin API showed that
 * set does not fit the system — this platform is lifecycle- and
 * workflow-shaped, not CRUD-shaped, and roughly seventeen genuine
 * actions (opening registration, publishing results, requesting a
 * re-upload, accepting an appeal) had no honest home in it. The board
 * amended the set to name each action explicitly rather than fold them
 * into `update`, so that "may edit a season" and "may open registration
 * to the public" remain separately grantable. ALLOWED_VERBS below is the
 * amended set, and adding to it still requires an ADR amendment.
 *
 * ENFORCEMENT IS NOT WIRED YET. No Gate check consults any of these
 * names at the time of writing: authorization is still the single
 * `EnsureUserIsAdmin` surface check, and stays that way until the
 * "switch authorization on" epic. Until then ADR-015 §4.3's rule — that
 * a permission may exist only if some code path checks it — cannot be
 * enforced, because nothing checks anything. The architecture test that
 * binds this catalogue to the Gate checks in both directions lands with
 * that epic; the consistency tests that accompany this file are what can
 * be enforced today.
 */
final class PermissionCatalog
{
    /**
     * Every verb permitted in a permission name.
     *
     * @var array<int, string>
     */
    public const ALLOWED_VERBS = [
        // CRUD
        'view',
        'create',
        'update',
        'delete',
        // Lifecycle
        'archive',
        'restore',
        'cancel',
        'activate',
        'deactivate',
        // Season registration window
        'open_registration',
        'close_registration',
        'reopen_registration',
        // Stage results
        'preview_results',
        'calculate_results',
        'publish_results',
        'reopen_results',
        'simulate_ranking',
        // Application review workflow
        'ready_for_judging',
        'request_reupload',
        // Judge scoring workflow (the /judge surface)
        'start',
        'save_draft',
        'submit',
        // Live streaming control. 'start' is shared with the scoring
        // workflow above — the verb set is global, and a verb meaning
        // "begin this thing" reads correctly for both.
        'stop',
        // Identity. Editing what a role or an account can DO is separated
        // from editing what it is called: renaming is cosmetic, while
        // granting is the escalation surface PE-1/PE-2 guard. One
        // permission covering both would let whoever may tidy a label also
        // hand out capability.
        'grant_permissions',
        'assign_roles',
        // Membership lifecycle (ADR-015 §4.4, amended 2026-08-20). 'end'
        // rather than 'delete' or 'cancel': ADR-016 Q4 made membership
        // historical, so a membership finishes by acquiring left_at and the
        // row stays. 'transfer' rather than create+end: moving a contestant
        // out of another supervisor's circle is a different trust from
        // enrolling a newcomer, and the two cannot be granted apart if the
        // act is expressed as both.
        'end',
        'transfer',
        // Appeal decisions
        'accept',
        'reject',
        // Content
        'publish',
        // Operational
        'reprocess',
        'retry',
        'reindex',
        'export',
    ];

    /**
     * resource => actions.
     *
     * Grounded in the admin API surface that exists today, plus the
     * identity resources the Identity & Access epics build. Entries
     * marked NO ENDPOINT YET describe a capability the platform intends
     * and the capability matrix in ADR-015 §7.3 already grants, but
     * whose route has not been built — they are listed so the roles UI
     * and the seeder are complete, and each becomes enforceable when its
     * endpoint lands.
     *
     * @var array<string, array<int, string>>
     */
    private const CATALOG = [
        // ── Identity ────────────────────────────────────────────────
        // NO ENDPOINT YET — built across the Identity & Access epics.
        'users' => ['view', 'create', 'update', 'assign_roles', 'activate', 'deactivate', 'delete', 'restore'],
        'roles' => ['view', 'create', 'update', 'grant_permissions', 'delete'],
        'permissions' => ['view'],

        // ── Competition ─────────────────────────────────────────────
        'seasons' => [
            'view',
            'create',
            'update',
            'archive',
            'restore',
            'cancel',
            'open_registration',
            'close_registration',
            'reopen_registration',
        ],
        'season_rules' => ['view', 'update'],
        'stages' => [
            'view',
            'create',
            'update',
            'delete',
            'preview_results',
            'calculate_results',
            'publish_results',
            'reopen_results',
            'simulate_ranking',
        ],

        // ── Reference data ──────────────────────────────────────────
        'countries' => ['view', 'create', 'activate', 'deactivate'],
        // Read-only catalogues behind the season and stage rule pickers
        // (participation types, tajweed levels, judge score systems).
        // Read-only by design: they are seeded reference data with no
        // write endpoint, so one view permission covers the resource.
        'lookups' => ['view'],

        // ── Organisation ────────────────────────────────────────────
        // Centres, circles and the memberships that link contestants to
        // them. Separate from contestants.* because managing where people
        // study is not managing the people: a data-entry operator who may
        // correct a contestant's phone number has no business relocating a
        // centre, and the reverse is just as true.
        'centers' => ['view', 'create', 'update', 'delete'],
        'circles' => ['view', 'create', 'update', 'delete'],
        // memberships.end rather than .delete: ending a membership
        // removes nothing (Q4 made it historical), and .transfer is
        // separate from create+end because moving someone out of
        // another supervisor's circle is not implied by being trusted
        // to enrol a newcomer.
        'memberships' => ['view', 'create', 'end', 'transfer'],

        // ── People ──────────────────────────────────────────────────
        // contestants.update: NO ENDPOINT YET (granted to data_entry in
        // the ADR-015 §7.3 matrix).
        'contestants' => ['view', 'update'],
        'judges' => ['view', 'create'],
        // NO ENDPOINT YET — the judge_assignments table has no route at
        // all; its epic builds them.
        'judge_assignments' => ['view', 'create', 'delete'],

        // ── Competition workflow ────────────────────────────────────
        'applications' => ['view', 'ready_for_judging', 'request_reupload'],
        // start/save_draft/submit are the /judge scoring surface, which the
        // first pass at this catalogue missed by enumerating only the admin
        // routes. Without them the judge role could view an evaluation and
        // nothing else, so the ADR-015 §7.3 matrix's grant of scoring to
        // judges had nothing to attach to.
        'evaluations' => ['view', 'start', 'save_draft', 'submit'],
        'appeals' => ['view', 'accept', 'reject'],

        // ── Media ───────────────────────────────────────────────────
        'media' => ['view', 'create', 'update', 'delete', 'reprocess'],
        // videos.reprocess re-runs the FFmpeg HLS pipeline for one video.
        // It was missing while media.reprocess existed, so the identical
        // operation was grantable for one asset type and not the other.
        'videos' => ['view', 'delete', 'reprocess'],
        // streaming.start / streaming.stop are separate from create on
        // purpose. Creating a room provisions RTMP keys; starting one puts
        // a signal on air and stopping one takes it off, mid-competition.
        // Folding them into create would hand whoever can set up a room
        // the ability to cut a live broadcast — precisely the conflation
        // the `manage` verb was removed to prevent (ADR-015 §4.4).
        'streaming' => ['view', 'create', 'start', 'stop', 'delete'],

        // ── Content ─────────────────────────────────────────────────
        'content' => ['view', 'create', 'delete', 'publish'],
        'sponsors' => ['view', 'create', 'delete'],

        // ── Operations ──────────────────────────────────────────────
        'notifications' => ['view', 'retry'],
        'reports' => ['view', 'export'],
        'search' => ['view', 'reindex'],
        // NO ENDPOINT YET — the admin panel calls /admin/system/audit-logs,
        // which is not routed.
        'audit' => ['view'],
        // NO ENDPOINT YET — only a public settings read exists.
        'settings' => ['view', 'update'],
    ];

    /**
     * Every permission name, flattened, in catalogue order.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::CATALOG as $resource => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }

    /**
     * resource => permission names. The grouping the roles UI renders,
     * derived from the names rather than stored (ADR-015 §1).
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::CATALOG as $resource => $actions) {
            $groups[$resource] = array_map(
                static fn (string $action): string => "{$resource}.{$action}",
                $actions
            );
        }

        return $groups;
    }

    /** @return array<int, string> */
    public static function resources(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * Every permission belonging to the given resources.
     *
     * Exists so a role definition can say "everything about seasons"
     * without listing the actions, which would go stale the moment a new
     * one is added — the drift this catalogue exists to prevent.
     *
     * @param  array<int, string>  $resources
     * @return array<int, string>
     */
    public static function forResources(array $resources): array
    {
        $grouped = self::grouped();
        $names = [];

        foreach ($resources as $resource) {
            if (! isset($grouped[$resource])) {
                throw new \InvalidArgumentException("Unknown permission resource: {$resource}");
            }

            $names = array_merge($names, $grouped[$resource]);
        }

        return $names;
    }

    public static function has(string $name): bool
    {
        return in_array($name, self::all(), true);
    }

    public static function count(): int
    {
        return count(self::all());
    }
}
