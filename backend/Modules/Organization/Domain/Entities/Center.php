<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Entities;

use InvalidArgumentException;
use Modules\Organization\Domain\Events\CenterCreated;
use Modules\Organization\Domain\Events\CenterUpdated;
use Modules\Organization\Domain\ValueObjects\CenterId;
use Modules\Organization\Domain\ValueObjects\Coordinates;

/**
 * A place where circles meet — ADR-016 D7.
 *
 * Owns its location in full: name, country, city, address and optionally a
 * point on a map. Circles belong to a centre and duplicate none of it, so
 * relocating a centre is one write rather than a sweep across every circle
 * that ever referenced it.
 *
 * The aggregate deliberately knows nothing about circles. A centre with no
 * circles and a centre with a hundred are the same object here; the rule that
 * a populated centre may not be deleted needs a count the aggregate cannot
 * take, so it lives in the use case with the repository that can.
 */
final class Center
{
    /** @var array<int, object> */
    private array $events = [];

    private function __construct(
        public readonly CenterId $id,
        private string $name,
        private string $countryId,
        private string $city,
        private string $address,
        private ?Coordinates $coordinates,
    ) {}

    public static function create(
        CenterId $id,
        string $name,
        string $countryId,
        string $city,
        string $address,
        ?Coordinates $coordinates = null,
    ): self {
        self::assertValidName($name);
        self::assertPresent($city, 'city');
        self::assertPresent($address, 'address');

        $center = new self($id, trim($name), $countryId, trim($city), trim($address), $coordinates);

        $center->events[] = new CenterCreated(
            centerId: $id->value,
            name: $center->name,
            countryId: $countryId,
            occurredAt: now()->toIso8601String(),
        );

        return $center;
    }

    /** Rebuilt from storage; records no event. */
    public static function reconstitute(
        CenterId $id,
        string $name,
        string $countryId,
        string $city,
        string $address,
        ?Coordinates $coordinates,
    ): self {
        return new self($id, $name, $countryId, $city, $address, $coordinates);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getCoordinates(): ?Coordinates
    {
        return $this->coordinates;
    }

    /**
     * Moves or renames the centre.
     *
     * The country is not editable. A centre that changed country would be a
     * different centre — its circles, its supervisors and every application
     * that froze its name belong to the place it was, and letting one row
     * migrate between countries would silently rewrite all of that. Closing it
     * and opening another is the honest operation.
     *
     * Records an event only when something actually changed, so an audit trail
     * is not filled with entries saying a form was submitted unaltered.
     */
    public function update(string $name, string $city, string $address, ?Coordinates $coordinates): void
    {
        self::assertValidName($name);
        self::assertPresent($city, 'city');
        self::assertPresent($address, 'address');

        $before = [$this->name, $this->city, $this->address, $this->coordinates];

        $this->name = trim($name);
        $this->city = trim($city);
        $this->address = trim($address);
        $this->coordinates = $coordinates;

        $unchanged = $before[0] === $this->name
            && $before[1] === $this->city
            && $before[2] === $this->address
            && $this->sameCoordinates($before[3], $coordinates);

        if ($unchanged) {
            return;
        }

        $this->events[] = new CenterUpdated(
            centerId: $this->id->value,
            previousName: $before[0],
            name: $this->name,
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

    private function sameCoordinates(?Coordinates $a, ?Coordinates $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        return $a !== null && $b !== null && $a->equals($b);
    }

    private static function assertValidName(string $name): void
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A centre name cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new InvalidArgumentException('A centre name cannot exceed 255 characters.');
        }
    }

    private static function assertPresent(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("A centre {$field} cannot be empty.");
        }
    }
}
