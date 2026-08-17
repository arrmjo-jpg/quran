<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

/**
 * Seeds the six actors ADR-001 named, with the capability matrix from
 * ADR-015 §7.3.
 *
 * TWO DIFFERENT CONTRACTS, and the difference is the point:
 *
 * SYSTEM ROLES (is_system = true — only super_admin) are defined here, in
 * code, and this seeder is their single source of truth. Every run
 * re-synchronises them to their definition. No API, no UI and no use case
 * can alter them — Role::syncPermissions() refuses a system role
 * outright. So the way super_admin gains a newly added permission is that
 * the catalogue grows and this seeder runs, never that someone edits it
 * in a panel. That is what makes "system role" mean immutable rather
 * than merely protected by a badge.
 *
 * SEEDED ROLES (is_system = false — the other five) are starting points,
 * not definitions. They are created once with the grants below and are
 * then owned by whoever operates the platform: re-running this seeder
 * deliberately leaves an existing one exactly as it is, so an operator's
 * edits are never silently reverted by a deploy.
 *
 * WHY super_admin IS SEEDED FROM PermissionCatalog::all() RATHER THAN A
 * LIST: it must hold every permission by enumeration (ADR-015 §5, no
 * wildcards). Writing the list out by hand would mean a new catalogue
 * entry silently failing to reach it — the class of drift this design
 * spends most of its effort preventing.
 */
final class RolesSeeder extends Seeder
{
    public function __construct(
        private readonly RoleRepositoryContract $roles,
    ) {}

    public function run(): void
    {
        $this->syncSystemRoles();
        $this->createSeededRoles();
    }

    /**
     * Re-synchronised on every run: their definition lives in code.
     */
    private function syncSystemRoles(): void
    {
        foreach ($this->systemRoleDefinitions() as $name => $permissions) {
            $existing = $this->roles->findByName($name);

            // Reconstituted from the definition rather than mutated —
            // the aggregate refuses permission changes on a system role,
            // and rightly so.
            $this->roles->save(new Role(
                id: $existing?->id ?? RoleId::generate(),
                name: $name,
                isSystem: true,
                permissions: PermissionName::fromMany($permissions),
            ));
        }
    }

    /**
     * Created once, then left alone.
     */
    private function createSeededRoles(): void
    {
        foreach ($this->seededRoleDefinitions() as $name => $permissions) {
            if ($this->roles->findByName($name) !== null) {
                continue;
            }

            $this->roles->save(new Role(
                id: RoleId::generate(),
                name: $name,
                isSystem: false,
                permissions: PermissionName::fromMany($permissions),
            ));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function systemRoleDefinitions(): array
    {
        return [
            'super_admin' => PermissionCatalog::all(),
        ];
    }

    /**
     * The ADR-015 §7.3 matrix, as initial grants.
     *
     * "(assigned only)" and "(self)" from the matrix are NOT expressed
     * here: they are policy checks layered on top of a permission, not
     * permissions themselves. `evaluations.submit` says a judge may
     * score; which applications they may score is decided by their panel
     * assignments. Both are required and neither substitutes for the
     * other.
     *
     * @return array<string, array<int, string>>
     */
    private function seededRoleDefinitions(): array
    {
        $competitionManager = PermissionCatalog::forResources([
            'seasons', 'season_rules', 'stages', 'countries',
            'applications', 'judges', 'judge_assignments', 'appeals',
            'media', 'videos', 'streaming', 'notifications', 'reports',
        ]);

        $competitionManager = array_merge($competitionManager, [
            'permissions.view',
            'contestants.view',
            'evaluations.view',
            'content.view',
            'sponsors.view',
        ]);

        return [
            // Runs the competition, but cannot touch identity: no users,
            // no roles, no audit, no settings. Separating "runs the
            // competition" from "controls who can run it" is the reason
            // roles exist at all.
            'competition_manager' => $competitionManager,

            // Scores what they are assigned. The scoring verbs are the
            // /judge surface; the view grants are what that screen needs
            // to show an application and its media.
            'judge' => [
                'applications.view',
                'judges.view',
                'judge_assignments.view',
                'evaluations.view',
                'evaluations.start',
                'evaluations.save_draft',
                'evaluations.submit',
                'media.view',
                'videos.view',
            ],

            // Same scoring capability, without the judge's own profile
            // and panel visibility.
            'evaluator' => [
                'applications.view',
                'evaluations.view',
                'evaluations.start',
                'evaluations.save_draft',
                'evaluations.submit',
                'media.view',
                'videos.view',
            ],

            'data_entry' => [
                'countries.view',
                'contestants.view',
                'contestants.update',
                'applications.view',
                'media.view',
                'media.create',
                'media.update',
                'videos.view',
                'reports.view',
            ],

            'moderator' => [
                'applications.view',
                'appeals.view',
                'appeals.accept',
                'appeals.reject',
                'content.view',
                'content.create',
                'content.delete',
                'content.publish',
                'sponsors.view',
                'sponsors.create',
                'sponsors.delete',
                'media.view',
                'videos.view',
                'streaming.view',
                'notifications.view',
            ],
        ];
    }
}
