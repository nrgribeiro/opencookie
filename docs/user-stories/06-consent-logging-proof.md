# User Stories — Consent Logging & Proof

Module ref: spec §4.6

---

## US-LOG-1 — Record each consent action

**As a** data controller (account owner)
**I want** every consent action logged
**So that** I can prove consent to a DPA.

### Acceptance Criteria
- **Given** a visitor makes or changes a choice, **when** the SDK posts the event, **then** a record is stored with: pseudonymous consent ID, timestamp, categories accepted/rejected, banner version, consent text shown, method (accept-all / reject-all / custom), hashed/truncated IP, user agent.
- **Given** PII minimization, **then** no raw IP or directly identifying data is stored where avoidable.
- **Given** records are written, **then** the store is append-only/immutable (no in-place edits).

---

## US-LOG-2 — Store logs in EU region

**As a** GDPR-bound owner
**I want** consent proof hosted in the EU
**So that** data residency expectations are met.

### Acceptance Criteria
- **Given** any consent record, **then** it is persisted in EU-region infrastructure.
- **Given** the managed model, **then** no self-host/export-only path is required at launch.

---

## US-LOG-3 — Export consent logs

**As an** account owner
**I want** to export my consent records
**So that** I can respond to audits or requests.

### Acceptance Criteria
- **Given** stored records for my domain, **when** I export, **then** I receive CSV and/or JSON containing all fields needed as proof.
- **Given** a date range filter, **when** I export, **then** only records in range are included.
- **Given** I lack rights to a domain, **then** I cannot export its logs.

---

## US-LOG-4 — Enforce retention

**As a** privacy-conscious platform
**I want** records purged after 24 months
**So that** retention is not excessive.

### Acceptance Criteria
- **Given** a record older than 24 months, **when** the retention job runs, **then** it is permanently purged.
- **Given** a domain is deleted, **then** its consent logs are NOT deleted early — they persist until the 24-month retention elapses.
- **Given** purge runs, **then** the action is itself auditable (counts, timestamps), without retaining the purged content.
