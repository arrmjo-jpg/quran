<?php

declare(strict_types=1);

namespace App\Console\Commands\Scaffolding;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * make:platform-event
 *
 * Generates the Domain Event vertical slice per ADR-008:
 *   1. Domain/Events/{EventName}.php                         (Outbox-compatible Event DTO)
 *   2. Application/Listeners/{EventName}Subscriber.php       (Idempotent Listener stub)
 *   3. Tests/Unit/Events/{EventName}Test.php                 (Pest payload schema test)
 *
 * Per ADR-011: All stubs are versioned under stubs/platform/v1/
 *
 * Usage:
 *   php artisan make:platform-event {ModuleName} {EventName}
 *
 * Example:
 *   php artisan make:platform-event Contestants ContestantRegistered
 */
final class MakePlatformEvent extends Command
{
    protected $signature = 'make:platform-event
        {module : The PascalCase module name (e.g. Contestants)}
        {event  : The PascalCase event name in past tense (e.g. ContestantRegistered)}';

    protected $description = 'Generate a Domain Event DTO, Listener stub, and Pest schema test (ADR-008 / ADR-011)';

    public function handle(): int
    {
        $module = $this->argument('module');
        $event = $this->argument('event');

        $modulePath = base_path("Modules/{$module}");

        if (! is_dir($modulePath)) {
            $this->error("Module '{$module}' does not exist. Run: php artisan make:platform-module {$module}");

            return self::FAILURE;
        }

        // Enforce past-tense naming convention per ADR-008
        if (! $this->isPastTense($event)) {
            $this->warn('⚠️  Event names MUST be past tense per ADR-008 (e.g. ContestantRegistered, not ContestantRegister).');
            if (! $this->confirm('Continue anyway?', false)) {
                return self::FAILURE;
            }
        }

        $this->info("📡  Generating Domain Event: <comment>{$event}</comment> in module <comment>{$module}</comment>");
        $this->newLine();

        $tokens = $this->buildTokens($module, $event);

        $this->createEventDto($modulePath, $event, $tokens);
        $this->createListener($modulePath, $event, $tokens);
        $this->createSchemaTest($modulePath, $event, $tokens);

        $this->newLine();
        $this->info("✅  Domain Event <comment>{$event}</comment> scaffolded in <comment>{$module}</comment>.");
        $this->line("   Remember to register the listener in <comment>{$module}ServiceProvider::registerOutboxSubscribers()</comment>");

        return self::SUCCESS;
    }

    private function createEventDto(string $modulePath, string $event, array $tokens): void
    {
        $content = $this->render('event-dto', $tokens);
        $file = "{$modulePath}/Domain/Events/{$event}.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Event DTO: Domain/Events/{$event}.php");
    }

    private function createListener(string $modulePath, string $event, array $tokens): void
    {
        $content = $this->render('event-listener', $tokens);
        $file = "{$modulePath}/Application/Listeners/{$event}Subscriber.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Listener: Application/Listeners/{$event}Subscriber.php");
    }

    private function createSchemaTest(string $modulePath, string $event, array $tokens): void
    {
        // Ensure the Events sub-dir exists inside Unit
        $testDir = "{$modulePath}/Tests/Unit/Events";
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $content = $this->render('event-schema-test', $tokens);
        $file = "{$testDir}/{$event}Test.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Payload Schema Test: Tests/Unit/Events/{$event}Test.php");
    }

    private function render(string $stubName, array $tokens): string
    {
        $stubPath = base_path("stubs/platform/v1/{$stubName}.stub");

        if (! file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: {$stubPath}");
        }

        $stub = file_get_contents($stubPath);

        return str_replace(array_keys($tokens), array_values($tokens), $stub);
    }

    /** @return array<string, string> */
    private function buildTokens(string $module, string $event): array
    {
        return [
            '{{ModuleName}}' => $module,
            '{{moduleLower}}' => Str::lower($module),
            '{{moduleSnake}}' => Str::snake($module),
            '{{EventName}}' => $event,
            '{{eventLower}}' => Str::lower($event),
            '{{eventSnake}}' => Str::snake($event),
            '{{eventType}}' => Str::snake($event),  // e.g. contestant_registered
        ];
    }

    /**
     * Heuristic check for past-tense naming (ends in -ed, -d, -en, -t, -n).
     * Not exhaustive — just catches common mistakes.
     */
    private function isPastTense(string $name): bool
    {
        return (bool) preg_match('/(ed|ied|en|[^aeiou]t|[^aeiou]n)$/i', $name);
    }
}
