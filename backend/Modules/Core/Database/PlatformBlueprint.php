<?php

declare(strict_types=1);

namespace Modules\Core\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * PlatformBlueprint
 *
 * Reusable schema blueprint helper for platform migrations.
 * Enforces InnoDB, utf8mb4_unicode_ci, UUID v7 primary keys, and index conventions.
 */
final class PlatformBlueprint
{
    /**
     * Set default engine, charset, and collation on a table blueprint.
     */
    public static function defaults(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';
    }

    /**
     * Add UUID primary key 'id' to table blueprint.
     */
    public static function uuidPrimary(Blueprint $table, string $column = 'id'): void
    {
        self::defaults($table);
        $table->uuid($column)->primary();
    }

    /**
     * Add soft deletes column with standard index naming convention.
     */
    public static function softDeletes(Blueprint $table): void
    {
        $table->softDeletes()->index('idx_'.$table->getTable().'_deleted_at');
    }

    /**
     * Foreign key helper for media_assets table.
     */
    public static function mediaForeign(Blueprint $table, string $column = 'media_asset_id', bool $nullable = false): void
    {
        $col = $table->uuid($column);
        if ($nullable) {
            $col->nullable();
        }
        $table->foreign($column, 'fk_'.$table->getTable().'_'.$column)
            ->references('id')
            ->on('media_assets')
            ->onDelete('RESTRICT');
    }

    /**
     * Foreign key helper for seasons table.
     */
    public static function seasonForeign(Blueprint $table, string $column = 'season_id', bool $nullable = false): void
    {
        $col = $table->uuid($column);
        if ($nullable) {
            $col->nullable();
        }
        $table->foreign($column, 'fk_'.$table->getTable().'_'.$column)
            ->references('id')
            ->on('seasons')
            ->onDelete('RESTRICT');
    }
}
