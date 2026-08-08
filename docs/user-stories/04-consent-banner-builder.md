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

**Status:** ⚠️ Partially implemented — missing: no logo upload/URL field in the builder UI (`resources/js/pages/domains/banner.tsx`) even though `BannerConfig.layout.logo` and validation (`layout.logo` in `UpdateBannerRequest`) already support it; the in-page "preview" is a hand-rolled mock (title/body/buttons only), not the actual SDK-rendered banner. Layout type/position/theme/accent color and draft/publish separation (`BannerService::draftFor`) are implemented.

---

## US-BAN-2 — Enforce equal-prominence consent buttons

**As a** compliance-conscious owner
**I want** Reject as easy as Accept
**So that** consent is freely given per GDPR.

### Acceptance Criteria
- **Given** the first banner layer, **then** Accept All and Reject All are both present on that layer with equal visual prominence.
- **Given** I style Reject smaller/hidden or move it behind extra clicks, **when** I save/publish, **then** validation warns and blocks publish until corrected.
- **Given** a Customize/Settings action, **then** it is offered alongside Accept/Reject, not as the only alternative to Accept.

**Status:** ✅ Implemented — `BannerService::assertPublishable` blocks publish when any language is missing `rejectAll`/`acceptAll`/`customize` text (`app/Services/Banner/BannerService.php`, covered by `tests/Feature/BannerTest.php`). The SDK (`resources/sdk/cmp.ts` `renderBanner`) always renders Accept, Reject, and Customize together with the same button styling — button prominence isn't independently configurable, so there's no styling path for an owner to shrink/hide Reject.

---

## US-BAN-3 — Configure consent categories

**As an** account owner
**I want** to present granular category choices
**So that** visitors consent per purpose.

### Acceptance Criteria
- **Given** the preferences panel, **then** categories Necessary, Preferences, Statistics, Marketing are shown, each with description and its cookie list.
- **Given** the Necessary category, **then** it is locked ON and cannot be toggled, but is described.
- **Given** non-necessary categories, **then** they default OFF (no pre-ticked boxes).

**Status:** ✅ Implemented — categories are fixed platform-wide (`STANDARD_CATEGORIES` in `app/Http/Controllers/Ingest/ConfigController.php`: necessary/preferences/statistics/marketing, each with name + description, translated), not owner-configurable, matching the story's intent. The SDK customize panel (`resources/sdk/cmp.ts::renderCustomize`) only renders checkboxes for `NON_NECESSARY` categories, all initialized `false`; Necessary is never a toggle (always locked on via `choose()`'s `{ necessary: true, ...categories }`). Per-category cookie lists render in the details modal (`renderDetailsModal`, `cookieDetails` keyed by category).

---

## US-BAN-4 — Manage languages

**As an** account owner with a multi-lingual site
**I want** the banner in multiple languages
**So that** visitors understand the consent request.

### Acceptance Criteria
- **Given** the builder, **when** I add languages and provide text per language, **then** the banner auto-detects visitor locale and shows the matching language.
- **Given** no matching locale, **then** the configured fallback language is shown.
- **Given** a language is missing required text, **when** I publish, **then** validation flags the gap.

**Status:** ✅ Implemented — the builder (`resources/js/pages/domains/banner.tsx`) lets owners add/remove languages and edit per-language content; `UpdateBannerRequest` requires a content entry per selected language and `BannerService::assertPublishable` blocks publish if any language is missing a required key. The SDK's `currentLang()` (`resources/sdk/cmp.ts`) matches `navigator.languages` against the configured set (exact, then base-language) and falls back to `defaultLanguage`.

---

## US-BAN-5 — Link privacy/cookie policy

**As an** account owner
**I want** the banner to link to my policy
**So that** the consent is informed.

### Acceptance Criteria
- **Given** the builder, **when** I set a policy URL, **then** the banner displays a link to it.
- **Given** no policy URL is set, **when** I publish, **then** validation warns that a policy link is required for "informed" consent.

**Status:** ✅ Implemented — `policy_url` is editable in the builder, rendered as a link in the SDK banner footer when set, and `BannerService::assertPublishable` blocks publish with a specific error when it's blank (tested in `tests/Feature/BannerTest.php::'blocks publishing without a policy url'`).

---

## US-BAN-6 — Preview and publish

**As an** account owner
**I want** to preview before going live
**So that** I avoid publishing mistakes.

### Acceptance Criteria
- **Given** a draft config, **when** I open preview, **then** I see the banner rendered as visitors would, in each configured language.
- **Given** all validations pass, **when** I publish, **then** the config version becomes live and the live SDK serves it.
- **Given** a published version exists, **then** I can view version history and the active version is clearly marked.

**Status:** ⚠️ Partially implemented — missing: no version history view (list of prior/archived `BannerConfig` versions); the builder only shows the current draft version and a "Published vN" badge (`resources/js/pages/domains/banner.tsx`). Preview only shows the currently-selected language at a time (via the language selector), not all configured languages side by side. Publish itself works end-to-end: `BannerService::publish` validates, archives the prior published version, marks the new one published, and busts the ingest config cache (`ConfigController::cacheKey`) so the live SDK serves it immediately.
