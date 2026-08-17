<?php

declare(strict_types=1);

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Exceptions\InvalidUserEmailException;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;

uses()->group('core', 'unit', 'domain');

test('user aggregate enforces email validation invariant via Email ValueObject', function (): void {
    new Email('invalid-email-string');
})->throws(InvalidUserEmailException::class);

test('user aggregate can be instantiated with Value Objects and manages active state', function (): void {
    $user = new User(
        id: UserId::generate(),
        email: new Email('user@example.com'),
        name: 'Test User',
        type: UserType::contestant()
    );

    expect($user->getEmail()->value)->toBe('user@example.com');
    expect($user->isActive())->toBeTrue();

    $user->deactivate();
    expect($user->isActive())->toBeFalse();
});

test('UserType accepts only contestant and admin', function (): void {
    expect(UserType::contestant()->value)->toBe('contestant');
    expect(UserType::admin()->value)->toBe('admin');
    expect(UserType::contestant()->isContestant())->toBeTrue();
    expect(UserType::admin()->isAdmin())->toBeTrue();
    expect(UserType::ALLOWED)->toBe(['contestant', 'admin']);
});

test('UserType rejects the pre-ADR-015 contestant value', function (): void {
    // 'user' was the contestant value before the ADR-015 rename. It must
    // never be accepted again — this is the guard that makes the rename
    // permanent rather than a one-off data fix.
    new UserType('user');
})->throws(InvalidArgumentException::class);

test('UserType rejects an arbitrary value', function (): void {
    new UserType('superuser');
})->throws(InvalidArgumentException::class);
