<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A point on the earth, or nothing at all.
 *
 * Exists as a pair rather than two loose columns because latitude without
 * longitude is not half a location — it is no location. Making them
 * inseparable in the domain means no code path can persist one and forget the
 * other, which is how a map ends up plotting points on the prime meridian.
 *
 * Range-checked because the values are typed by hand from documents and maps:
 * a transposed pair (31.9, 35.9 entered as 35.9, 31.9) is still valid on both
 * axes and cannot be caught here, but a longitude of 359 can be.
 */
final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90, got {$latitude}.");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180, got {$longitude}.");
        }
    }

    /** Both or neither — see the class docblock. */
    public static function fromNullable(?float $latitude, ?float $longitude): ?self
    {
        if ($latitude === null && $longitude === null) {
            return null;
        }

        if ($latitude === null || $longitude === null) {
            throw new InvalidArgumentException('Coordinates need both a latitude and a longitude, or neither.');
        }

        return new self($latitude, $longitude);
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }
}
