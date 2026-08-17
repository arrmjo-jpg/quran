<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;

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

        UserModel::query()->updateOrCreate(
            ['email' => 'admin@quran.test'],
            [
                'id' => '00000000-0000-0000-0000-000000000001',
                'name' => 'Platform Admin',
                'type' => 'admin',
                'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
                'is_active' => true,
            ]
        );
    }
}
