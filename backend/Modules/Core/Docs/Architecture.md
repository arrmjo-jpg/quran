# Core Module — Architecture Guide

## Clean Architecture Layers
```
Modules/Core/
├── Domain/                 ← Pure PHP entities, Value Objects, Repository Contracts (Zero framework code)
├── Application/            ← Task-based Use Cases, Commands DTOs, Outbox event dispatching
├── Infrastructure/         ← Eloquent Models, Repository Implementations, DB Migrations
└── Presentation/           ← Controllers, Form Requests, JsonResources, API Routes
```

## Immutable Value Objects
- `UserId`: UUID v7 identifier.
- `Email`: Validated RFC email string.
- `PasswordHash`: Bcrypt/argon2 hashed password string.
- `Locale`: BCP-47 locale ('ar', 'en').

## Transaction Rules (ADR-012)
All state mutations inside Use Cases run inside `DB::transaction()` blocks co-locating entity updates with outbox event persistence.
