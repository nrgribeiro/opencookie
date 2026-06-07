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

---

## US-SCAN-3 — Override classification

**As an** account owner
**I want** to manually re-categorize a cookie
**So that** I can correct or classify unknowns.

### Acceptance Criteria
- **Given** a cookie (classified or unclassified), **when** I change its category and save, **then** the override persists across future scans.
- **Given** I set purpose/provider text or GDPR metadata (retention, data controller, GDPR portal URL), **then** it appears in the cookie declaration and the consent banner's cookie-details view.
- **Given** an overridden cookie reappears in a later scan, **then** my override is retained, not reset by auto-classification.

---

## US-SCAN-4 — Detect changes between scans

**As an** account owner
**I want** to know when new cookies appear
**So that** I stay compliant as my site changes.

### Acceptance Criteria
- **Given** a prior scan exists, **when** a new scan runs, **then** newly seen cookies are flagged as "new" and missing ones marked "not seen".
- **Given** new unclassified cookies are found, **then** an alert (per settings) notifies the owner.

---

## US-SCAN-5 — Schedule recurring scans

**As an** account owner
**I want** scans to run automatically
**So that** disclosures stay current without manual runs.

### Acceptance Criteria
- **Given** a verified domain, **when** I enable scheduled scans, **then** a scan runs on the configured cadence (e.g. monthly).
- **Given** a scheduled scan completes, **then** results update the cookie list and declaration, and change-detection rules apply.
