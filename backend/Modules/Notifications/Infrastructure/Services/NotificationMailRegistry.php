<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Services;

use Closure;
use Modules\Notifications\Contracts\NotificationMailRegistryContract;
use Modules\Notifications\Contracts\NotificationRetryEnvelope;
use Modules\Notifications\Domain\Exceptions\TemplateNotRetryableException;

/**
 * The registry itself — ADR-020 D5.
 *
 * Bound as a singleton, because registrations happen once at boot and every
 * retry has to see them. A fresh instance per resolution would be empty, and
 * an empty registry fails in the one way that looks like a missing feature
 * rather than a wiring mistake.
 */
final class NotificationMailRegistry implements NotificationMailRegistryContract
{
    /** @var array<string, Closure(string, array<string, mixed>): NotificationRetryEnvelope> */
    private array $factories = [];

    public function register(string $templateKey, Closure $factory): void
    {
        $this->factories[$templateKey] = $factory;
    }

    public function has(string $templateKey): bool
    {
        return isset($this->factories[$templateKey]);
    }

    public function rebuild(string $templateKey, string $userId, array $payload): NotificationRetryEnvelope
    {
        if (! isset($this->factories[$templateKey])) {
            throw TemplateNotRetryableException::for($templateKey);
        }

        return ($this->factories[$templateKey])($userId, $payload);
    }
}
