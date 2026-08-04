# Core Module — Quran Competition Platform

## Overview
The **Core Module** is the foundation of the platform architecture. It manages system users, RBAC roles/permissions, system settings, locale languages, and outbox domain events.

## Domain Aggregates
- `User`: Identity aggregate root (email, name, surface discriminator, active state, preferred locale).
- `Role` & `Permission`: RBAC access control structures.
- `OutboxEvent`: Transactional Outbox event records for reliable async message publishing.

## Cross-Module Integration
Other modules MUST interact with Core strictly via:
- `Modules\Core\Contracts\CoreServiceContract`
- Published Outbox Domain Events (`user_registered`, `user_deactivated`, etc.)
