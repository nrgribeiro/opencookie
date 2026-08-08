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

**Status:** ✅ Implemented — `Ingest\ConsentController` records consent_id, timestamp, categories, banner/policy version, an authoritative server-computed `consent_text_hash`, method, salted `ip_hash` (no raw IP), and truncated user agent. `ConsentRecord` sets `UPDATED_AT = null` and no code path updates a record after creation; the `consent_records` migration is append-only/partitioned with no update grants used in app code.

---

## US-LOG-2 — Store logs in EU region

**As a** GDPR-bound owner
**I want** consent proof hosted in the EU
**So that** data residency expectations are met.

### Acceptance Criteria
- **Given** any consent record, **then** it is persisted in EU-region infrastructure.
- **Given** the managed model, **then** no self-host/export-only path is required at launch.

**Status:** ❌ Not implemented — nothing in the codebase pins storage to an EU region; `config/database.php`, `config/queue.php`, `config/services.php`, and `.env.example` all default `AWS_DEFAULT_REGION` to `us-east-1` with no EU override or region assertion. This is purely a deployment/infra decision to be made outside the app, not something the code currently enforces.

---

## US-LOG-3 — Export consent logs

**As an** account owner
**I want** to export my consent records
**So that** I can respond to audits or requests.

### Acceptance Criteria
- **Given** stored records for my domain, **when** I export, **then** I receive CSV and/or JSON containing all fields needed as proof.
- **Given** a date range filter, **when** I export, **then** only records in range are included.
- **Given** I lack rights to a domain, **then** I cannot export its logs.

**Status:** ⚠️ Partially implemented — `ConsentLogController::export` streams a CSV with all proof fields and honors `from`/`to` date-range filters, and `$this->authorize('view', $domain)` blocks owners without rights (super admins bypass via `Gate::before`, as designed). Missing: no JSON export option (CSV only — acceptance criteria allows "CSV and/or JSON" so this alone is acceptable, but no alternative format exists), and the dashboard preview (`index`) only ever shows the latest 50 records with no pagination/search for older or specific records.

---

## US-LOG-4 — Enforce retention

**As a** privacy-conscious platform
**I want** records purged after 24 months
**So that** retention is not excessive.

### Acceptance Criteria
- **Given** a record older than 24 months, **when** the retention job runs, **then** it is permanently purged.
- **Given** a domain is deleted, **then** its consent logs are NOT deleted early — they persist until the 24-month retention elapses.
- **Given** purge runs, **then** the action is itself auditable (counts, timestamps), without retaining the purged content.

**Status:** ✅ Implemented — `PurgeExpiredConsentJob` computes a 24-month cutoff, drops fully-expired monthly partitions on Postgres (or deletes rows older than cutoff elsewhere), and logs `consent.retention.purge` with driver, cutoff, and counts/dropped-partition names — no purged content is retained. `consent_records.domain_id` has no FK (migration comment confirms this deliberately lets logs outlive domain/user deletion). Scheduled via `Schedule::job(new PurgeExpiredConsentJob)->dailyAt('03:15')` in `routes/console.php`.
