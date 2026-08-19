<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The secret that lets an invitee claim their account.
 *
 * Exists as a value object so that one rule cannot be forgotten in one place:
 * **the plaintext is returned exactly once, at generation, and is never
 * persisted.** Only the digest is stored, so a database disclosure yields
 * nothing that can be presented back as a token.
 *
 * That is why this class has two constructors with different shapes.
 * generate() is the only way to obtain a plaintext; fromHash() is the only way
 * to rebuild one from storage, and it deliberately cannot produce a plaintext
 * — a repository reading a row has no business being able to mint the secret.
 *
 * Comparison goes through hash_equals(). A plain === on digests leaks timing
 * information that narrows a brute force, and the cost of doing it correctly
 * is nothing.
 */
final readonly class InvitationToken
{
    /** 32 bytes of randomness, rendered as 64 hex characters. */
    private const RANDOM_BYTES = 32;

    private function __construct(
        public string $hash,
        public ?string $plaintext,
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new InvalidArgumentException('An invitation token hash must be 64 lowercase hex characters.');
        }
    }

    /**
     * A new token. The returned object is the only place the plaintext will
     * ever exist — hand it to the invitee and keep the hash.
     */
    public static function generate(): self
    {
        $plaintext = bin2hex(random_bytes(self::RANDOM_BYTES));

        return new self(hash('sha256', $plaintext), $plaintext);
    }

    /** Rebuilt from storage. Carries no plaintext, by construction. */
    public static function fromHash(string $hash): self
    {
        return new self($hash, null);
    }

    public static function hashOf(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public function matches(string $plaintext): bool
    {
        return hash_equals($this->hash, self::hashOf($plaintext));
    }
}
