<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Media\Contracts\MediaServiceContract;

uses(RefreshDatabase::class)->group('media', 'feature');

/*
|--------------------------------------------------------------------------
| The Media boundary — Epic 4 Story 4 (ADR-016 D24)
|--------------------------------------------------------------------------
|
| Media's first cross-module method, following the shape Countries
| established and Core, Organization and Contestants took after it.
|
| It exists because `contestants.photo_media_asset_id` has always been a bare
| UUID that nothing could turn into anything displayable. What is worth
| testing is not that a row comes back — it is the three cases a caller will
| actually meet: a resolvable asset, an id pointing at nothing, and a batch
| that must not become an N+1.
*/

function mediaAsset(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('media_assets')->insert(array_merge([
        'id' => $id,
        'uploader_id' => null,
        'disk' => 'public',
        'file_path' => 'contestants/'.Str::random(8).'.jpg',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 12345,
        'hash_sha256' => hash('sha256', $id),
        'collection' => 'contestant_photos',
        'custom_properties' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

test('an asset resolves to something a screen can render', function (): void {
    $id = mediaAsset();

    $resolved = app(MediaServiceContract::class)->findResolvedByIds([$id]);

    expect($resolved)->toHaveKey($id);

    $asset = $resolved[$id];

    expect($asset->id)->toBe($id);
    expect($asset->mimeType)->toBe('image/jpeg');
    expect($asset->isImage)->toBeTrue();

    // The whole point of the boundary: a url, not an id.
    expect($asset->url)->toBeString();
    expect($asset->url)->toContain('.jpg');
});

test('the thumbnail comes from custom_properties, and is null when absent', function (): void {
    $withThumb = mediaAsset([
        'custom_properties' => json_encode(['thumb_url' => 'https://cdn.test/thumb.jpg']),
    ]);
    $withoutThumb = mediaAsset();

    $resolved = app(MediaServiceContract::class)->findResolvedByIds([$withThumb, $withoutThumb]);

    expect($resolved[$withThumb]->thumb)->toBe('https://cdn.test/thumb.jpg');

    // Null rather than a guessed convention. A thumbnail that was never
    // generated has no address, and inventing one produces a broken image
    // where the full-size picture would have worked.
    expect($resolved[$withoutThumb]->thumb)->toBeNull();
});

test('a non-image asset says so rather than being assumed', function (): void {
    $id = mediaAsset(['mime_type' => 'application/pdf', 'file_name' => 'certificate.pdf']);

    $resolved = app(MediaServiceContract::class)->findResolvedByIds([$id]);

    expect($resolved[$id]->isImage)->toBeFalse();
});

test('an id that matches nothing is simply absent', function (): void {
    $resolved = app(MediaServiceContract::class)->findResolvedByIds([(string) Str::uuid()]);

    // Absent, not a null placeholder. The caller decides what a missing
    // asset means in its own context — for a contestant photo it means
    // "no picture", which is different from "the asset is broken".
    expect($resolved)->toBe([]);
});

test('a soft-deleted asset does not resolve', function (): void {
    $id = mediaAsset();
    DB::table('media_assets')->where('id', $id)->update(['deleted_at' => now()]);

    expect(app(MediaServiceContract::class)->findResolvedByIds([$id]))->toBe([]);

    // A deleted asset has no file to point at, so returning a url for it
    // would hand a screen an address that 404s.
});

test('an empty batch short-circuits', function (): void {
    expect(app(MediaServiceContract::class)->findResolvedByIds([]))->toBe([]);
});

test('resolving many assets costs one query', function (): void {
    $ids = [];

    foreach (range(1, 6) as $ignored) {
        $ids[] = mediaAsset();
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $resolved = app(MediaServiceContract::class)->findResolvedByIds($ids);

    $queries = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($resolved)->toHaveCount(6);

    // The reason the method takes an array. A per-id version would be an
    // N+1 the first time anything renders a list of contestants with photos.
    expect($queries)->toBe(1);
});
