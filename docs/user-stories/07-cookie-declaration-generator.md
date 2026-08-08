# User Stories — Cookie Declaration / Policy Generator

Module ref: spec §4.7

---

## US-DECL-1 — Auto-generate cookie declaration

**As an** account owner
**I want** a declaration table built from scan results
**So that** my disclosures are accurate without manual effort.

### Acceptance Criteria
- **Given** a completed scan, **then** a declaration table is generated grouping cookies by category with name, provider, purpose, expiry, and GDPR metadata (retention, data controller, GDPR rights portal URL) where available.
- **Given** classification overrides exist, **then** the declaration reflects the overridden values.
- **Given** a new scan changes the cookie set, **then** the declaration updates to stay in sync.

**Status:** ⚠️ Partially implemented — missing: `DeclarationController::build()` (`app/Http/Controllers/Ingest/DeclarationController.php`) only emits name, provider, category, purpose, and expiry; it does not select or render the GDPR metadata columns (`retention`, `data_controller`, `gdpr_portal_url`) that already exist on `cookies`/`cookie_classifications` and are editable via `CookieController::update()`. Override reflection and rescan sync both work (`CookieController::update()` and `RunScanJob` both call `DeclarationController::bustCache()`, and the table is rebuilt live from the `cookies` table on each cache miss).

---

## US-DECL-2 — Embed declaration on policy page

**As an** account owner
**I want** an embed snippet for my cookie policy page
**So that** the disclosure stays current automatically.

### Acceptance Criteria
- **Given** a domain with a declaration, **when** I open the embed section, **then** I get a copy-paste snippet that renders the live declaration.
- **Given** the declaration updates after a scan, **then** the embedded view reflects changes without re-pasting the snippet.

**Status:** ✅ Implemented — `resources/js/pages/domains/show.tsx` renders a "Cookie declaration embed" card with a copy-paste `<script>` snippet (`declarationSnippet`) pointing at `GET /v1/c/{domainUid}/declaration.js` (`routes/ingest.php`); the endpoint is cached (10 min TTL) and busted on override edits and rescans, so the embedded view updates without re-pasting.

---

## US-DECL-3 — Multi-language declaration

**As an** account owner with a multi-lingual site
**I want** the declaration in multiple languages
**So that** disclosures match the site language.

### Acceptance Criteria
- **Given** configured languages, **then** category names and purpose text are shown in the matching language.
- **Given** no match for visitor locale, **then** the fallback language is shown.
- **Given** missing translations for a category, **then** the owner is flagged to complete them.

**Status:** ⚠️ Partially implemented — missing: only cookie *purpose* text is translated (via `cookie_overrides.purpose_translations`, resolved per-`lang` query param in `DeclarationController::build()` with fallback to the banner's `default_language`); category *names* are emitted as the raw `CookieCategory` enum value with no translation table, so they don't match the site language. The owner-facing flag for incomplete translations does exist (`DomainController::index()` computes `missingTranslations`, surfaced in `resources/js/pages/domains/show.tsx`).
