<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data: idempotent, safe on every deploy (ADR-015 §4.3).
        $this->call(PermissionsSeeder::class);
        $this->call(RolesSeeder::class);

        UserModel::query()->updateOrCreate(
            ['email' => 'admin@quran.test'],
            [
                'id' => '01920000-0000-7000-8000-000000000001',
                'name' => 'Platform Admin',
                'type' => 'admin',
                'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
                'is_active' => true,
            ]
        );
    }
}
