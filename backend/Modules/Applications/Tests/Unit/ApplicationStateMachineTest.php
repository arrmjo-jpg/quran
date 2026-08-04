<?php

declare(strict_types=1);

use Modules\Applications\Domain\Services\ApplicationStateMachine;

uses()->group('applications', 'unit', 'statemachine');

test('application state machine enforces valid status transitions', function (): void {
    $sm = new ApplicationStateMachine;

    // Valid transition: draft -> submitted
    expect($sm->canTransition('draft', 'submitted'))->toBeTrue();
    expect($sm->transition('draft', 'submitted'))->toBe('submitted');

    // Valid transition: submitted -> video_reupload_requested
    expect($sm->canTransition('submitted', 'video_reupload_requested'))->toBeTrue();

    // Invalid transition: draft -> qualified (must fail)
    expect($sm->canTransition('draft', 'qualified'))->toBeFalse();
});

test('application state machine throws exception on invalid status transition', function (): void {
    $sm = new ApplicationStateMachine;
    $sm->transition('draft', 'published');
})->throws(InvalidArgumentException::class);
