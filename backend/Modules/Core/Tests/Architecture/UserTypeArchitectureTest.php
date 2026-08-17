<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'architecture', 'identity');

/*
|--------------------------------------------------------------------------
| users.type — ADR-015 §1
|--------------------------------------------------------------------------
| `type` is the authentication surface and has exactly two values. Before
| ADR-015 the contestant value was 'user', which said nothing useful on a
| table where every row is a user. These tests keep the rename permanent:
| the first two pin the allowed set, and the third is the one that matters
| in six months, when someone reaches for the old literal out of habit.
*/

test('UserType permits exactly contestant and admin', function (): void {
    expect(UserType::ALLOWED)->toBe(['contestant', 'admin']);
});

test('every persisted user type is a permitted value', function (): void {
    UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'arch-contestant@quran.test',
        'name' => 'Arch Contestant',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'arch-admin@quran.test',
        'name' => 'Arch Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $stored = UserModel::query()->pluck('type')->unique()->values()->all();

    foreach ($stored as $type) {
        expect(UserType::ALLOWED)->toContain($type);
    }
});

test("no source file uses the string 'user' as a type value", function (): void {
    // Scans the two places a type value is written or compared: the
    // modules and the app kernel. Response payloads legitimately contain a
    // 'user' KEY (e.g. ['token' => ..., 'user' => ...]), so the patterns
    // below match only the value positions — a comparison against type, a
    // named argument, or a 'type' array key.
    $patterns = [
        "/type\s*(===|==|!==|!=)\s*'user'/",   // $x->type === 'user'
        "/type:\s*'user'/",                     // type: 'user'
        "/'type'\s*=>\s*'user'/",               // 'type' => 'user'
    ];

    $roots = [base_path('Modules'), base_path('app')];
    $violations = [];

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // Two files legitimately contain the old literal: the rename
            // migration, whose down() must restore it, and this file,
            // which spells the patterns out. Excluding them by name keeps
            // the patterns strict rather than weakening them to pass.
            if (str_contains($path, 'rename_user_type_to_contestant') || $path === __FILE__) {
                continue;
            }

            $content = (string) file_get_contents($path);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    $violations[] = $path;
                    break;
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "These files still use 'user' as a type value; ADR-015 renamed it to 'contestant': "
        .implode(', ', $violations)
    );
});
