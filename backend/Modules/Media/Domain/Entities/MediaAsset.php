<?php

declare(strict_types=1);

namespace Modules\Media\Domain\Entities;

use Modules\Media\Domain\ValueObjects\MediaAssetId;

/**
 * MediaAsset Aggregate Root
 *
 * Governs stored file assets on private or public storage disks per ADR-005/ADR-006.
 */
final class MediaAsset
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    /**
     * @param  array<string, mixed>  $customProperties
     */
    public function __construct(
        public readonly MediaAssetId $id,
        public readonly ?string $uploaderId,
        public readonly string $disk, // 'r2_private', 'r2_public'
        public readonly string $filePath,
        public readonly string $fileName,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly ?string $hashSha256 = null,
        public readonly string $collection = 'default',
        public readonly array $customProperties = [],
        private ?string $deletedAt = null,
    ) {}

    public static function create(
        MediaAssetId $id,
        ?string $uploaderId,
        string $disk,
        string $filePath,
        string $fileName,
        string $mimeType,
        int $sizeBytes,
        ?string $hashSha256 = null,
        string $collection = 'default',
        array $customProperties = []
    ): self {
        return new self(
            id: $id,
            uploaderId: $uploaderId,
            disk: $disk,
            filePath: $filePath,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            hashSha256: $hashSha256,
            collection: $collection,
            customProperties: $customProperties
        );
    }

    public function isPrivate(): bool
    {
        return str_contains($this->disk, 'private');
    }

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
