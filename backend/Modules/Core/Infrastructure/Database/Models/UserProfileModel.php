<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the user_profiles table — ADR-016 D13.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $display_name
 * @property string|null $bio
 * @property string|null $avatar_media_id
 * @property array<string, string>|null $social_links
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserProfileModel extends Model
{
    protected $table = 'user_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'display_name',
        'bio',
        'avatar_media_id',
        'social_links',
    ];

    protected $casts = [
        // Cast so the column arrives as an array rather than a JSON string.
        // SocialLinks::fromArray is the only thing that reads it, and handing
        // it a string would be a decode nobody wrote.
        'social_links' => 'array',
    ];
}
