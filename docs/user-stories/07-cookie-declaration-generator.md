# User Stories — Cookie Declaration / Policy Generator

Module ref: spec §4.7

---

## US-DECL-1 — Auto-generate cookie declaration

**As an** account owner
**I want** a declaration table built from scan results
**So that** my disclosures are accurate without manual effort.

### Acceptance Criteria
- **Given** a completed scan, **then** a declaration table is generated grouping cookies by category with name, provider, purpose, and expiry.
- **Given** classification overrides exist, **then** the declaration reflects the overridden values.
- **Given** a new scan changes the cookie set, **then** the declaration updates to stay in sync.

---

## US-DECL-2 — Embed declaration on policy page

**As an** account owner
**I want** an embed snippet for my cookie policy page
**So that** the disclosure stays current automatically.

### Acceptance Criteria
- **Given** a domain with a declaration, **when** I open the embed section, **then** I get a copy-paste snippet that renders the live declaration.
- **Given** the declaration updates after a scan, **then** the embedded view reflects changes without re-pasting the snippet.

---

## US-DECL-3 — Multi-language declaration

**As an** account owner with a multi-lingual site
**I want** the declaration in multiple languages
**So that** disclosures match the site language.

### Acceptance Criteria
- **Given** configured languages, **then** category names and purpose text are shown in the matching language.
- **Given** no match for visitor locale, **then** the fallback language is shown.
- **Given** missing translations for a category, **then** the owner is flagged to complete them.
