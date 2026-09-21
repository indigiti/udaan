# Udaan Distributed Content Blueprint — v0.6.x

## Principle

> **Centralize trust, decentralize distribution.**

### Centralize

- source approval
- factual verification
- moderation
- content signing
- canonical manifest
- revocation / version authority
- future reviewer RBAC

### Decentralize

- encrypted local caching
- pack replication
- future WebRTC transfer
- future school LAN/edge distribution
- origin fallback

## v0.6.0 implementation

```text
Approved cards.json
      ↓
Pack builder
      ↓
SHA-256 + Ed25519
      ↓
Signed manifest
      ↓
HTTPS origin delivery
      ↓
Browser verifies
      ↓
Encrypted IndexedDB
```

No peer transport is enabled yet. v0.6.0 intentionally establishes the trust/integrity layer first.

## Why public-key signing

A future peer may possess and redistribute a pack, but must never receive the Udaan private signing key. Devices verify with the Ed25519 public key. Any modification to a signed pack breaks hash/signature validation.

## Protected key

`data/content-signing.key`

Treat as production-sensitive recovery material. Back it up with `app-secret.key` and `storage-encryption.key`.

## Device bank

Maximum v0.6.0 signed bank capacity: 1,008 verified cards/device.

The capacity is balanced by pillar when the content inventory permits it. It is not a requirement to download 1,008 if the live approved bank is smaller.

## Future v0.6.x transport

Planned, not yet enabled:

1. short-lived peer IDs;
2. signaling service;
3. WebRTC data channels;
4. 3–8 peer swarm;
5. manifest comparison;
6. missing-pack transfer;
7. signature verification before acceptance;
8. origin fallback;
9. school edge-node support.

Do not exchange WhatsApp number, learner hash, persistent device GUID, answers, progress, interests, commitments or leaderboard identity with peers.

## Database

No MariaDB is required for v0.6.x content distribution. MariaDB will later provide durable accounts, results, reviewer workflows, audit, moderation, analytics and multi-device history. Signed distributed content remains useful after the DB is introduced.
