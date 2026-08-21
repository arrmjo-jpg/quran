<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * An administrator's social links — ADR-016 D2, Q5.
 *
 * A CLOSED SET OF EIGHT, and that is the whole reason this is a value object
 * rather than a JSON column. Q5 settled it: an open list would make this an
 * `array<string, string>` wearing a type name, and adding a platform would be
 * a value someone writes into JSON that nothing checks. Here it is a change to
 * this file, reviewed like any other.
 *
 * INVALID STATE CANNOT EXIST. There is one way in — fromArray() — and it
 * either returns a fully validated value or throws. No setter, no mutation, no
 * partially-built instance that some caller has to remember to check.
 *
 * WHAT IT DOES NOT DO, deliberately:
 *
 *   It does not normalise. A URL entered as `twitter.com/...` is stored as it
 *   was entered. Accepting both hosts for `x` is a compatibility decision, not
 *   a data-migration one, and rewriting a caller's input during validation
 *   would make every save a silent edit. Unifying the host later is a
 *   migration — reviewable and reversible — not a side effect of a constructor.
 *
 *   It does not carry keys for platforms nobody set. `toArray()` returns the
 *   links that exist and nothing else, so a profile with one link is one key,
 *   not one key and seven nulls.
 */
final readonly class SocialLinks
{
    /**
     * The closed set, and the host each link must belong to.
     *
     * `website` has no host restriction — it is the field for whatever else a
     * person wants to point at — but it must be https, which the others need
     * not be. That asymmetry is Q5's, not an oversight: an arbitrary host is
     * exactly where an insecure scheme matters most.
     *
     * `x` carries two hosts because the rename is still in progress and
     * refusing `twitter.com` would reject links that work today.
     *
     * @var array<string, array<int, string>>
     */
    private const PLATFORM_HOSTS = [
        'website' => [],
        'x' => ['x.com', 'twitter.com'],
        'linkedin' => ['linkedin.com'],
        'facebook' => ['facebook.com'],
        'instagram' => ['instagram.com'],
        'youtube' => ['youtube.com'],
        'telegram' => ['t.me'],
        'tiktok' => ['tiktok.com'],
    ];

    /** @param array<string, string> $links validated, and carrying only the platforms that are set */
    private function __construct(
        private array $links,
    ) {}

    /** @return array<int, string> */
    public static function platforms(): array
    {
        return array_keys(self::PLATFORM_HOSTS);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * The only way in.
     *
     * @param  array<string, mixed>|null  $links  null and [] both mean "no links"
     *
     * @throws InvalidArgumentException on an unknown platform or an unacceptable URL
     */
    public static function fromArray(?array $links): self
    {
        if ($links === null || $links === []) {
            return new self([]);
        }

        $validated = [];

        foreach ($links as $platform => $url) {
            if (! is_string($platform) || ! array_key_exists($platform, self::PLATFORM_HOSTS)) {
                throw new InvalidArgumentException(
                    "Unknown social platform: {$platform}. Accepted: ".implode(', ', self::platforms()).'.'
                );
            }

            // A key present with nothing behind it is a contradiction: Q5 says
            // an unset platform has no key, so a key must carry a link.
            if ($url === null || $url === '') {
                throw new InvalidArgumentException(
                    "The {$platform} link is empty. Omit the key entirely rather than sending a blank value."
                );
            }

            if (! is_string($url)) {
                throw new InvalidArgumentException("The {$platform} link must be a string.");
            }

            self::assertAcceptable($platform, $url);

            // Stored exactly as given — see the class docblock on normalisation.
            $validated[$platform] = $url;
        }

        return new self($validated);
    }

    public function get(string $platform): ?string
    {
        return $this->links[$platform] ?? null;
    }

    public function has(string $platform): bool
    {
        return isset($this->links[$platform]);
    }

    public function isEmpty(): bool
    {
        return $this->links === [];
    }

    /**
     * The links that exist, and no key for the ones that do not.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->links;
    }

    public function equals(self $other): bool
    {
        return $this->links === $other->links;
    }

    private static function assertAcceptable(string $platform, string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException("The {$platform} link is not a valid URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        $allowedSchemes = $platform === 'website' ? ['https'] : ['http', 'https'];

        if (! in_array($scheme, $allowedSchemes, true)) {
            throw new InvalidArgumentException(
                $platform === 'website'
                    ? "The website link must use https: {$url}"
                    : "The {$platform} link must be an http or https URL: {$url}"
            );
        }

        $hosts = self::PLATFORM_HOSTS[$platform];

        if ($hosts === []) {
            return;
        }

        foreach ($hosts as $allowed) {
            // The host itself, or a subdomain of it. `www.linkedin.com` and
            // `ae.linkedin.com` are the same platform as `linkedin.com`, and
            // rejecting a URL a person pasted from their own browser would be
            // a rule nobody could guess. The check is anchored on a leading dot
            // so `notlinkedin.com` cannot pass as a subdomain.
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return;
            }
        }

        throw new InvalidArgumentException(
            "The {$platform} link must point at ".implode(' or ', $hosts).": {$url}"
        );
    }
}
