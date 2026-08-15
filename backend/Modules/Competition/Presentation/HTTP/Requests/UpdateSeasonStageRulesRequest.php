<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The payload is the season's complete stage rule set. That it covers
 * every stage of *this* season exactly once is checked by
 * UpdateSeasonStageRulesUseCase — that needs the season's current stages
 * to decide, which is a domain read rather than a validation rule.
 *
 * judge_score_system_id is required per rule because
 * season_stage_rules.judge_score_system_id is NOT NULL and each stage may
 * legitimately be judged on a different scale.
 * qualification_percentage is nullable: a final stage may rank without
 * eliminating anyone.
 */
final class UpdateSeasonStageRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.stage_id' => ['required', 'uuid', 'exists:stages,id'],
            'rules.*.judge_score_system_id' => ['required', 'uuid', 'exists:judge_score_systems,id'],
            'rules.*.qualification_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
