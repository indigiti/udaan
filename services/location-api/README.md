# Location API

Standalone Go service for resolving GPS coordinates into official India Post DIGIPIN values and, when configured, local government postal/PIN metadata.

## Current status

- DIGIPIN encode/decode: implemented locally in Go.
- Official conformance vector: `13.11179621,80.20264269 -> 4T396F42L7`.
- Postal/PIN lookup: interface is ready; the government dataset adapter is intentionally not hard-coded until the exact dataset/schema is supplied.
- No external runtime dependency is required for DIGIPIN.

The DIGIPIN implementation is ported from India Post's public implementation at `INDIAPOST-gov/digipin`, source blob `02a89afa066a30f5cf2a55dbc2a64e7964efbf30` (published algorithm update dated 2026-05-04).

## Run

```bash
go run ./cmd/location-api
```

Default listen address: `:8080`.

Optional API key:

```bash
LOCATION_API_KEY='change-me' go run ./cmd/location-api
```

Health/version endpoints stay unauthenticated. API endpoints require `X-API-Key` only when `LOCATION_API_KEY` is set.

## Endpoints

### Encode DIGIPIN

```http
GET /v1/digipin/encode?latitude=13.11179621&longitude=80.20264269
```

### Decode DIGIPIN

```http
GET /v1/digipin/decode?digipin=4T396F42L7
```

### Resolve location

```http
POST /v1/location/resolve
Content-Type: application/json

{
  "latitude": 13.11179621,
  "longitude": 80.20264269,
  "accuracy_m": 5
}
```

Until a government postal dataset adapter is configured, the response still returns the DIGIPIN and includes:

```json
"postal": {
  "available": false,
  "reason": "POSTAL_DATA_NOT_CONFIGURED"
}
```

This separation is deliberate: DIGIPIN is deterministic and should never fail just because postal data is missing or stale.

## Government postal data integration

Implement `internal/postal.Resolver` for the supplied source. The public API contract does not need to change. Prefer a local PostGIS-backed resolver when polygon/service-area data is available. Do not infer PIN codes from DIGIPIN: they are separate addressing systems.

## Test

```bash
go test ./...
```
