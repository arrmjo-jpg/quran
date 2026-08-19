<?php

declare(strict_types=1);

uses()->group('core', 'architecture', 'identity');

/*
|--------------------------------------------------------------------------
| Accounts are never destroyed — ADR-016 D5, Q6
|--------------------------------------------------------------------------
|
| The board settled Q6 as the narrow reading: the platform does not hard-delete
| accounts, and that is already true — so this epic builds no retention policy,
| no purge mechanism and no expiry periods. Those remain a separate project if
| they are ever wanted.
|
| What this file adds is the part a document cannot do: it makes the decision
| enforced rather than described. The identity epic showed what the gap between
| the two costs — ADR-015 named two audit events for months while the code
| emitted neither, and nothing failed, because nothing was checking.
|
| Two different regressions are guarded, and the second is the one that would
| actually happen:
|
|   A. Someone calls forceDelete() on an account.
|   B. Someone removes the SoftDeletes trait from an account model — after
|      which every existing ->delete() call becomes a permanent delete, with
|      no new code written anywhere and no obvious diff to review.
|
| B is the quiet one. A grep for forceDelete would never find it.
*/

/**
 * The models that represent a person. Records and assets are deliberately
 * excluded: an application, a video or an announcement may one day have a
 * legitimate reason to be destroyed, and Q6 was answered about accounts.
 *
 * @var array<string, string>
 */
const ACCOUNT_MODELS = [
    'UserModel' => 'Modules/Core/Infrastructure/Database/Models/UserModel.php',
    'ContestantModel' => 'Modules/Contestants/Infrastructure/Database/Models/ContestantModel.php',
    'JudgeModel' => 'Modules/Judges/Infrastructure/Database/Models/JudgeModel.php',
];

/**
 * The one permitted hard delete, named with its reason.
 *
 * AdminMediaController destroys a media asset together with its file on disk.
 * Once the file is gone the row cannot be meaningfully restored, so soft
 * deleting it would leave a record pointing at nothing — the delete is the
 * honest operation, not the exception to a rule.
 *
 * Anything added here is a decision someone has to defend in review, which is
 * the entire point of keeping the list short.
 *
 * @var array<string, string>
 */
const PERMITTED_HARD_DELETES = [
    'Modules/Media/Presentation/HTTP/Controllers/AdminMediaController.php' => 'destroys the stored file alongside the row; a soft delete would leave a row pointing at nothing',
];

/**
 * @return array<int, string>
 */
function applicationPhpFiles(): array
{
    $files = [];

    foreach ([base_path('Modules'), base_path('app'), base_path('database')] as $root) {
        if (! is_dir($root)) {
            continue;
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            // Tests are excluded on purpose. A test that destroys its own
            // fixture is housekeeping, and forbidding it would push people
            // toward leaving rows behind instead — which makes suites
            // interfere with each other, a worse problem than this one.
            if (str_contains($relative, '/Tests/')) {
                continue;
            }

            $files[] = $relative;
        }
    }

    return $files;
}

test('the account models still soft delete', function (): void {
    // The quiet regression: drop the trait and every existing ->delete()
    // silently becomes permanent. No new call appears anywhere to review.
    foreach (ACCOUNT_MODELS as $name => $path) {
        $full = base_path($path);

        expect(file_exists($full))->toBeTrue("{$name} is no longer at {$path} — update ACCOUNT_MODELS.");

        $source = (string) file_get_contents($full);

        // The trait must be USED inside the class, not merely imported.
        //
        // The first version searched the whole file for "SoftDeletes", which
        // the top-level `use Illuminate\Database\Eloquent\SoftDeletes;` import
        // satisfies by itself — so deleting the trait from the class body left
        // this guard green. It was caught by deliberately removing the trait
        // and watching the test pass, which is the whole reason for making a
        // guard fail before trusting it.
        //
        // Written as a line scan rather than a regex on purpose: the pattern
        // that replaced it needed escaping through three layers and broke
        // silently, which is exactly the failure this file exists to prevent.
        $usesTrait = false;

        foreach (explode("\n", str_replace("\r", '', $source)) as $line) {
            // A top-level import starts at column 0; a trait use inside a
            // class body is indented.
            if (str_starts_with($line, 'use ')) {
                continue;
            }

            if (str_contains($line, 'SoftDeletes') && str_contains(ltrim($line), 'use ')) {
                $usesTrait = true;
                break;
            }
        }

        expect($usesTrait)->toBeTrue(
            "{$name} no longer uses the SoftDeletes trait. Deleting an account would now be permanent (ADR-016 D5)."
        );
    }
});

test('no account is ever hard deleted', function (): void {
    $offenders = [];

    foreach (applicationPhpFiles() as $relative) {
        if (array_key_exists($relative, PERMITTED_HARD_DELETES)) {
            continue;
        }

        $source = (string) file_get_contents(base_path($relative));

        if (str_contains($source, 'forceDelete')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBeEmpty(
        "forceDelete() outside the permitted list (ADR-016 D5):\n  ".implode("\n  ", $offenders).
        "\n\nIf this is deliberate, add the file to PERMITTED_HARD_DELETES with the reason."
    );
});

test('every permitted hard delete still exists and still needs its exemption', function (): void {
    // An exemption is a liability the moment it stops being needed: it is a
    // hole in the scan above that nobody is looking at any more.
    foreach (PERMITTED_HARD_DELETES as $path => $reason) {
        $full = base_path($path);

        expect(file_exists($full))->toBeTrue("{$path} no longer exists — remove it from PERMITTED_HARD_DELETES.");
        expect(str_contains((string) file_get_contents($full), 'forceDelete'))->toBeTrue(
            "{$path} no longer calls forceDelete — remove it from PERMITTED_HARD_DELETES."
        );
        expect($reason)->not->toBe('', "{$path} is exempt without a stated reason.");
    }
});

test('the raw query builder is not used to delete accounts either', function (): void {
    // forceDelete is not the only way round SoftDeletes. DB::table('users')
    // ->delete() bypasses Eloquent entirely and leaves no trace in the model
    // layer at all, so the ban would be trivially escapable without this.
    //
    // Migrations are excluded: dropping or rebuilding a table is schema work,
    // not an account deletion, and a migration that removes the users table is
    // a change nobody merges by accident.
    $tables = ['users', 'contestants', 'judges'];
    $offenders = [];

    foreach (applicationPhpFiles() as $relative) {
        if (str_contains($relative, 'database/migrations') || str_contains($relative, '/Migrations/')) {
            continue;
        }

        $source = (string) file_get_contents(base_path($relative));

        foreach ($tables as $table) {
            $pattern = '/DB::table\(\s*[\'"]'.$table.'[\'"]\s*\)[^;]{0,200}->delete\(/s';

            if (preg_match($pattern, $source)) {
                $offenders[] = "{$relative} (DB::table('{$table}')->delete())";
            }
        }
    }

    expect($offenders)->toBeEmpty(
        "Raw deletes against account tables (ADR-016 D5):\n  ".implode("\n  ", $offenders)
    );
});
