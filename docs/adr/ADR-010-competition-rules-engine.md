# ADR-010: Competition Rules Engine Architecture

| Field        | Value                                                                                                                           |
|--------------|---------------------------------------------------------------------------------------------------------------------------------|
| **ID**       | ADR-010                                                                                                                         |
| **Date**     | 2026-07-31                                                                                                                      |
| **Authors**  | Platform Architecture Team                                                                                                      |
| **Status**   | Accepted                                                                                                                        |
| **Deciders** | Jordan Radio and Television Corporation — Engineering Leadership                                                                |
| **Related**  | ADR-001 · ADR-002 · ADR-003 · ADR-004 · ADR-005 · ADR-006 · ADR-008 · ADR-009 · ERD · MIGRATION-SPECIFICATION                   |

---

## Status

**Accepted** — This document is the constitutional reference governing all competition rules, evaluation rubrics, tie-breaking algorithms, judge panel mechanics, score calculation formulas, qualification logic, results approval pipelines, appeals workflows, and configurable season parameters for the platform.

---

## Context

### Why This ADR Exists

ADR-001 through ADR-009 established the technical infrastructure, database architecture, API contracts, eventing outbox, and development standards for the platform.

However, the **business logic of the Quran Competition itself** — how recitation criteria are weighted, how scores are calculated, how ties are broken, how judge absences are handled, how contestants advance between stages, how results are approved, and how appeals are processed — was previously distributed across inventory notes or left implicit.

Without a single, governing Rules Engine ADR, the following business risks occur:

- **Hardcoded Competition Rules**: Weighting percentages (e.g. Tajweed 30%, Memorization 30%) hardcoded in PHP code prevent changing criteria from season to season without code deployments.
- **Arbitrary Tie-Breaking**: Lack of a deterministic tie-breaking sequence leads to manual disputes or unfair disqualification of tied contestants.
- **Unfair Score Manipulation**: Using "Trimmed Mean" (dropping high/low scores) without authorization alters official panel scoring.
- **Uncontrolled Waitlist Promotion**: Automatic candidate promotion upon contestant withdrawal causes unintended bracket shifts without admin oversight.
- **Premature Result Publication**: Contestant rankings becoming public before official Manager review and approval.
- **Missing Appeals Process**: Contestants having no formal mechanism to appeal scoring or administrative decisions post-publication.

This ADR eliminates these business risks by establishing a dynamic, database-configurable **Competition Rules Engine** before any module implementation begins.

---

## Architectural Principles

### Principle 1: Zero Hardcoded Competition Rules
No scoring weight, pass mark, tie-break sequence, stage quota, or appeal duration may be hardcoded in PHP application code. All competition parameters are governed by the Competition Rules Engine and stored in database configuration tables per season.

### Principle 2: Dynamic Season-Specific Rubrics
Each season defines its own evaluation rubric (`EvaluationTemplate`). Criteria codes, names, maximum points, and percentage weights are dynamically loaded per season and stage.

### Principle 3: Complete Panel Scoring (No Score Dropping)
Every assigned judge's score is mandatory. "Trimmed Mean" (dropping highest and lowest scores) is strictly prohibited. Final contestant scores are computed from all 5 panel judges. Absent judges must be replaced by an Admin via formal `JudgeReplacement`.

### Principle 4: Human-In-The-Loop Governance
Crucial competition transitions — publishing results, promoting waitlist candidates, overriding scores, and resolving appeals — require explicit human approval via the Admin Dashboard. They are never 100% automated.

### Principle 5: Deterministic & Auditable Rule Execution
Every rule execution (score aggregation, tie-break evaluation, qualification calculation) produces an immutable execution trace stored in `stage_results` and logged to `audit_logs`.

---

## Formal Decisions

---

### PART I — DYNAMIC EVALUATION RUBRIC & SCORING ENGINE

#### Decision 1: Season-Specific Rubric & Criteria Architecture

##### 1.1 Rubric Structure
The evaluation rubric is decoupled from application code using a three-level hierarchy:

```
Season (e.g. "2026 Season")
  └── EvaluationTemplate (e.g. "Standard Quran Recitation Rubric v1")
        ├── EvaluationCriteria: Tajweed       (Max: 100, Weight: 30%, Code: 'tajweed')
        ├── EvaluationCriteria: Memorization  (Max: 100, Weight: 30%, Code: 'memorization')
        ├── EvaluationCriteria: Voice/Melody  (Max: 100, Weight: 20%, Code: 'voice')
        └── EvaluationCriteria: Performance   (Max: 100, Weight: 20%, Code: 'performance')
```

##### 1.2 Database Configuration Schema
Evaluation criteria weights and maximum scores are stored per template in `evaluation_criteria` and `evaluation_criterion_translations` (ADR-005 Decision 12).

##### 1.3 Score Calculation Formula
For a given contestant evaluation across $N$ assigned panel judges ($N = 5$), the final stage score $S_{\text{final}}$ is calculated as:

$$S_{\text{judge}} = \sum_{c \in \text{Criteria}} \left( \text{Score}_{c} \times \frac{\text{Weight}_{c}}{100} \right)$$

$$S_{\text{final}} = \frac{1}{N} \sum_{j=1}^{N} S_{\text{judge}, j}$$

- **No Trimmed Mean**: Every assigned judge's score is included in $S_{\text{final}}$. Dropping highest/lowest scores is prohibited.
- Precision: All score calculations maintain **2 decimal places** (`DECIMAL(5,2)`).

---

### PART II — TIE-BREAKING & QUALIFICATION ENGINE

#### Decision 2: Deterministic Tie-Breaking Engine

##### 2.1 Tie-Breaking Priority Sequence
When two or more contestants achieve identical final stage scores ($S_{\text{final}}$), the Competition Rules Engine resolves the tie using the following strict priority sequence:

```
1. Final Aggregate Score (S_final)
        │ (if tied)
        ▼
2. Highest Score in 'Tajweed' Criterion
        │ (if tied)
        ▼
3. Highest Score in 'Memorization' (Hifz) Criterion
        │ (if tied)
        ▼
4. Highest Score in 'Voice/Melody' (Sawt) Criterion
        │ (if tied)
        ▼
5. Committee Manual Decision (Admin set manual_tie_break_flag)
```

##### 2.2 Date of Birth / Age Rule
Contestant age or date of birth (DOB) is **excluded** from tie-breaking unless explicitly mandated by official competition bylaws for a specific season.

##### 2.3 Tie-Break Audit Logging
Whenever a tie is broken, the Rules Engine writes the exact tie-breaking criteria used into `stage_results.results_data` JSON payload and dispatches a `TieBreakResolved` event.

#### Decision 3: Qualification & Advancement Logic

##### 3.1 Advancement Modes
Each stage defines its qualification rule via `stages.qualification_mode`:

| Mode Code | Advancement Logic |
|---|---|
| `top_n_overall` | Top $N$ contestants with highest $S_{\text{final}}$ advance to next stage. |
| `min_score_threshold` | All contestants with $S_{\text{final}} \ge \text{Threshold}$ (e.g. 85.00) advance. |
| `country_quota` | Top $N$ contestants per country advance (ensuring global representation). |
| `hybrid` | Top $N$ overall + Top 1 per unrepresented country. |

##### 3.2 Automated Draft Generation vs. Manual Publication
The Rules Engine generates a **Draft Qualification List** (`stage_results.status = 'draft'`). The list is **not active** until the Competition Manager reviews, signs off, and clicks `Approve & Publish`.

---

### PART III — JUDGE PANEL & SCORE LOCKING MECHANICS

#### Decision 4: Judge Panel Requirements & Absence Protocol

##### 4.1 Fixed Panel Requirement
Every stage evaluation requires exactly **5 assigned judges** (`stage_judge_assignments`).

##### 4.2 Absent Judge Protocol
An evaluation session cannot be finalized (`evaluations.status = 'approved'`) if any assigned judge has not submitted their score.

If a judge is absent, unresponsive, or recused:
1. System alerts Competition Manager.
2. Manager invokes `JudgeReplacementUseCase`.
3. The absent judge's assignment is removed, and a backup judge is assigned.
4. Existing scores submitted by other judges remain intact.

#### Decision 5: Score Edit Window & Locking Protocol

##### 5.1 Edit Window
A judge may edit and re-save draft scores (`evaluations.status = 'in_progress'`) indefinitely until they explicitly click **"Submit Evaluation"**.

##### 5.2 Hard Lock on Submission
Clicking "Submit Evaluation" transitions status to `completed` and **locks** the score record. Further judge edits via HTTP API return HTTP 423 Locked.

##### 5.3 Manager Revision Return Protocol
If a Competition Manager detects a scoring anomaly or error during audit:
1. Manager invokes `ReturnEvaluationForRevision` Use Case with mandatory explanatory notes.
2. Status transitions to `returned_for_revision`.
3. The score record is unlocked for that specific judge only.
4. Judge edits, re-saves, and re-submits.
5. All return actions are logged to `audit_logs`.

---

### PART IV — RESULTS PUBLICATION & APPEALS WORKFLOW

#### Decision 6: Results Approval & Publication Pipeline

Results move through a 4-step governed pipeline:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ STEP 1: CALCULATE DRAFT RESULTS                                            │
│ Competition Rules Engine computes final scores, applies tie-breakers,      │
│ and generates stage_results (status: draft).                               │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STEP 2: MANAGER AUDIT & SIGN-OFF                                            │
│ Competition Manager inspects draft rankings, score distribution, and        │
│ tie-break decisions in Admin Dashboard.                                     │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STEP 3: OFFICIAL APPROVAL & PUBLICATION                                     │
│ Manager clicks "Approve & Publish Results".                                 │
│ stage_results.status → published. StageResultsPublished event dispatched.   │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STEP 4: PUBLIC PORTAL & NOTIFICATION DISPATCH                              │
│ Cloudflare CDN edge cache purged. Contestant notification emails & in-app   │
│ dispatches queued via Horizon.                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Decision 7: Appeals & Disqualification Workflow

##### 7.1 Appeal Submission Window
Contestants have a **72-hour window** following `StageResultsPublished` to submit a formal appeal via the Contestant Portal.

##### 7.2 Appeal Handling Process
1. Submission creates an `ApplicationAppeal` record.
2. Appeals Committee reviews claim.
3. Committee outcome:
   - **Upheld**: Manager triggers `OverrideEvaluationScore` or `ReopenEvaluation`. Score updated, rankings recalculated.
   - **Rejected**: Appeal status updated to `rejected` with official response text.

##### 7.3 Contestant Withdrawal & Disqualification
- If an accepted contestant transitions to `withdrawn` or `disqualified`:
- **No Automatic Waitlist Promotion**: The system does **not** automatically promote the next candidate.
- **Manual Waitlist Promotion**: The Competition Manager views the waitlist in the Admin Dashboard and manually selects and clicks `Promote Candidate`.

---

### PART V — CONFIGURABLE SEASON PARAMETERS REGISTRY

#### Decision 8: Rules Engine Configuration Schema

The following table documents all configurable parameters managed by the Rules Engine per season:

| Parameter Key | Data Type | Default | Description |
|---|---|---|---|
| `registration_grace_period_minutes` | `integer` | `720` (12h) | Upload completion grace period after cutoff |
| `appeal_window_hours` | `integer` | `72` | Post-publication appeal window duration |
| `max_applications_per_contestant` | `integer` | `1` | Max application submissions per contestant per season |
| `min_contestant_age` | `integer` | `6` | Minimum eligible age as of season start date |
| `max_contestant_age` | `integer` | `40` | Maximum eligible age as of season start date |
| `required_panel_judges_count` | `integer` | `5` | Mandatory judge count per evaluation |
| `allow_contestant_withdrawals` | `boolean` | `true` | Permits contestants to self-withdraw post-acceptance |
| `allow_appeals` | `boolean` | `true` | Enables appeal submission button post-publication |
| `tie_breaker_sequence` | `json` | `["tajweed","memorization","voice","manual"]` | Configurable tie-break criterion order |

---

## Competition Rules Anti-Patterns (Prohibited Practices)

1. ❌ **No Hardcoded Criteria Percentages**: Writing `float $score = $tajweed * 0.30;` in PHP code. Always load weight from `evaluation_criteria`.
2. ❌ **No Trimmed Mean / Dropping Scores**: Dropping high or low judge scores. All 5 assigned judges' scores must be averaged.
3. ❌ **No Automatic Waitlist Promotion**: Automatically promoting waitlist candidates when someone withdraws. Always require Manager sign-off.
4. ❌ **No Auto-Publishing Results**: Publishing stage results immediately after the last judge submits without Manager approval.
5. ❌ **No Age-Based Tie Breaking Without Bylaw Entry**: Using DOB to break ties unless configured in the season's official bylaws.
6. ❌ **No Score Edits Post-Submission**: Allowing a judge to modify scores after clicking "Submit Evaluation" without a formal Manager revision return.

---

## Consequences

### Positive Consequences
- **Total Season Flexibility**: Competition managers can adjust criteria weights, pass thresholds, and appeal windows per season via the Admin Dashboard without developer code changes.
- **Uncompromised Fairness**: Deterministic tie-breaking rules and complete 5-judge panel averages eliminate scoring bias and subjective disputes.
- **Controlled Executive Oversight**: Mandatory Manager sign-offs for result publication and waitlist promotion prevent accidental state exposure.

### Negative Consequences / Trade-offs
- **Additional Database Queries**: Loading criteria weights and season rules dynamically requires Redis caching to avoid database overhead. (Mitigated by ADR-006 Cache Tags).

---

## References

- [ADR-001: System Architecture](./ADR-001-system-architecture.md)
- [ADR-002: Modular Monolith & Module Boundaries](./ADR-002-modular-monolith-module-boundaries.md)
- [ADR-004: API Standards & Conventions](./ADR-004-api-standards-conventions.md)
- [ADR-005: Database Architecture](./ADR-005-database-architecture.md)
- [ADR-006: Infrastructure Architecture](./ADR-006-infrastructure-architecture.md)
- [ADR-008: Eventing & Domain Events Governance](./ADR-008-eventing-domain-events.md)
- [ADR-009: Development Standards & Code Quality](./ADR-009-development-standards-code-quality.md)
- [ADR-ROADMAP.md](../ADR-ROADMAP.md)
