<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Entities;

use InvalidArgumentException;
use Modules\Organization\Domain\Events\CircleCreated;
use Modules\Organization\Domain\Events\CircleUpdated;
use Modules\Organization\Domain\ValueObjects\CircleId;

/**
 * A study circle, held at a centre — ADR-016 D6.
 *
 * Independent of the contestants who attend it: it has its own lifecycle,
 * permissions and consumers, which is why it is an entity here rather than a
 * property of a contestant.
 *
 * KNOWS NOTHING ABOUT ITS LOCATION, and that absence is Q3. Country, city,
 * address and coordinates belong to the centre and are read through
 * `centerId`; duplicating any of them here would create a second truth that
 * goes stale the first time a centre moves.
 *
 * The supervisor is held as a plain user id rather than an entity. ADR-002
 * forbids this module importing concrete classes from Core, and D9 settles
 * that a supervisor is simply a User — so the id is the whole of what this
 * aggregate needs to know, exactly as `centerId` is on a Center's country.
 */
final class Circle
{
    /** @var array<int, object> */
    private array $events = [];

    private function __construct(
        public readonly CircleId $id,
        private string $name,
        private readonly string $centerId,
        private ?string $supervisorUserId,
    ) {}

    public static function create(
        CircleId $id,
        string $name,
        string $centerId,
        ?string $supervisorUserId = null,
    ): self {
        self::assertValidName($name);

        $circle = new self($id, trim($name), $centerId, $supervisorUserId);

        $circle->events[] = new CircleCreated(
            circleId: $id->value,
            name: $circle->name,
            centerId: $centerId,
            occurredAt: now()->toIso8601String(),
        );

        return $circle;
    }

    /** Rebuilt from storage; records no event. */
    public static function reconstitute(
        CircleId $id,
        string $name,
        string $centerId,
        ?string $supervisorUserId,
    ): self {
        return new self($id, $name, $centerId, $supervisorUserId);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCenterId(): string
    {
        return $this->centerId;
    }

    public function getSupervisorUserId(): ?string
    {
        return $this->supervisorUserId;
    }

    /**
     * Renames the circle, appoints a supervisor, or removes one.
     *
     * THE CENTRE IS NOT AMONG THE PARAMETERS, and `centerId` is readonly to
     * enforce it. Under Q3 a circle's location IS its centre's, so moving a
     * circle between centres would silently relocate every record that reads
     * through it — the same objection that makes a Centre's country immutable.
     * ADR-016 does not decide whether circles genuinely relocate; refusing it
     * is the reversible direction, because relaxing this later costs nothing
     * while retrofitting the rule after circles have drifted costs history.
     *
     * Records an event only when something actually changed, so an audit trail
     * is not filled with entries saying a form was submitted unaltered.
     */
    public function update(string $name, ?string $supervisorUserId): void
    {
        self::assertValidName($name);

        $previousName = $this->name;
        $previousSupervisor = $this->supervisorUserId;

        $this->name = trim($name);
        $this->supervisorUserId = $supervisorUserId;

        if ($previousName === $this->name && $previousSupervisor === $this->supervisorUserId) {
            return;
        }

        $this->events[] = new CircleUpdated(
            circleId: $this->id->value,
            previousName: $previousName,
            name: $this->name,
            previousSupervisorUserId: $previousSupervisor,
            supervisorUserId: $this->supervisorUserId,
            occurredAt: now()->toIso8601String(),
        );
    }

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    private static function assertValidName(string $name): void
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A circle name cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new InvalidArgumentException('A circle name cannot exceed 255 characters.');
        }
    }
}
