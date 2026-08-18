<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonRuleVersionModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

/**
 * The point of this file: configure a season using nothing but the admin
 * HTTP surface, then open registration. Every other Season test reaches
 * into the domain or the repository to reach an activatable season,
 * because until Stage management and the Stage Rules API existed there was
 * no way to configure stages through the API at all. If this file passes,
 * the season lifecycle is genuinely usable end to end.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'open-registration');

function openRegAdmin(): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'open-reg-admin-'.Str::random(8).'@quran.test',
        'name' => 'Open Reg Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

/** @return array{participation_type: string, tajweed_level: string, country: string, score_system: string} */
function openRegLookups(): array
{
    $scoreSystem = JudgeScoreSystemModel::query()->create([
        'id' => (string) Str::uuid(), 'code' => 'out_of_100', 'max_score' => 100,
        'display_order' => 1, 'is_active' => true,
    ]);
    JudgeScoreSystemTranslationModel::query()->create([
        'id' => (string) Str::uuid(), 'judge_score_system_id' => $scoreSystem->id,
        'locale' => 'en', 'name' => 'Out of 100',
    ]);

    return [
        'participation_type' => ParticipationTypeModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'mixed', 'display_order' => 1, 'is_active' => true])->id,
        'tajweed_level' => TajweedLevelModel::query()->create(['id' => (string) Str::uuid(), 'code' => 'advanced', 'display_order' => 1, 'is_active' => true])->id,
        'country' => CountryModel::query()->create(['id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962', 'is_active' => true])->id,
        'score_system' => $scoreSystem->id,
    ];
}

/** @return array<string, mixed> */
function openRegSeasonPayload(): array
{
    $slug = 'e2e-'.Str::random(8);

    return [
        'slug' => $slug, 'year' => 2031,
        'registration_start' => '2031-01-01T00:00:00+00:00',
        'registration_end' => '2031-01-15T00:00:00+00:00',
        'start_date' => '2031-01-16T00:00:00+00:00',
        'end_date' => '2031-03-01T00:00:00+00:00',
        'title_ar' => 'موسم', 'public_name_ar' => 'موسم القرآن',
        'title_en' => 'Season', 'public_name_en' => 'Quran Season',
        'title_es' => 'Temporada', 'public_name_es' => 'Temporada del Corán',
    ];
}

/** @return array<string, mixed> */
function openRegStagePayload(string $name, string $type = 'preliminary'): array
{
    return [
        'type' => $type,
        'start_date' => '2031-02-01T00:00:00+00:00',
        'end_date' => '2031-02-10T00:00:00+00:00',
        'translations' => [
            'ar' => ['name' => $name.' AR', 'public_name' => $name.' علني'],
            'en' => ['name' => $name, 'public_name' => $name.' Public'],
            'es' => ['name' => $name.' ES', 'public_name' => $name.' Público'],
        ],
    ];
}

test('a season configured entirely through the admin API can open registration', function (): void {
    $admin = openRegAdmin();
    $lookups = openRegLookups();

    $seasonId = $this->actingAs($admin)
        ->postJson('/api/v1/admin/seasons', openRegSeasonPayload())
        ->assertStatus(201)
        ->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $lookups['participation_type'],
        'tajweed_level_id' => $lookups['tajweed_level'],
        'country_ids' => [$lookups['country']],
    ])->assertStatus(200);

    $preliminaryId = $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/stages", openRegStagePayload('Preliminary'))
        ->assertStatus(201)->json('data.id');

    $finalId = $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/stages", openRegStagePayload('Final', 'final'))
        ->assertStatus(201)->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $preliminaryId, 'judge_score_system_id' => $lookups['score_system'], 'qualification_percentage' => 60],
            ['stage_id' => $finalId, 'judge_score_system_id' => $lookups['score_system'], 'qualification_percentage' => null],
        ],
    ])->assertStatus(200);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_open')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_frozen', true);

    // The frozen snapshot must carry both stages, with required_score
    // derived from each stage's own score system.
    $version = SeasonRuleVersionModel::query()->where('season_id', $seasonId)->firstOrFail();
    $snapshot = is_array($version->snapshot_json) ? $version->snapshot_json : json_decode((string) $version->snapshot_json, true);

    expect($version->version)->toBe(1);
    expect($snapshot['stages'])->toHaveCount(2);
    expect($snapshot['stages'][0]['stage_number'])->toBe(1);
    // toEqual, not toBe: a whole-numbered float round-trips through
    // snapshot_json as 60, not 60.0.
    expect($snapshot['stages'][0]['required_score'])->toEqual(60.0);
    expect($snapshot['stages'][1]['required_score'])->toBeNull();
    expect($snapshot['eligible_countries'])->toHaveCount(1);
    expect($snapshot['min_age'])->toBe(10);
});

test('open-registration returns 422, not 500, when the season has no stages', function (): void {
    $admin = openRegAdmin();
    $lookups = openRegLookups();

    $seasonId = $this->actingAs($admin)
        ->postJson('/api/v1/admin/seasons', openRegSeasonPayload())
        ->assertStatus(201)->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $lookups['participation_type'],
        'tajweed_level_id' => $lookups['tajweed_level'],
        'country_ids' => [$lookups['country']],
    ])->assertStatus(200);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INCOMPLETE_SEASON_RULES')
        ->assertJsonFragment(['missing' => ['stages']]);
});

test('open-registration returns 422 when a stage was added after the rules were set', function (): void {
    $admin = openRegAdmin();
    $lookups = openRegLookups();

    $seasonId = $this->actingAs($admin)
        ->postJson('/api/v1/admin/seasons', openRegSeasonPayload())
        ->assertStatus(201)->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/rules", [
        'min_age' => 10, 'max_age' => 18,
        'participation_type_id' => $lookups['participation_type'],
        'tajweed_level_id' => $lookups['tajweed_level'],
        'country_ids' => [$lookups['country']],
    ])->assertStatus(200);

    $firstId = $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/stages", openRegStagePayload('Preliminary'))
        ->assertStatus(201)->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $firstId, 'judge_score_system_id' => $lookups['score_system'], 'qualification_percentage' => 60],
        ],
    ])->assertStatus(200);

    // A stage added now has no rule — the rule set was complete when written.
    $lateId = $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/stages", openRegStagePayload('Final', 'final'))
        ->assertStatus(201)->json('data.id');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INCOMPLETE_SEASON_RULES')
        ->assertJsonFragment(['missing' => ["stage_rule.missing.{$lateId}"]]);
});

test('open-registration returns 422, not 500, when no country is eligible', function (): void {
    $admin = openRegAdmin();
    $lookups = openRegLookups();

    $seasonId = $this->actingAs($admin)
        ->postJson('/api/v1/admin/seasons', openRegSeasonPayload())
        ->assertStatus(201)->json('data.id');

    // Rules set directly rather than through PATCH /rules, which requires
    // at least one country — the empty-country path is only reachable when
    // the season was configured before that endpoint existed.
    $seasons = app(SeasonRepositoryContract::class);
    $season = $seasons->findOrFail($seasonId);
    $season->setAgeRange(10, 18);
    $season->setParticipationType($lookups['participation_type']);
    $season->setTajweedLevel($lookups['tajweed_level']);
    $seasons->save($season);

    $stageId = $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/stages", openRegStagePayload('Final', 'final'))
        ->assertStatus(201)->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $stageId, 'judge_score_system_id' => $lookups['score_system'], 'qualification_percentage' => 60],
        ],
    ])->assertStatus(200);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INCOMPLETE_SEASON_RULES')
        ->assertJsonFragment(['missing' => ['eligible_countries']]);
});
