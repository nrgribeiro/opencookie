# User Stories — Cookie Scanner

Module ref: spec §4.3

---

## US-SCAN-1 — Run an on-demand scan

**As an** account owner
**I want** to scan my domain for cookies and trackers
**So that** I know what needs disclosure and consent.

### Acceptance Criteria
- **Given** a verified domain, **when** I trigger a scan, **then** a scan job starts and status shows "scanning".
- **Given** a scan runs, **then** it crawls up to 100 pages (free-tier cap) and detects cookies, localStorage, sessionStorage, and third-party requests.
- **Given** the domain is unverified, **when** I try to scan, **then** the action is blocked with a verify-first prompt.
- **Given** a scan finishes, **then** status shows "complete" with timestamp and pages-crawled count.
- **Given** a scan fails (unreachable site, timeout), **then** status shows "failed" with a reason and a retry option.

**Status:** ✅ Implemented — `ScanController::store` blocks unverified domains, `RunScanJob` runs queued/running/complete/failed states with timestamps, pages-crawled count, and error message; `domains/show.tsx` surfaces status, pages, timestamp, and error, and reuses the "Run scan" action as the retry path. Note: the page does not poll for live status updates after dispatch — the owner must refresh to see a scan move from queued/running to complete/failed.

---

## US-SCAN-2 — Auto-classify detected cookies

**As an** account owner
**I want** detected cookies sorted into categories
**So that** consent gating is correct without manual effort.

### Acceptance Criteria
- **Given** a completed scan, **then** each cookie is assigned a category (Necessary / Preferences / Statistics / Marketing) using the in-house cookie DB.
- **Given** a cookie is not in the DB, **then** it is marked "Unclassified" and flagged for review.
- **Given** each cookie, **then** name, provider, purpose, expiry, type (HTTP/script), and source domain are recorded where determinable.
- **Given** the cookie matches the Open Cookie Database, **then** GDPR metadata — retention, data controller, and GDPR rights portal URL — is recorded alongside it.

**Status:** ✅ Implemented — `CookieClassifier` resolves category/provider/purpose/retention/data controller/GDPR portal URL from `cookie_classifications` (seeded by `cookies:import-ocd`) with a static `NAME_MAP`/`HOST_MAP` fallback; unmatched cookies default to `CookieCategory::Unclassified`. `RunScanJob` records name, provider, category, purpose, expiry, type, source domain, and first-party flag on every detected cookie.

---

## US-SCAN-3 — Override classification

**As an** account owner
**I want** to manually re-categorize a cookie
**So that** I can correct or classify unknowns.

### Acceptance Criteria
- **Given** a cookie (classified or unclassified), **when** I change its category and save, **then** the override persists across future scans.
- **Given** I set purpose/provider text or GDPR metadata (retention, data controller, GDPR portal URL), **then** it appears in the cookie declaration and the consent banner's cookie-details view.
- **Given** an overridden cookie reappears in a later scan, **then** my override is retained, not reset by auto-classification.

**Status:** ✅ Implemented — `CookieOverride` (keyed by `cookie_name` + `source_domain`) stores category, provider, purpose, retention, data controller, and GDPR portal URL; `RunScanJob` looks up overrides before falling back to `CookieClassifier` on every rescan, so overrides survive re-classification. `CookieController::update` lets owners edit classified or unclassified cookies.

---

## US-SCAN-4 — Detect changes between scans

**As an** account owner
**I want** to know when new cookies appear
**So that** I stay compliant as my site changes.

### Acceptance Criteria
- **Given** a prior scan exists, **when** a new scan runs, **then** newly seen cookies are flagged as "new" and missing ones marked "not seen".
- **Given** new unclassified cookies are found, **then** an alert (per settings) notifies the owner.

**Status:** ✅ Implemented — `RunScanJob` correctly sets `CookieStatus::New`/`NotSeen`/`Active` per rescan, and `SendCookieAlertJob` emails the owner (respecting the `new_cookie_alerts` setting) when new or unclassified cookies appear. `domains/show.tsx` surfaces per-cookie status with "New"/"Not seen" badges and sorts changed cookies to the top of the list.

---

## US-SCAN-5 — Schedule recurring scans

**As an** account owner
**I want** scans to run automatically
**So that** disclosures stay current without manual runs.

### Acceptance Criteria
- **Given** a verified domain, **when** I enable scheduled scans, **then** a scan runs on the configured cadence (e.g. monthly).
- **Given** a scheduled scan completes, **then** results update the cookie list and declaration, and change-detection rules apply.

**Status:** ✅ Implemented — `DomainSettingsController` lets owners toggle `scheduled_scan_enabled` and pick `scan_frequency` (weekly/monthly); `routes/console.php` schedules `scans:dispatch-scheduled` for both cadences, and `DispatchScheduledScans` queues `RunScanJob` for matching verified domains, which busts the declaration cache and runs the same change-detection path as US-SCAN-4. `UpdateDomainSettingsRequest` rejects enabling scheduled scans when the owner's tier has `scheduled_scans_allowed = false`, and `DispatchScheduledScans` skips any domain whose tier no longer allows it (e.g. after a downgrade), so the entitlement is enforced at both the request and dispatch layers.
