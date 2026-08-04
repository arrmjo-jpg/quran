<?php

declare(strict_types=1);

use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Exceptions\InvalidUserEmailException;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\UserId;

uses()->group('core', 'unit', 'domain');

test('user aggregate enforces email validation invariant via Email ValueObject', function (): void {
    new Email('invalid-email-string');
})->throws(InvalidUserEmailException::class);

test('user aggregate can be instantiated with Value Objects and manages active state', function (): void {
    $user = new User(
        id: UserId::generate(),
        email: new Email('user@example.com'),
        name: 'Test User',
        type: 'user'
    );

    expect($user->getEmail()->value)->toBe('user@example.com');
    expect($user->isActive())->toBeTrue();

    $user->deactivate();
    expect($user->isActive())->toBeFalse();
});
