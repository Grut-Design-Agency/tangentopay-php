## [0.2.5] - 2026-05-21

### Changed
- `ApiKey` model now exposes `publicKey`, `secretKey`, `webhookSecret`, `keyType`, `keyName`, `isActive`, `lastUsedAt`, `expiresAt`. Old `key` and `type` fields removed.
- `ServicesResource::createApiKey()` now requires `$keyName` and `$keyType` parameters (no longer takes a single `$type` positional arg).
- `ServicesResource::listApiKeys()` never returns `secretKey` or `webhookSecret`.
- `ServicesResource::updateWebhook()` now takes optional `?array $events` parameter instead of no events.

### Added
- `ServicesResource::rotateApiKey(int $serviceId, int $keyId): ApiKey` — atomically regenerates all three credentials.
- Webhook secrets now use `whs_test_…` / `whs_live_…` prefix (previously `whsec_…`).
