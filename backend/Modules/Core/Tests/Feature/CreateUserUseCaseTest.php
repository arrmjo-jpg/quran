<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Commands\CreateUserCommand;
use Modules\Core\Application\UseCases\CreateUserUseCase;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\PasswordHash;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Repositories\UserRepository;

uses(RefreshDatabase::class)->group('core', 'feature', 'usecases');

test('create user use case persists user in database', function (): void {
    $useCase = new CreateUserUseCase(new UserRepository);

    $id = UserId::generate();
    $email = new Email('newuser@example.com');
    $password = PasswordHash::fromPlainPassword('Secret1234!');

    $command = new CreateUserCommand(
        id: $id,
        email: $email,
        name: 'New Platform User',
        type: UserType::contestant(),
        passwordHash: $password
    );

    $useCase->execute($command);

    $this->assertDatabaseHas('users', [
        'id' => $id->value,
        'email' => 'newuser@example.com',
        'name' => 'New Platform User',
        'type' => 'contestant',
        'is_active' => 1,
    ]);
});
