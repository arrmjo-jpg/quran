<?php

declare(strict_types=1);

use Modules\Core\Domain\ValueObjects\SocialLinks;

uses()->group('core', 'unit', 'identity', 'profiles');

/*
|--------------------------------------------------------------------------
| SocialLinks — ADR-016 D2, Q5
|--------------------------------------------------------------------------
|
| A unit test with no database: the whole point of the value object is that it
| refuses bad input before anything reaches storage, so a test that needed a
| table would be testing the wrong layer.
*/

test('the platform set is closed, and it is the eight Q5 named', function (): void {
    expect(SocialLinks::platforms())->toBe([
        'website', 'x', 'linkedin', 'facebook', 'instagram', 'youtube', 'telegram', 'tiktok',
    ]);
});

test('an unknown platform is refused', function (): void {
    // The reason the set is closed rather than open: without this, "githbu"
    // is stored happily and nobody finds out.
    expect(fn () => SocialLinks::fromArray(['github' => 'https://github.com/someone']))
        ->toThrow(InvalidArgumentException::class, 'Unknown social platform: github');
});

test('null and an empty array both mean no links', function (): void {
    expect(SocialLinks::fromArray(null)->isEmpty())->toBeTrue()
        ->and(SocialLinks::fromArray([])->isEmpty())->toBeTrue()
        ->and(SocialLinks::empty()->toArray())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Q5: an unset platform has no key
|--------------------------------------------------------------------------
*/

test('only the platforms that were set appear', function (): void {
    $links = SocialLinks::fromArray([
        'website' => 'https://example.com',
        'linkedin' => 'https://linkedin.com/in/admin',
    ]);

    expect($links->toArray())->toBe([
        'website' => 'https://example.com',
        'linkedin' => 'https://linkedin.com/in/admin',
    ])
        ->and($links->has('facebook'))->toBeFalse()
        ->and($links->get('facebook'))->toBeNull()
        ->and($links->toArray())->not->toHaveKey('facebook');
});

test('a key with nothing behind it is refused rather than treated as unset', function (): void {
    // Sending `"facebook": null` is a caller believing the JSON has a fixed
    // shape. Accepting it silently would let that belief spread.
    expect(fn () => SocialLinks::fromArray(['facebook' => null]))
        ->toThrow(InvalidArgumentException::class, 'Omit the key entirely');

    expect(fn () => SocialLinks::fromArray(['facebook' => '']))
        ->toThrow(InvalidArgumentException::class, 'Omit the key entirely');
});

/*
|--------------------------------------------------------------------------
| Per-platform host validation
|--------------------------------------------------------------------------
*/

test('each platform accepts its own host', function (string $platform, string $url): void {
    expect(SocialLinks::fromArray([$platform => $url])->get($platform))->toBe($url);
})->with([
    ['website', 'https://example.com/about'],
    ['x', 'https://x.com/someone'],
    ['linkedin', 'https://linkedin.com/in/someone'],
    ['facebook', 'https://facebook.com/someone'],
    ['instagram', 'https://instagram.com/someone'],
    ['youtube', 'https://youtube.com/@someone'],
    ['telegram', 'https://t.me/someone'],
    ['tiktok', 'https://tiktok.com/@someone'],
]);

test('a link pointing at the wrong platform is refused', function (): void {
    expect(fn () => SocialLinks::fromArray(['linkedin' => 'https://facebook.com/someone']))
        ->toThrow(InvalidArgumentException::class, 'must point at linkedin.com');
});

test('a host that merely ends with the platform name is refused', function (): void {
    // The check is anchored on a leading dot precisely so this cannot pass.
    expect(fn () => SocialLinks::fromArray(['linkedin' => 'https://notlinkedin.com/in/someone']))
        ->toThrow(InvalidArgumentException::class, 'must point at linkedin.com');
});

test('a subdomain of the platform is accepted', function (string $url): void {
    expect(SocialLinks::fromArray(['linkedin' => $url])->get('linkedin'))->toBe($url);
})->with([
    'https://www.linkedin.com/in/someone',
    'https://ae.linkedin.com/in/someone',
]);

test('a string that is not a URL at all is refused', function (): void {
    expect(fn () => SocialLinks::fromArray(['linkedin' => 'linkedin.com/in/someone']))
        ->toThrow(InvalidArgumentException::class, 'not a valid URL');
});

/*
|--------------------------------------------------------------------------
| x accepts both hosts, and neither is rewritten
|--------------------------------------------------------------------------
*/

test('x accepts both x.com and twitter.com', function (string $url): void {
    expect(SocialLinks::fromArray(['x' => $url])->get('x'))->toBe($url);
})->with([
    'https://x.com/someone',
    'https://twitter.com/someone',
]);

test('a twitter.com link is stored exactly as entered', function (): void {
    // The decision recorded in ADR-016 Q5: the value object validates, it does
    // not rewrite. This test is what would fail if someone added a helpful
    // normalisation later without changing the ADR first.
    $url = 'https://twitter.com/someone';

    expect(SocialLinks::fromArray(['x' => $url])->get('x'))->toBe($url)
        ->and(SocialLinks::fromArray(['x' => $url])->toArray())->toBe(['x' => $url]);
});

/*
|--------------------------------------------------------------------------
| Scheme rules
|--------------------------------------------------------------------------
*/

test('website must be https', function (): void {
    expect(fn () => SocialLinks::fromArray(['website' => 'http://example.com']))
        ->toThrow(InvalidArgumentException::class, 'must use https');
});

test('website accepts any host, which is what makes it the website field', function (): void {
    $url = 'https://some-personal-domain.example/page';

    expect(SocialLinks::fromArray(['website' => $url])->get('website'))->toBe($url);
});

test('a non-web scheme is refused even on the right host', function (): void {
    expect(fn () => SocialLinks::fromArray(['telegram' => 'ftp://t.me/someone']))
        ->toThrow(InvalidArgumentException::class, 'must be an http or https URL');
});

/*
|--------------------------------------------------------------------------
| Value semantics
|--------------------------------------------------------------------------
*/

test('two sets carrying the same links are equal', function (): void {
    $a = SocialLinks::fromArray(['x' => 'https://x.com/someone']);
    $b = SocialLinks::fromArray(['x' => 'https://x.com/someone']);
    $c = SocialLinks::fromArray(['x' => 'https://twitter.com/someone']);

    expect($a->equals($b))->toBeTrue()
        // Not normalising means these two are genuinely different values, and
        // the equality check must say so rather than paper over it.
        ->and($a->equals($c))->toBeFalse();
});

test('a validated set round-trips through toArray', function (): void {
    $input = [
        'website' => 'https://example.com',
        'x' => 'https://x.com/someone',
        'telegram' => 'https://t.me/someone',
    ];

    expect(SocialLinks::fromArray(SocialLinks::fromArray($input)->toArray())->toArray())->toBe($input);
});
