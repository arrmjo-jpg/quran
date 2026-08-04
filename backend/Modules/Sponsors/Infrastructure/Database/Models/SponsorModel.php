<?php

declare(strict_types=1);

namespace Modules\Sponsors\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $tier
 * @property string|null $logo_media_id
 * @property string|null $website_url
 * @property int $display_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereDisplayOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereLogoMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SponsorModel whereWebsiteUrl($value)
 *
 * @mixin \Eloquent
 */
final class SponsorModel extends Model
{
    protected $table = 'sponsors';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'tier',
        'logo_media_id',
        'website_url',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
