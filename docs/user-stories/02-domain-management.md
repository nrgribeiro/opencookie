# User Stories — Domain Management

Module ref: spec §4.2

---

## US-DOM-1 — Add a domain

**As an** account owner
**I want** to add my website domain
**So that** I can configure consent for it.

### Acceptance Criteria
- **Given** I have no domain yet (free tier = 1), **when** I submit a valid hostname, **then** the domain is created in unverified state.
- **Given** I already have 1 domain on free tier, **when** I try to add another, **then** I am blocked with an explanation of the free-tier limit.
- **Given** I submit an invalid or malformed hostname, **when** I submit, **then** the form rejects it with a clear reason.
- **Given** a domain is added, **then** a unique domain ID and embed snippet are generated.

**Status:** ✅ Implemented — `DomainController::store` creates the domain with a generated `domain_uid`; `StoreDomainRequest` validates hostname format/uniqueness and enforces the owner's `Tier::max_domains` cap (`withValidator`) with a tier-specific error message.

---

## US-DOM-2 — Verify domain ownership

**As an** account owner
**I want** to prove I control the domain
**So that** consent config and scanning are authorized.

### Acceptance Criteria
- **Given** an unverified domain, **when** I open verification, **then** I am offered DNS TXT, meta tag, and file-upload methods with instructions.
- **Given** I placed the DNS TXT / meta / file token, **when** I trigger verification, **then** the platform checks and marks the domain verified on success.
- **Given** the token is absent or wrong, **when** I verify, **then** verification fails with the detected vs. expected value shown.
- **Given** a domain is unverified, **then** scanning and banner-live status are disabled.

**Status:** ✅ Implemented — `DomainVerifier` supports all three methods (`checkDnsTxt`, `checkMetaTag`, `checkFile`) against a per-domain token; `DomainController::verify` records `last_error`/`last_checked_at`/`verified_at` and flips `verify_status`. `ScanController` blocks scanning unless `$domain->isVerified()`; `banner_live` only flips true when the ingest SDK actually pings (`ImpressionController`), never before verification.

---

## US-DOM-3 — Get embed snippet

**As an** account owner
**I want** a copy-paste script snippet
**So that** I can install the consent SDK on my site.

### Acceptance Criteria
- **Given** a domain exists, **when** I view its install page, **then** I see a `<script>` snippet containing the unique domain ID and a copy button.
- **Given** I copy the snippet, **then** it references the CDN-served SDK and requires no further config to render the banner.

**Status:** ✅ Implemented — `DomainController::embedSnippet` renders a `<script src=".../sdk/v1/cmp.js" data-domain="...">` tag from `config('services.cmp_cdn')`; `domains/show.tsx` displays it with a working `CopyButton`.

---

## US-DOM-4 — View domain status

**As an** account owner
**I want** to see my domain's state at a glance
**So that** I know what's pending.

### Acceptance Criteria
- **Given** a domain, **when** I view it, **then** I see status indicators: verified (yes/no), scanning state, banner live (yes/no), last scan date.
- **Given** the SDK has been detected loading on the live site, **then** banner-live shows true; otherwise false.

**Status:** ✅ Implemented — `DomainController::summary`/`show` expose verify status, latest scan (status, pages crawled, finished/error), `banner_live`, and `last_scanned_at`; `domains/index.tsx` and `domains/show.tsx` render these as badges. `banner_live` is flipped by `ImpressionController` on the first real ingest ping, not just on publish.

---

## US-DOM-5 — Delete a domain

**As an** account owner
**I want** to remove a domain
**So that** I can free my slot or stop using the platform.

### Acceptance Criteria
- **Given** a domain, **when** I delete it, **then** I am warned this removes banner config and scan data, and asked to confirm.
- **Given** I confirm deletion, **then** the domain, its config, scans, and cookie records are removed; consent logs follow the retention policy (not deleted early).
- **Given** deletion completes, **then** the embed snippet for that domain stops serving an active banner.

**Status:** ✅ Implemented — `domains/show.tsx` shows a confirmation dialog warning that banner config, scans, and cookie records are removed while consent logs are retained; `DomainController::destroy` calls `$domain->delete()`. Migrations cascade-delete `domain_verifications`, `scans`, `cookies`, `cookie_overrides`, `banner_configs`, `policy_versions`, `notification_settings`, and `banner_impressions` on `domain_id`; `consent_records.domain_id` has its cascading FK deliberately dropped (migration `2026_06_04_000001_drop_consent_records_domain_fk`) so logs survive per retention policy. No explicit test found asserting the ingest API stops serving a live banner after deletion, but `Domain::getRouteKeyName()` binding by `domain_uid` means a deleted domain 404s on any ingest lookup, which achieves the same effect.
