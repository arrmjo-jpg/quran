<?php

use Illuminate\Contracts\Console\Kernel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = UserModel::query()->updateOrCreate(
    ['email' => 'admin@quran.test'],
    [
        'id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Platform Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'mfa_enabled' => false,
    ]
);

echo 'Admin user reset successfully: '.$user->email."\n";
