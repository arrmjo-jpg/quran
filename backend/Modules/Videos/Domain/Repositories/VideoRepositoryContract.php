<?php

declare(strict_types=1);

namespace Modules\Videos\Domain\Repositories;

use Modules\Videos\Domain\Entities\Video;
use Modules\Videos\Domain\ValueObjects\VideoId;

interface VideoRepositoryContract
{
    public function findOrFail(VideoId $id): Video;

    public function findByApplicationId(string $appId): ?Video;

    public function save(Video $video): void;
}
