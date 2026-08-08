# User Stories — Dashboard & Analytics

Module ref: spec §4.8

---

## US-DASH-1 — View consent metrics

**As an** account owner
**I want** to see consent rates over time
**So that** I understand visitor behavior and banner performance.

### Acceptance Criteria
- **Given** a domain with logged events, **when** I open the dashboard, **then** I see accept / reject / partial rates and banner impressions.
- **Given** a selected time range, **when** I change it, **then** metrics and trend charts recompute for that range.
- **Given** opt-in by category, **then** I see per-category acceptance percentages.

**Status:** ⚠️ Partially implemented — missing: `DashboardController::consentMetrics()` computes accept/reject/custom rates, impressions, and per-category percentages for the selected range (`resources/js/pages/dashboard.tsx`), and metrics recompute on range change, but there is no trend chart over time — no charting library is used anywhere in the app, only point-in-time stat cards and per-category bars for the current range.

---

## US-DASH-2 — View scan summary

**As an** account owner
**I want** a snapshot of detected cookies
**So that** I know my disclosure surface.

### Acceptance Criteria
- **Given** a latest scan, **then** the dashboard shows total cookies by category and an unclassified count.
- **Given** unclassified cookies exist, **then** they are surfaced with a link to classify them.

**Status:** ✅ Implemented

---

## US-DASH-3 — Compliance health check

**As an** account owner
**I want** a compliance checklist
**So that** I can fix gaps before they're a liability.

### Acceptance Criteria
- **Given** a domain, **then** the dashboard shows a checklist: banner live?, Reject button present?, policy linked?, scan recent (within cadence)?, unclassified cookies = 0?
- **Given** any item fails, **then** it is marked clearly with a link to resolve it.
- **Given** all items pass, **then** the domain shows a healthy/compliant state.

**Status:** ⚠️ Partially implemented — missing: `App\Services\DomainCompliance::checklist()` provides all five checks (banner live, reject button, policy linked, scan recency, unclassified count = 0) and the owner dashboard renders them with check/cross icons, but `DashboardController::index()` only passes `checklist()`, not `evaluate()`'s `isCompliant` flag (which the admin dashboard does use), so there is no explicit overall "healthy/compliant" summary state shown to the owner — only the per-item list.

---

## US-DASH-4 — Preview and export recent logs

**As an** account owner
**I want** a quick view of recent consent records
**So that** I can spot-check without a full export.

### Acceptance Criteria
- **Given** recent consent events, **then** the dashboard shows a preview list (most recent N).
- **Given** the preview, **when** I click export, **then** I am taken to the full export flow (see US-LOG-3).

**Status:** ✅ Implemented
