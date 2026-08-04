<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Evaluations\Domain\Entities\Evaluation;

/**
 * EvaluationResource
 *
 * JUDGE BLINDNESS is enforced here:
 * - Only this judge's own data is exposed.
 * - No other judges' scores, averages, ranks, or qualification status.
 * - No contestant identity (name/photo/nationality).
 *
 * Supports both:
 *   - Evaluation (Domain Entity): id = EvaluationId VO, applicationId (camelCase public property)
 *   - EvaluationModel (Eloquent): id = string, application_id (snake_case attribute)
 */
final class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        // Resolve id: either a ValueObject with ->value, or a plain string
        if ($resource instanceof Evaluation) {
            $id = $resource->id->value;
            $applicationId = $resource->applicationId;
            $status = $resource->getStatus();
            $totalScore = $resource->getTotalScore();
            $submittedAt = null;
        } else {
            // Eloquent model
            $id = $resource->id;
            $applicationId = $resource->application_id;
            $status = $resource->status;
            $totalScore = (float) $resource->total_score;
            $submittedAt = $resource->submitted_at;
        }

        return [
            'id' => (string) $id,
            'application_id' => (string) $applicationId,
            'status' => (string) $status,
            'total_score' => (float) $totalScore,
            'submitted_at' => $submittedAt,
        ];
    }
}
