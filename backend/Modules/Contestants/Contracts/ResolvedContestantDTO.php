<?php

declare(strict_types=1);

namespace Modules\Contestants\Contracts;

/**
 * ResolvedContestantDTO
 *
 * Cross-module read shape for the contestant record behind an account.
 * Owned by Contestants — consumers read these three fields and cannot reach
 * anything else about the person.
 *
 * THE THREE FIELDS ARE ADR-016 D22, AND THE CLASS IS HOW D22 IS ENFORCED.
 * D22 is D18 held up to a mirror: the same rule — *shows what identifies,
 * explains, or links; does not show what describes* — applied to what a
 * contestant may contribute to an account screen rather than the reverse.
 *
 *   id           the navigation target for /contestants/{id}. Without it the
 *                relationship is displayed but cannot be followed.
 *   fullName     identifies the competitor. Its DIVERGENCE from users.name
 *                is itself information: they are separate columns and
 *                nothing keeps them in step.
 *   isDeleted    explains. An account that looks ordinary but whose
 *                contestant record was removed is not competing, and the
 *                account screen otherwise gives no hint why.
 *
 * THE ACCOUNT ID IS NOT A FIELD HERE. A batch consumer needs to know which
 * account each row belongs to, and the obvious place to put that is a fourth
 * property — which would quietly make this class four fields wide and blur
 * what D22 admitted. So the batch is returned keyed by account id instead:
 * the link lives in the shape of the result, and the DTO stays exactly the
 * three facts about the person.
 *
 * `national_id`, `date_of_birth`, `gender`, `phone_number`, `country_id` and
 * `profile_completeness` were each considered and each excluded — they
 * describe the person, and belong on the contestant's own screen behind that
 * screen's permission. They are absent here rather than merely undocumented,
 * so a screen that wants one cannot quietly add it: widening this class means
 * re-opening D22 in the ADR where the reasoning lives.
 */
final readonly class ResolvedContestantDTO
{
    public function __construct(
        public string $id,
        public string $fullName,
        public bool $isDeleted,
    ) {}
}
