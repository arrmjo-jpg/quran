# Media Module — Quran Competition Platform

## Overview
The **Media Module** is the central file storage and media asset management engine. It handles private/public file uploads, presigned Cloudflare R2 URL generation, and image conversions.

## Responsibilities
- Manage stored file records (`MediaAsset` aggregate) across `r2_private` and `r2_public` S3-compatible object storage disks per ADR-005/ADR-006.
- Maintain SHA-256 integrity hashes for file deduplication and security.
- Provide secure presigned URL tokens for private recitation video streaming.

## Aggregates
1. `MediaAsset`: Root aggregate governing media file metadata, storage disks, and custom properties.

## Published Domain Events
- `media_uploaded`
- `media_deleted`

## Consumed Events
- None.

## Dependencies
- Core (User Contract)

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-005: Database Architecture & Media Storage](../../../docs/adr/ADR-005-database-architecture.md)
- [ADR-006: Video Transcoding Architecture](../../../docs/adr/ADR-006-video-transcoding-architecture.md)
