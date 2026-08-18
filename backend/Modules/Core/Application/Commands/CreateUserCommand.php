<?php

declare(strict_types=1);

namespace Modules\Core\Application\Commands;

use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\PasswordHash;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;

/**
 * CreateUserCommand DTO
 */
final readonly class CreateUserCommand
{
    public function __construct(
        public UserId $id,
        public Email $email,
        public string $name,
        public UserType $type,
        public PasswordHash $passwordHash,
        public Locale $preferredLocale = new Locale('ar'),
    ) {}
}
