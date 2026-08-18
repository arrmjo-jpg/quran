<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Applications\Infrastructure\Database\Models\ApplicationModel;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;
use Modules\Contestants\Infrastructure\Database\Models\ContestantModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Judges\Infrastructure\Database\Models\JudgeModel;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;

final class EvaluationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $type = 'user'): UserModel
    {
        return UserModel::query()->create([
            'id' => (string) Uuid::v4(),
            'email' => $email,
            'name' => 'Evaluations Test User',
            'type' => $type,
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);
    }

    private function makeJudge(UserModel $user): JudgeModel
    {
        return JudgeModel::query()->create([
            'id' => (string) Uuid::v7(),
            'user_id' => $user->id,
            'full_name' => 'Test Judge',
            'specialization' => 'tajweed',
            'is_active' => true,
        ]);
    }

    /** @return array{application_id: string, contestant_id: string, season_id: string} */
    private function makeApplication(): array
    {
        $contestantUser = $this->makeUser('eval-contestant-'.Uuid::v4().'@quran.test');

        $countryRepo = app(CountryRepositoryContract::class);
        (new CountriesSeeder)->run($countryRepo);
        $country = $countryRepo->findByIso2(new CountryIso2('JO'));

        $contestantRepo = app(ContestantRepositoryContract::class);
        $contestant = Contestant::create(
            id: ContestantId::generate(),
            userId: $contestantUser->id,
            countryId: $country->id->value,
            fullName: 'Eval Test Contestant',
            dateOfBirth: new BirthDate('1998-06-15'),
            gender: new Gender('male'),
            phoneNumber: '+962790000020'
        );
        $contestantRepo->save($contestant);

        $seasonRepo = app(SeasonRepositoryContract::class);
        $season = Season::create(
            id: (string) Uuid::v7(),
            slug: 'eval-test-season-'.Uuid::v4(),
            year: 2026,
            regStartIso: '2026-08-01 00:00:00',
            regEndIso: '2026-08-15 23:59:59',
            startDateIso: '2026-08-16 00:00:00',
            endDateIso: '2026-09-30 23:59:59'
        );
        $seasonRepo->save($season);

        $stageId = (string) Uuid::v7();
        DB::table('stages')->insert([
            'id' => $stageId,
            'season_id' => $season->id,
            'stage_number' => 1,
            'type' => 'preliminary',
            'start_date' => now(),
            'end_date' => now()->addDays(10),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $applicationId = (string) Uuid::v7();
        ApplicationModel::query()->create([
            'id' => $applicationId,
            'contestant_id' => $contestant->id->value,
            'season_id' => $season->id,
            'stage_id' => $stageId,
            'application_number' => 'APP-'.strtoupper(substr(md5($applicationId), 0, 8)),
            'status' => 'ready_for_judging',
        ]);

        return ['application_id' => $applicationId, 'contestant_id' => $contestant->id->value, 'season_id' => $season->id];
    }

    private function makeCriterion(string $seasonId, string $code, string $weight = '40.00'): string
    {
        $templateId = (string) Uuid::v7();
        DB::table('evaluation_templates')->insert([
            'id' => $templateId,
            'season_id' => $seasonId,
            'name' => 'Test Template '.$code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $criterionId = (string) Uuid::v7();
        DB::table('evaluation_criteria')->insert([
            'id' => $criterionId,
            'template_id' => $templateId,
            'name' => ucfirst($code),
            'code' => $code,
            'max_score' => 100,
            'weight_percent' => $weight,
            'display_order' => 0,
        ]);

        return $criterionId;
    }

    public function test_judge_can_submit_evaluation_directly_from_draft_without_crashing(): void
    {
        $judgeUser = $this->makeUser('judge-direct-submit@quran.test');
        $judge = $this->makeJudge($judgeUser);
        $app = $this->makeApplication();

        $evaluation = EvaluationModel::query()->create([
            'id' => (string) Uuid::v7(),
            'application_id' => $app['application_id'],
            'judge_id' => $judge->id,
            'total_score' => 0.0,
            'status' => 'draft',
        ]);

        $tajweedCriterion = $this->makeCriterion($app['season_id'], 'tajweed');

        $response = $this->actingAs($judgeUser)->postJson("/api/v1/judge/evaluations/{$evaluation->id}/submit", [
            'criteria_scores' => [
                ['criterion_id' => $tajweedCriterion, 'score' => 85],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');

        expect((float) $response->json('data.total_score'))->toBe(85.0);
    }

    public function test_judge_can_start_then_submit_evaluation(): void
    {
        $judgeUser = $this->makeUser('judge-start-submit@quran.test');
        $judge = $this->makeJudge($judgeUser);
        $app = $this->makeApplication();

        $evaluation = EvaluationModel::query()->create([
            'id' => (string) Uuid::v7(),
            'application_id' => $app['application_id'],
            'judge_id' => $judge->id,
            'total_score' => 0.0,
            'status' => 'draft',
        ]);

        $this->actingAs($judgeUser)->postJson("/api/v1/judge/evaluations/{$evaluation->id}/start")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $criterion = $this->makeCriterion($app['season_id'], 'memorization');

        $this->actingAs($judgeUser)->postJson("/api/v1/judge/evaluations/{$evaluation->id}/submit", [
            'criteria_scores' => [
                ['criterion_id' => $criterion, 'score' => 90],
            ],
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_criteria_scores_are_persisted_and_visible_in_evaluation_scores_table(): void
    {
        $judgeUser = $this->makeUser('judge-persist@quran.test');
        $judge = $this->makeJudge($judgeUser);
        $app = $this->makeApplication();

        $evaluation = EvaluationModel::query()->create([
            'id' => (string) Uuid::v7(),
            'application_id' => $app['application_id'],
            'judge_id' => $judge->id,
            'total_score' => 0.0,
            'status' => 'draft',
        ]);

        $tajweed = $this->makeCriterion($app['season_id'], 'tajweed');
        $voice = $this->makeCriterion($app['season_id'], 'voice');

        $this->actingAs($judgeUser)->postJson("/api/v1/judge/evaluations/{$evaluation->id}/submit", [
            'criteria_scores' => [
                ['criterion_id' => $tajweed, 'score' => 38],
                ['criterion_id' => $voice, 'score' => 19],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('evaluation_scores', [
            'evaluation_id' => $evaluation->id,
            'criterion_id' => $tajweed,
            'score' => 38,
        ]);
        $this->assertDatabaseHas('evaluation_scores', [
            'evaluation_id' => $evaluation->id,
            'criterion_id' => $voice,
            'score' => 19,
        ]);
    }

    public function test_calculate_results_produces_real_category_averages_not_a_fake_split(): void
    {
        $admin = $this->makeUser('eval-admin@quran.test', 'admin');
        $judgeUser = $this->makeUser('judge-calc@quran.test');
        $judge = $this->makeJudge($judgeUser);
        $app = $this->makeApplication();

        $tajweed = $this->makeCriterion($app['season_id'], 'tajweed');
        $memorization = $this->makeCriterion($app['season_id'], 'memorization');
        $voice = $this->makeCriterion($app['season_id'], 'voice');

        $evaluation = EvaluationModel::query()->create([
            'id' => (string) Uuid::v7(),
            'application_id' => $app['application_id'],
            'judge_id' => $judge->id,
            'total_score' => 0.0,
            'status' => 'draft',
        ]);

        // Deliberately skewed scores — a fake 40/40/20 split of the total
        // would never reproduce this exact breakdown.
        $this->actingAs($judgeUser)->postJson("/api/v1/judge/evaluations/{$evaluation->id}/submit", [
            'criteria_scores' => [
                ['criterion_id' => $tajweed, 'score' => 10],
                ['criterion_id' => $memorization, 'score' => 90],
                ['criterion_id' => $voice, 'score' => 5],
            ],
        ])->assertStatus(200);

        $stageId = ApplicationModel::query()->findOrFail($app['application_id'])->stage_id;

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/stages/{$stageId}/calculate-results");

        $response->assertStatus(200);

        $this->assertDatabaseHas('results', [
            'application_id' => $app['application_id'],
            'tajweed_score' => 10,
            'memorization_score' => 90,
            'voice_score' => 5,
        ]);
    }

    public function test_recalculating_results_preserves_the_existing_row_id(): void
    {
        $admin = $this->makeUser('eval-admin-recalc@quran.test', 'admin');
        $judgeUser = $this->makeUser('judge-recalc@quran.test');
        $judge = $this->makeJudge($judgeUser);
        $app = $this->makeApplication();

        $tajweed = $this->makeCriterion($app['season_id'], 'tajweed');

        $evaluation = EvaluationModel::query()->create([
            'id' => (string) Uuid::v7(),
            'application_id' => $app['application_id'],
            'judge_id' => $judge->id,
            'total_score' => 0.0,
            'status' => 'draft',
        ]);

        $this->actingAs($judgeUser)->postJson("/api/v1/judge/evaluations/{$evaluation->id}/submit", [
            'criteria_scores' => [['criterion_id' => $tajweed, 'score' => 70]],
        ])->assertStatus(200);

        $stageId = ApplicationModel::query()->findOrFail($app['application_id'])->stage_id;

        $this->actingAs($admin)->postJson("/api/v1/admin/stages/{$stageId}/calculate-results")->assertStatus(200);
        $firstId = DB::table('results')->where('application_id', $app['application_id'])->value('id');

        $this->actingAs($admin)->postJson("/api/v1/admin/stages/{$stageId}/calculate-results")->assertStatus(200);
        $secondId = DB::table('results')->where('application_id', $app['application_id'])->value('id');

        expect($secondId)->toBe($firstId);
    }

    public function test_appeal_for_a_nonexistent_application_returns_422(): void
    {
        $data = $this->makeApplication();
        $contestantUser = UserModel::query()->find(
            ContestantModel::query()->findOrFail($data['contestant_id'])->user_id
        );

        $this->actingAs($contestantUser)->postJson('/api/v1/contestant/appeals', [
            'application_id' => (string) Uuid::v7(),
            'reason' => 'This application does not exist and this should fail validation.',
        ])->assertStatus(422);
    }

    public function test_contestant_cannot_appeal_another_contestants_application(): void
    {
        $app = $this->makeApplication();
        $otherUser = $this->makeUser('other-contestant@quran.test');

        $countryRepo = app(CountryRepositoryContract::class);
        (new CountriesSeeder)->run($countryRepo);
        $country = $countryRepo->findByIso2(new CountryIso2('JO'));

        $contestantRepo = app(ContestantRepositoryContract::class);
        $otherContestant = Contestant::create(
            id: ContestantId::generate(),
            userId: $otherUser->id,
            countryId: $country->id->value,
            fullName: 'Other Contestant',
            dateOfBirth: new BirthDate('1998-06-15'),
            gender: new Gender('male'),
            phoneNumber: '+962790000021'
        );
        $contestantRepo->save($otherContestant);

        $this->actingAs($otherUser)->postJson('/api/v1/contestant/appeals', [
            'application_id' => $app['application_id'],
            'reason' => 'Trying to appeal an application that belongs to someone else entirely.',
        ])->assertStatus(403);
    }
}
