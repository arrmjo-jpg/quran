# Videos Module — Quran Competition Platform

## Overview
The **Videos Module** manages recitation video transcoding pipelines, FFmpeg HLS adaptive bitrate variant generation (1080p, 720p, 480p, 360p), audio waveform generation, and video status state transitions per ADR-006.

## Responsibilities
- Govern recitation video transcoding state machine (`uploaded` -> `processing` -> `ready` / `failed`).
- Maintain HLS master playlist URLs and variant manifests per ADR-006.
- Issue `video_transcoding_completed` domain events for Application module state progression.

## Aggregates
1. `Video`: Aggregate root governing raw video asset references, duration, HLS master playlist path, and variants.

## Published Domain Events
- `video_transcoding_started`
- `video_transcoding_completed`
- `video_transcoding_failed`

## Consumed Domain Events
- `application_submitted`

## Dependencies
- Core (Outbox Event Bus)
- Media (Raw MediaAsset Storage Contract)

## ADR References
- [ADR-002: Module Boundaries](../../../docs/adr/ADR-002-modular-monolith-module-boundaries.md)
- [ADR-006: Video Transcoding Architecture](../../../docs/adr/ADR-006-video-transcoding-architecture.md)
- [ADR-008: Eventing Governance](../../../docs/adr/ADR-008-eventing-domain-events.md)
