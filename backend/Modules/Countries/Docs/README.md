# Countries Module — Quran Competition Platform

## Overview
The **Countries Module** is the canonical Reference Data & Localization Blueprint for the platform. It provides standardized ISO 3166-1 country codes, international dial codes, flag URLs, and multi-language translations (Option C strategy).

## Responsibilities
- Maintain authoritative ISO2, ISO3, and Phone code reference data.
- Manage localized country names across supported languages (`ar`, `en`).
- Provide active country eligibility specifications (`ActiveCountriesSpecification`).
- Prevent hard deletion of country records to guarantee historical integrity across Contestants, Judges, Applications, and Reports.

## Public Contracts
- `Modules\Countries\Contracts\CountriesServiceContract`
- `Modules\Countries\Domain\Repositories\CountryRepositoryContract`

## Published Domain Events
- `country_created`
- `country_updated`
- `country_activated`
- `country_deactivated`

## Consumed Events
- None (Reference module).

## Use Cases
- `CreateCountryUseCase`
- `UpdateCountryUseCase`
- `ActivateCountryUseCase`
- `DeactivateCountryUseCase`

## Permissions
- `countries.view`: View country list and details.
- `countries.manage`: Admin management (create, update, activate, deactivate).

## API Endpoints
- `GET /api/v1/countries` (Public / Contestant surface)
- `GET /api/v1/admin/countries` (Admin surface)
- `POST /api/v1/admin/countries` (Admin surface)
- `PUT /api/v1/admin/countries/{id}` (Admin surface)

## Dependencies
- Core (Value Objects, Base Provider)

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-005: Database Architecture (Option C Translations)](../../../docs/adr/ADR-005-database-architecture.md)
- [ADR-011: Scaffolding Architecture](../../../docs/adr/ADR-011-module-scaffolding-code-generation.md)
- [ADR-012: Application Layer Architecture](../../../docs/adr/ADR-012-application-layer-usecase-orchestration.md)
