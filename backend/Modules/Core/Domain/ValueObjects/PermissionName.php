<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * PermissionName Value Object — ADR-015 §4.4.
 *
 * A `resource.action` name, validated for shape at construction. This is
 * the type every permission reference travels as, so that a permission is
 * never an anonymous string being passed around.
 *
 * ON VALIDATION AGAINST THE CATALOGUE: this class deliberately checks the
 * *shape* only, not membership of PermissionCatalog. The catalogue lives
 * in Infrastructure and the domain may not import it (ADR-002, ADR-012) —
 * a domain object that could not be constructed without Infrastructure
 * would invert the dependency the whole layering exists to protect.
 * Membership is asserted where the boundary permits it: the roles use
 * cases reject unknown names, and an architecture test asserts every
 * permission reference in the codebase resolves to a catalogue entry. The
 * two together give the guarantee, without the domain learning where the
 * catalogue is stored.
 */
final readonly class PermissionName
{
    /**
     * resource.action, both snake_case. Kept in step with the catalogue's
     * own consistency test, which asserts the same shape from the other
     * side.
     */
    private const PATTERN = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*\.[a-z][a-z0-9]*(_[a-z0-9]+)*$/';

    public function __construct(
        public string $value,
    ) {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(
                "Invalid permission name: '{$value}'. Expected snake_case resource.action."
            );
        }
    }

    /** The group a roles UI renders this under — derived, never stored. */
    public function resource(): string
    {
        return explode('.', $this->value)[0];
    }

    public function action(): string
    {
        return explode('.', $this->value)[1];
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, self>
     */
    public static function fromMany(array $names): array
    {
        return array_map(static fn (string $name): self => new self($name), array_values($names));
    }

    /**
     * @param  array<int, self>  $permissions
     * @return array<int, string>
     */
    public static function toStrings(array $permissions): array
    {
        return array_map(static fn (self $p): string => $p->value, array_values($permissions));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
