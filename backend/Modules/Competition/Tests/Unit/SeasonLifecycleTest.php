<?php

declare(strict_types=1);

use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Events\SeasonArchived;
use Modules\Competition\Domain\Events\SeasonFrozen;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Services\SeasonRuleSnapshotFactory;
use Modules\Competition\Domain\Services\SeasonStateMachine;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;
use Modules\Competition\Domain\ValueObjects\ResolvedSeasonRules;
use Modules\Competition\Domain\ValueObjects\ResolvedStageRule;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;

uses()->group('competition', 'unit', 'season-lifecycle');

function makeDraftSeason(): Season
{
    return Season::create(
        id: fake()->uuid(),
        slug: 'test-season-'.fake()->unique()->numerify('####'),
        year: 2027,
        regStartIso: '2027-01-01T00:00:00+00:00',
        regEndIso: '2027-01-15T00:00:00+00:00',
        startDateIso: '2027-01-16T00:00:00+00:00',
        endDateIso: '2027-03-01T00:00:00+00:00',
    );
}

function fullyConfiguredResolvedRules(): ResolvedSeasonRules
{
    return new ResolvedSeasonRules(
        participationType: new ResolvedLookupOption('pt-1', 'mixed', ['ar' => 'مختلط', 'en' => 'Mixed', 'es' => 'Mixto']),
        tajweedLevel: new ResolvedLookupOption('tl-1', 'advanced', ['ar' => 'متقدم', 'en' => 'Advanced', 'es' => 'Avanzado']),
        eligibleCountries: [
            new ResolvedLookupOption('jo', 'JO', ['ar' => 'الأردن', 'en' => 'Jordan', 'es' => 'Jordania']),
        ],
        stageRules: [
            new ResolvedStageRule(
                stageId: 'stage-1',
                stageNumber: 1,
                type: 'preliminary',
                name: ['ar' => 'التصفيات', 'en' => 'Preliminaries', 'es' => 'Preliminares'],
                judgeScoreSystem: new ResolvedLookupOption('s', 'scale_100', ['ar' => 'أ', 'en' => 'a', 'es' => 'a']),
                judgeScoreSystemMaxScore: 100.0,
                qualificationPercentage: 70.0,
            ),
        ],
    );
}

/** Fully configure a draft season (translations + age + type + level) so it can freeze(). */
function makeReadyToFreezeSeason(): Season
{
    $season = makeDraftSeason();

    foreach (['ar' => 'موسم 2027', 'en' => 'Season 2027', 'es' => 'Temporada 2027'] as $locale => $title) {
        $season->setTranslation(new SeasonTranslation($locale, $title, publicName: $title));
    }

    $season->setAgeRange(6, 40);
    $season->setParticipationType('pt-1');
    $season->setTajweedLevel('tl-1');

    return $season;
}

// ── Happy path: the full lifecycle end to end ──────────────────────────────

test('a season can walk the entire lifecycle from draft to archived', function (): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();

    expect($season->getStatus())->toBe('draft');

    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());
    expect($season->getStatus())->toBe('registration_open');
    expect($season->isActive())->toBeTrue();
    expect($season->isFrozen())->toBeTrue();

    $season->closeRegistration();
    expect($season->getStatus())->toBe('registration_closed');

    $season->startCompetition($machine);
    expect($season->getStatus())->toBe('competition_running');

    $season->openJudging($machine);
    expect($season->getStatus())->toBe('judging');

    $season->complete($machine);
    expect($season->getStatus())->toBe('completed');

    $season->archive($machine, reason: null, byUserId: 'admin-1');
    expect($season->getStatus())->toBe('archived');
    expect($season->isActive())->toBeFalse();
    expect($season->getArchivedByUserId())->toBe('admin-1');
});

// ── freeze() guards ──────────────────────────────────────────────────────

test('freeze() rejects an incomplete translation set', function (): void {
    $season = makeDraftSeason();
    $season->setTranslation(new SeasonTranslation('ar', 'موسم', publicName: 'موسم'));
    // en/es missing entirely
    $season->setAgeRange(6, 40);
    $season->setParticipationType('pt-1');
    $season->setTajweedLevel('tl-1');

    expect(fn () => $season->freeze(new SeasonStateMachine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules()))
        ->toThrow(IncompleteSeasonRulesException::class);
});

test('freeze() rejects a translation missing public_name even if title is set', function (): void {
    $season = makeDraftSeason();
    foreach (['ar', 'en', 'es'] as $locale) {
        // title present, publicName omitted -> isComplete() is false
        $season->setTranslation(new SeasonTranslation($locale, 'title-'.$locale));
    }
    $season->setAgeRange(6, 40);
    $season->setParticipationType('pt-1');
    $season->setTajweedLevel('tl-1');

    expect(fn () => $season->freeze(new SeasonStateMachine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules()))
        ->toThrow(IncompleteSeasonRulesException::class);
});

test('freeze() rejects a season with no participation type, tajweed level, or age range selected', function (): void {
    $season = makeDraftSeason();
    foreach (['ar' => 'أ', 'en' => 'a', 'es' => 'a'] as $locale => $title) {
        $season->setTranslation(new SeasonTranslation($locale, $title, publicName: $title));
    }
    // age/type/level intentionally left unset

    expect(fn () => $season->freeze(new SeasonStateMachine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules()))
        ->toThrow(IncompleteSeasonRulesException::class);
});

test('freeze() can only be called from draft', function (): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();
    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());

    expect(fn () => $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules()))
        ->toThrow(InvalidSeasonTransitionException::class);
});

test('freeze() records a SeasonFrozen event carrying version 1 and the resolved snapshot', function (): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();
    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());

    $events = $season->releaseEvents();
    $frozenEvents = array_values(array_filter($events, fn ($e) => $e instanceof SeasonFrozen));

    expect($frozenEvents)->toHaveCount(1);
    expect($frozenEvents[0]->version)->toBe(1);
    expect($frozenEvents[0]->snapshot['participation_type']['code'])->toBe('mixed');
    expect($frozenEvents[0]->snapshot['stages'][0]['required_score'])->toBe(70.0);
});

// ── The frozen lock: no editing settings after freeze() ─────────────────────

test('age range, participation type, tajweed level, and translations are all locked after freeze()', function (Closure $mutate): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();
    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());

    expect(fn () => $mutate($season))->toThrow(SeasonAlreadyFrozenException::class);
})->with([
    'age range' => [fn (Season $s) => $s->setAgeRange(7, 41)],
    'participation type' => [fn (Season $s) => $s->setParticipationType('pt-2')],
    'tajweed level' => [fn (Season $s) => $s->setTajweedLevel('tl-2')],
    'translation' => [fn (Season $s) => $s->setTranslation(new SeasonTranslation('ar', 'محدّث', publicName: 'محدّث'))],
]);

test('settings remain editable freely before freeze() (still draft)', function (): void {
    $season = makeDraftSeason();

    $season->setAgeRange(6, 40);
    $season->setAgeRange(8, 35); // changing your mind pre-freeze is fine
    expect($season->getMinAge())->toBe(8);
    expect($season->getMaxAge())->toBe(35);
});

test('min_age cannot exceed max_age', function (): void {
    $season = makeDraftSeason();

    expect(fn () => $season->setAgeRange(40, 6))->toThrow(InvalidArgumentException::class);
});

// ── cancel(): draft-only, with a mandatory reason ────────────────────────────

test('a draft season can be cancelled with a reason', function (): void {
    $machine = new SeasonStateMachine;
    $season = makeDraftSeason();

    $season->cancel($machine, reason: 'Competition postponed indefinitely.', byUserId: 'admin-1');

    expect($season->getStatus())->toBe('archived');
    expect($season->getArchiveReason())->toBe('Competition postponed indefinitely.');

    $events = $season->releaseEvents();
    $archived = array_values(array_filter($events, fn ($e) => $e instanceof SeasonArchived));
    expect($archived)->toHaveCount(1);
    expect($archived[0]->wasCancelled)->toBeTrue();
});

test('cancel() requires a non-empty reason', function (): void {
    $season = makeDraftSeason();

    expect(fn () => $season->cancel(new SeasonStateMachine, reason: '   ', byUserId: 'admin-1'))
        ->toThrow(InvalidArgumentException::class);
});

test('cancel() is rejected once registration has opened — no cancellation path after that', function (): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();
    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());

    expect(fn () => $season->cancel($machine, reason: 'too late', byUserId: 'admin-1'))
        ->toThrow(InvalidSeasonTransitionException::class);
});

// ── archive(): completed-only ────────────────────────────────────────────

test('archive() is rejected from every status except completed', function (string $statusReached): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();
    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());

    if ($statusReached !== 'registration_open') {
        $season->closeRegistration();
    }
    if (in_array($statusReached, ['competition_running', 'judging'], true)) {
        $season->startCompetition($machine);
    }
    if ($statusReached === 'judging') {
        $season->openJudging($machine);
    }

    expect(fn () => $season->archive($machine, reason: null, byUserId: null))
        ->toThrow(InvalidSeasonTransitionException::class);
})->with(['registration_open', 'registration_closed', 'competition_running', 'judging']);

// ── No backward transitions anywhere in the real entity, not just the raw machine ──

test('the entity itself rejects a backward transition attempt', function (): void {
    $machine = new SeasonStateMachine;
    $season = makeReadyToFreezeSeason();
    $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules());
    $season->closeRegistration();

    // No method exists to go back to registration_open — the only way to
    // even attempt it is to misuse freeze() again, which the state
    // machine itself rejects since 'registration_closed' has no
    // '->registration_open' edge.
    expect(fn () => $season->freeze($machine, new SeasonRuleSnapshotFactory, fullyConfiguredResolvedRules()))
        ->toThrow(InvalidSeasonTransitionException::class);
});
