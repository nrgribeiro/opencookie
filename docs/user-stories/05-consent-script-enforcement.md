# User Stories — Consent Script / Enforcement (Client SDK)

Module ref: spec §4.5

---

## US-SDK-1 — Block non-essential scripts before consent

**As a** site owner
**I want** non-necessary scripts held until consent
**So that** my site complies with prior-consent rules.

### Acceptance Criteria
- **Given** a page with the SDK, **when** it loads and no valid consent exists, **then** scripts tagged `type="text/plain" data-cmp-category="..."` for non-necessary categories do NOT execute.
- **Given** known third-party tags, **where** feasible, **then** the SDK auto-blocks them without manual tagging.
- **Given** Necessary-only scripts, **then** they run regardless of consent state.

---

## US-SDK-2 — Render banner when no valid consent

**As a** visitor
**I want** a clear consent prompt on first visit
**So that** I can make a choice.

### Acceptance Criteria
- **Given** no consent record, or an expired one, or a record from an older policy/banner version, **when** the page loads, **then** the banner renders.
- **Given** a valid current consent record, **when** the page loads, **then** no banner shows and stored prefs apply.
- **Given** the banner renders, **then** it minimizes layout shift and loads async.

---

## US-SDK-3 — Capture and apply consent choice

**As a** visitor
**I want** my choice honored immediately
**So that** only what I allowed runs.

### Acceptance Criteria
- **Given** the banner, **when** I choose Accept All / Reject All / custom categories, **then** the SDK writes a first-party consent record (categories + version + timestamp) and activates only permitted categories.
- **Given** I reject all non-necessary, **then** no non-necessary script or cookie is set.
- **Given** I make a choice, **then** the SDK POSTs a consent event to the log endpoint.

---

## US-SDK-4 — Emit Google Consent Mode v2 signals

**As a** site owner using Google tags
**I want** Consent Mode signals driven by the banner
**So that** Google tags respect consent.

### Acceptance Criteria
- **Given** the SDK loads, **before** any choice, **then** it pushes Consent Mode defaults of `denied` for `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`.
- **Given** a consent choice, **then** the SDK pushes a Consent Mode update mapping granted categories to the matching signals.
- **Given** a returning visitor with a stored choice, **then** signals are set from the stored record before tags fire.

---

## US-SDK-5 — Re-open consent settings / withdraw

**As a** visitor
**I want** to change or withdraw consent anytime
**So that** withdrawal is as easy as giving it.

### Acceptance Criteria
- **Given** a stored choice, **then** a persistent re-open widget/link is available on the page.
- **Given** I open settings via `showSettings()` or the widget, **when** I change toggles and save, **then** the record updates, categories/signals re-apply live, and a new log event is sent.
- **Given** I withdraw all non-necessary, **then** previously allowed scripts are prevented on subsequent loads.

---

## US-SDK-6 — Fail safe when backend is unreachable

**As a** site owner
**I want** safe behavior during outages
**So that** tracking never runs without consent.

### Acceptance Criteria
- **Given** the log endpoint or config backend is unreachable, **when** the page loads, **then** the banner still renders and non-necessary scripts stay blocked.
- **Given** the backend is down, **then** the SDK never defaults to allowing tracking.
- **Given** connectivity returns, **then** queued consent events are sent (best effort).

---

## US-SDK-7 — Expose JS API

**As a** developer integrating the SDK
**I want** a small API
**So that** I can read and react to consent.

### Acceptance Criteria
- **Given** the SDK is loaded, **then** `getConsent()` returns current categories/state.
- **Given** consent changes, **then** `onConsentChange(cb)` invokes the callback with the new state.
- **Given** I call `showSettings()`, **then** the preferences panel opens.
