<?php

declare(strict_types=1);

use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\Services\SeasonStateMachine;
use Modules\Competition\Domain\ValueObjects\SeasonStatus;

uses()->group('competition', 'unit', 'season-state-machine');

test('every valid forward transition is allowed', function (string $from, string $to): void {
    $machine = new SeasonStateMachine;

    expect($machine->canTransition($from, $to))->toBeTrue();
    expect($machine->transition($from, $to))->toBe($to);
})->with([
    'draft -> registration_open' => [SeasonStatus::DRAFT, SeasonStatus::REGISTRATION_OPEN],
    'registration_open -> registration_closed' => [SeasonStatus::REGISTRATION_OPEN, SeasonStatus::REGISTRATION_CLOSED],
    'registration_closed -> competition_running' => [SeasonStatus::REGISTRATION_CLOSED, SeasonStatus::COMPETITION_RUNNING],
    'competition_running -> judging' => [SeasonStatus::COMPETITION_RUNNING, SeasonStatus::JUDGING],
    'judging -> completed' => [SeasonStatus::JUDGING, SeasonStatus::COMPLETED],
    'completed -> archived' => [SeasonStatus::COMPLETED, SeasonStatus::ARCHIVED],
    'draft -> archived (early cancellation)' => [SeasonStatus::DRAFT, SeasonStatus::ARCHIVED],
]);

test('skipping a state is rejected', function (): void {
    $machine = new SeasonStateMachine;

    expect($machine->canTransition(SeasonStatus::DRAFT, SeasonStatus::COMPETITION_RUNNING))->toBeFalse();
    expect(fn () => $machine->transition(SeasonStatus::DRAFT, SeasonStatus::COMPETITION_RUNNING))
        ->toThrow(InvalidSeasonTransitionException::class);
});

test('moving backward is rejected', function (string $from, string $to): void {
    $machine = new SeasonStateMachine;

    expect($machine->canTransition($from, $to))->toBeFalse();
    expect(fn () => $machine->transition($from, $to))->toThrow(InvalidSeasonTransitionException::class);
})->with([
    'registration_closed -> registration_open' => [SeasonStatus::REGISTRATION_CLOSED, SeasonStatus::REGISTRATION_OPEN],
    'competition_running -> registration_closed' => [SeasonStatus::COMPETITION_RUNNING, SeasonStatus::REGISTRATION_CLOSED],
    'judging -> competition_running' => [SeasonStatus::JUDGING, SeasonStatus::COMPETITION_RUNNING],
    'completed -> judging' => [SeasonStatus::COMPLETED, SeasonStatus::JUDGING],
]);

test('cancellation (-> archived) is only reachable from draft, not from any later state', function (string $from): void {
    $machine = new SeasonStateMachine;

    expect($machine->canTransition($from, SeasonStatus::ARCHIVED))->toBeFalse();
})->with([
    'registration_open' => [SeasonStatus::REGISTRATION_OPEN],
    'registration_closed' => [SeasonStatus::REGISTRATION_CLOSED],
    'competition_running' => [SeasonStatus::COMPETITION_RUNNING],
    'judging' => [SeasonStatus::JUDGING],
]);

test('archived is a true terminal state — no transition out of it', function (): void {
    $machine = new SeasonStateMachine;

    expect($machine->allowedFrom(SeasonStatus::ARCHIVED))->toBe([]);
    expect(fn () => $machine->transition(SeasonStatus::ARCHIVED, SeasonStatus::DRAFT))
        ->toThrow(InvalidSeasonTransitionException::class);
});

test('the exception reports what was actually allowed', function (): void {
    $machine = new SeasonStateMachine;

    try {
        $machine->transition(SeasonStatus::REGISTRATION_OPEN, SeasonStatus::ARCHIVED);
        $this->fail('Expected InvalidSeasonTransitionException to be thrown.');
    } catch (InvalidSeasonTransitionException $e) {
        expect($e->from)->toBe(SeasonStatus::REGISTRATION_OPEN);
        expect($e->to)->toBe(SeasonStatus::ARCHIVED);
        expect($e->allowed)->toBe([SeasonStatus::REGISTRATION_CLOSED]);
    }
});
