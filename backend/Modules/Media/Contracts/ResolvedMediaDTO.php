<?php

declare(strict_types=1);

namespace Modules\Media\Contracts;

/**
 * ResolvedMediaDTO
 *
 * Cross-module read shape for one stored asset: what a screen needs to
 * render it, and nothing about how it is stored. Owned by Media.
 *
 * `disk`, `file_path`, `hash_sha256`, `uploader_id` and `size_bytes` are all
 * absent on purpose. They describe where and how the file lives, which is
 * Media's business; a consumer holding them would be a consumer that could
 * start reasoning about storage layout, and moving a disk would then be a
 * change to every module that ever displayed a picture.
 *
 * `url` is nullable rather than guaranteed. Private disks issue presigned
 * URLs on demand and public ones do not, so an asset can legitimately have
 * no permanent address — and a caller that assumed a string would render a
 * broken image instead of falling back.
 *
 * `thumb` is nullable for a plainer reason: a thumbnail exists only if one
 * was generated. Deriving an address by convention would produce a 404 where
 * showing the full-size image would have worked.
 */
final readonly class ResolvedMediaDTO
{
    public function __construct(
        public string $id,
        public ?string $url,
        public ?string $thumb,
        public string $mimeType,
        public bool $isImage,
    ) {}
}
