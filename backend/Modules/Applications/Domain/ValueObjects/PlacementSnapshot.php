<?php

declare(strict_types=1);

namespace Modules\Applications\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Where a contestant studied at the moment they applied — ADR-016 D8.
 *
 * Four fields that only mean anything together, so they travel as one value
 * rather than as four parameters that can drift apart. This follows the
 * reasoning Coordinates records in the Organization module: latitude without
 * longitude is not half a location, it is none. A circle id without the circle
 * name is worse than none, because it looks like a snapshot while resolving to
 * whatever the circle is called today — which is the precise failure D8 exists
 * to prevent.
 *
 * IMMUTABLE ON PURPOSE, WITH NO SETTERS AND NO REFRESH. Nothing in this design
 * updates a snapshot after the fact. If a centre is renamed tomorrow, the
 * application still reads as it did when it was submitted; that is the whole
 * feature, and a well-meaning "keep it in sync" would silently undo it.
 */
final readonly class PlacementSnapshot
{
    public function __construct(
        public string $centerId,
        public string $circleId,
        public string $centerName,
        public string $circleName,
    ) {
        self::assertPresent($centerId, 'centre id');
        self::assertPresent($circleId, 'circle id');
        self::assertPresent($centerName, 'centre name');
        self::assertPresent($circleName, 'circle name');
    }

    /**
     * Rebuilds a snapshot from storage, or nothing at all.
     *
     * All four or none. The columns are nullable because Q4 refused to trap
     * rows written before this epic, so an application from before D8 has no
     * placement — but a row carrying two of the four is corruption rather than
     * history, and returning a half-built value object would push that
     * discovery to whatever rendered it.
     */
    public static function fromNullable(
        ?string $centerId,
        ?string $circleId,
        ?string $centerName,
        ?string $circleName,
    ): ?self {
        $present = array_filter(
            [$centerId, $circleId, $centerName, $circleName],
            static fn (?string $value): bool => $value !== null && $value !== ''
        );

        if ($present === []) {
            return null;
        }

        if (count($present) !== 4) {
            throw new InvalidArgumentException(
                'A placement snapshot must carry all four fields or none; this row carries '.count($present).'.'
            );
        }

        return new self($centerId, $circleId, $centerName, $circleName);
    }

    public function equals(self $other): bool
    {
        return $this->centerId === $other->centerId
            && $this->circleId === $other->circleId
            && $this->centerName === $other->centerName
            && $this->circleName === $other->circleName;
    }

    private static function assertPresent(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("A placement snapshot {$field} cannot be empty.");
        }
    }
}
