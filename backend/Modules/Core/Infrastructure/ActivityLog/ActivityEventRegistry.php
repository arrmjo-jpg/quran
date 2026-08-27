<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\ActivityLog;

/**
 * Which entity each domain event is about — ADR-017 D6.
 *
 * KEYED BY CLASS-NAME STRING, NOT BY IMPORT, AND THAT IS ARCHITECTURAL.
 * This class lives in Core and describes events owned by seven other modules.
 * Importing them would be a cross-module dependency outside `Contracts/`,
 * which ADR-002 forbids and the repaired boundary guard now actually detects.
 * A string is not a dependency: nothing here is constructed, called or
 * type-hinted.
 *
 * AN EXHAUSTIVE LIST RATHER THAN A RULE. The convention that a payload's first
 * key is the aggregate id holds for all 46 events today, and a rule built on it
 * would be right until it was quietly wrong. `all_judges_completed` is the case
 * that shows why: it is an Evaluations event about an *application*, and no
 * amount of reading the event's name would say so.
 *
 * ActivityEventRegistryTest asserts that every class under
 * `Modules/*\/Domain/Events` appears here, so adding an event without deciding
 * how it is logged fails the suite rather than producing a silently unlogged
 * event. Same pattern as ModuleBoundary::BASELINE: an explicit list plus a
 * guard that makes forgetting it impossible.
 */
final class ActivityEventRegistry
{
    /**
     * event FQCN => [entity type, the payload key holding that entity's id]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const EVENTS = [
        // Applications
        'Modules\Applications\Domain\Events\ApplicationCreated' => ['application', 'application_id'],
        'Modules\Applications\Domain\Events\ApplicationSubmitted' => ['application', 'application_id'],

        // Competition
        'Modules\Competition\Domain\Events\ResultsPublished' => ['stage', 'stage_id'],
        'Modules\Competition\Domain\Events\SeasonArchived' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonCancelled' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonCompetitionStarted' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonCompleted' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonCreated' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonJudgingStarted' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonRegistrationClosed' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonRegistrationOpened' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonRegistrationReopened' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonRestored' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\SeasonRulesFrozen' => ['season', 'season_id'],
        'Modules\Competition\Domain\Events\StageCreated' => ['stage', 'stage_id'],

        // Contestants
        'Modules\Contestants\Domain\Events\ContestantCreated' => ['contestant', 'contestant_id'],
        'Modules\Contestants\Domain\Events\ContestantDeleted' => ['contestant', 'contestant_id'],
        'Modules\Contestants\Domain\Events\ContestantRestored' => ['contestant', 'contestant_id'],
        'Modules\Contestants\Domain\Events\ContestantUpdated' => ['contestant', 'contestant_id'],

        // Core
        'Modules\Core\Domain\Events\InvitationAccepted' => ['invitation', 'invitation_id'],
        'Modules\Core\Domain\Events\RoleCreated' => ['role', 'role_id'],
        'Modules\Core\Domain\Events\RoleDeleted' => ['role', 'role_id'],
        'Modules\Core\Domain\Events\RolePermissionsChanged' => ['role', 'role_id'],
        'Modules\Core\Domain\Events\RoleRenamed' => ['role', 'role_id'],
        'Modules\Core\Domain\Events\UserDeactivated' => ['user', 'user_id'],
        'Modules\Core\Domain\Events\UserDeleted' => ['user', 'user_id'],
        'Modules\Core\Domain\Events\UserProfileUpdated' => ['user', 'user_id'],
        'Modules\Core\Domain\Events\UserReactivated' => ['user', 'user_id'],
        'Modules\Core\Domain\Events\UserRestored' => ['user', 'user_id'],
        'Modules\Core\Domain\Events\UserRolesChanged' => ['user', 'user_id'],

        // Countries
        'Modules\Countries\Domain\Events\CountryActivated' => ['country', 'country_id'],
        'Modules\Countries\Domain\Events\CountryCreated' => ['country', 'country_id'],
        'Modules\Countries\Domain\Events\CountryDeactivated' => ['country', 'country_id'],
        'Modules\Countries\Domain\Events\CountryUpdated' => ['country', 'country_id'],

        // Evaluations — note the first one is about an application, not an
        // evaluation. The registry exists for entries like this.
        'Modules\Evaluations\Domain\Events\AllJudgesCompleted' => ['application', 'application_id'],
        'Modules\Evaluations\Domain\Events\EvaluationApproved' => ['evaluation', 'evaluation_id'],
        'Modules\Evaluations\Domain\Events\EvaluationSubmitted' => ['evaluation', 'evaluation_id'],

        // Organization
        'Modules\Organization\Domain\Events\CenterCreated' => ['center', 'center_id'],
        'Modules\Organization\Domain\Events\CenterDeleted' => ['center', 'center_id'],
        'Modules\Organization\Domain\Events\CenterUpdated' => ['center', 'center_id'],
        'Modules\Organization\Domain\Events\CircleCreated' => ['circle', 'circle_id'],
        'Modules\Organization\Domain\Events\CircleDeleted' => ['circle', 'circle_id'],
        'Modules\Organization\Domain\Events\CircleUpdated' => ['circle', 'circle_id'],

        // The subject is the person who moved, not the membership rows that
        // opened and closed to record the move.
        'Modules\Organization\Domain\Events\ContestantTransferred' => ['contestant', 'contestant_id'],

        'Modules\Organization\Domain\Events\MembershipEnded' => ['membership', 'membership_id'],
        'Modules\Organization\Domain\Events\MembershipStarted' => ['membership', 'membership_id'],
    ];

    public static function knows(string $eventClass): bool
    {
        return isset(self::EVENTS[$eventClass]);
    }

    /** @return array{0: string, 1: string}|null [entity type, id key] */
    public static function describe(string $eventClass): ?array
    {
        return self::EVENTS[$eventClass] ?? null;
    }

    /** @return array<int, string> every registered event class */
    public static function registered(): array
    {
        return array_keys(self::EVENTS);
    }
}
