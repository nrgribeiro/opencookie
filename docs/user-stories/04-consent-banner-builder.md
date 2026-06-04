# User Stories — Consent Banner Builder

Module ref: spec §4.4

---

## US-BAN-1 — Configure banner appearance

**As an** account owner
**I want** to customize the banner look
**So that** it matches my brand.

### Acceptance Criteria
- **Given** the builder, **when** I set layout (box/bar/popup), position, theme (light/dark), colors, and logo, **then** changes reflect in a live preview.
- **Given** I save, **then** a new draft banner config version is stored without affecting the live banner until published.

---

## US-BAN-2 — Enforce equal-prominence consent buttons

**As a** compliance-conscious owner
**I want** Reject as easy as Accept
**So that** consent is freely given per GDPR.

### Acceptance Criteria
- **Given** the first banner layer, **then** Accept All and Reject All are both present on that layer with equal visual prominence.
- **Given** I style Reject smaller/hidden or move it behind extra clicks, **when** I save/publish, **then** validation warns and blocks publish until corrected.
- **Given** a Customize/Settings action, **then** it is offered alongside Accept/Reject, not as the only alternative to Accept.

---

## US-BAN-3 — Configure consent categories

**As an** account owner
**I want** to present granular category choices
**So that** visitors consent per purpose.

### Acceptance Criteria
- **Given** the preferences panel, **then** categories Necessary, Preferences, Statistics, Marketing are shown, each with description and its cookie list.
- **Given** the Necessary category, **then** it is locked ON and cannot be toggled, but is described.
- **Given** non-necessary categories, **then** they default OFF (no pre-ticked boxes).

---

## US-BAN-4 — Manage languages

**As an** account owner with a multi-lingual site
**I want** the banner in multiple languages
**So that** visitors understand the consent request.

### Acceptance Criteria
- **Given** the builder, **when** I add languages and provide text per language, **then** the banner auto-detects visitor locale and shows the matching language.
- **Given** no matching locale, **then** the configured fallback language is shown.
- **Given** a language is missing required text, **when** I publish, **then** validation flags the gap.

---

## US-BAN-5 — Link privacy/cookie policy

**As an** account owner
**I want** the banner to link to my policy
**So that** the consent is informed.

### Acceptance Criteria
- **Given** the builder, **when** I set a policy URL, **then** the banner displays a link to it.
- **Given** no policy URL is set, **when** I publish, **then** validation warns that a policy link is required for "informed" consent.

---

## US-BAN-6 — Preview and publish

**As an** account owner
**I want** to preview before going live
**So that** I avoid publishing mistakes.

### Acceptance Criteria
- **Given** a draft config, **when** I open preview, **then** I see the banner rendered as visitors would, in each configured language.
- **Given** all validations pass, **when** I publish, **then** the config version becomes live and the live SDK serves it.
- **Given** a published version exists, **then** I can view version history and the active version is clearly marked.
