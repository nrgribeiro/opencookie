# Cookie Consent Management Platform — Functional Specification

**Version:** 0.1
**Date:** 2026-05-27
**Scope:** Web targets only. GDPR-first. Free tier (no payment to access).

---

## 1. Purpose & Scope

A SaaS platform that lets website owners collect, store, and enforce visitor
cookie/tracking consent in a way compliant with **GDPR (Reg. 2016/679)** and the
**ePrivacy Directive 2002/58/EC** ("cookie law"). Owners manage their domains,
scan for cookies, configure a consent banner, and prove consent via audit logs
through a dashboard.

**In scope:** web targets (browser-loaded sites).

**Out of scope (now):** native mobile SDKs, server-to-server consent APIs,
payment/billing, CCPA and other non-EU regimes (architecture should allow later
extension).

### Launch Decisions (locked)

- **Ad-tech standards:** Google **Consent Mode v2** supported. IAB TCF v2.2 **not**
  at launch (category-based consent only; TCF deferred).
- **Geo-targeting:** **Global opt-in** — banner shown to every visitor, EU-style
  opt-in for all. No geo-IP branching at launch.
- **Consent log storage:** **Managed, EU-hosted.** Platform stores consent proof
  in EU infrastructure; owners export for audits. No self-host option at launch.
- **Free-tier limits:** 1 domain per account, ~50k consent banner pageviews/month,
  scanner crawls up to 100 pages per domain.
- **Consent log retention:** fixed 24 months, then auto-purge.
- **Cookie classification DB:** built and owned in-house, seeded from public open
  data (Open Cookie Database), curated over time.
- **Deferred to v2 (post-launch):** IAB TCF v2.2 support; multi-user / team roles
  per account.

---

## 2. Actors & Roles

| Actor | Description |
|-------|-------------|
| Visitor | End user browsing a client website; gives/withdraws consent. |
| Account Owner | Registers, owns one or more domains. |
| Team Member (later) | Invited user with scoped access to a domain. |
| Platform Admin | Internal; support, abuse handling, global config. |

---

## 3. Regulatory Requirements → Features

GDPR/ePrivacy demands the platform must satisfy:

1. **Prior consent** — non-essential cookies/scripts must NOT fire before consent.
   → consent-gated script loader.
2. **Granular consent** — per category (Necessary, Preferences, Statistics,
   Marketing). → category toggles.
3. **Freely given** — Reject as easy as Accept (equal prominence, same layer).
   → banner validation rules.
4. **No pre-ticked boxes** — all non-necessary categories default OFF.
5. **Informed** — purpose, vendor, duration, data shared per cookie.
   → cookie declaration table.
6. **Unambiguous** — affirmative action; no implied consent by scrolling.
7. **Withdrawable** — as easy to withdraw as to give. → persistent re-open widget.
8. **Proof of consent** — store what/when/version. → immutable consent log.
9. **Consent renewal** — re-prompt on expiry (≤12 months recommended) or policy
   change.
10. **No cookie walls** — site access not blocked by consent decision.
11. **Necessary cookies** — allowed without consent but must be disclosed.
12. **Records retention** — keep consent proof, but not excessively (defined
    retention).

---

## 4. Functional Modules

### 4.1 Account & Authentication
- Email + password signup, email verification.
- Passkey (WebAuthn) enrollment + sign-in alongside password (`@laravel/passkeys`).
- Password reset, session management.
- (Later) SSO, 2FA (TOTP/SMS).
- Account holds 1 domain (free-tier cap). Multi-domain reserved for future paid
  tiers.

### 4.2 Domain Management
- Add domain; verify ownership (DNS TXT, meta tag, or file upload).
- Per-domain isolated config.
- Generate unique embed snippet (`<script>` with domain ID).
- Status indicators: verified, scanning, banner live, last scan date.

### 4.3 Cookie Scanner
- On-demand + scheduled (e.g. monthly) crawl.
- Detects cookies, localStorage, sessionStorage, trackers, third-party requests.
- Auto-classifies into Necessary / Preferences / Statistics / Marketing using a
  known-cookie database; manual override.
- Per cookie: name, provider, purpose, expiry, type (HTTP/script), domain.
- Flags new/unclassified cookies between scans.
- Crawl limited to 100 pages per domain (free-tier cap).
- Classification uses in-house cookie DB, seeded from Open Cookie Database, curated
  over time.

### 4.4 Consent Banner Builder
- Visual config: layout (box/bar/popup), position, light/dark, colors, logo.
- Buttons: Accept All, Reject All, Customize/Settings — **enforce equal
  prominence** (validation warns if Reject hidden or de-emphasized).
- Granular preferences panel: category toggles + per-category cookie list.
- Necessary category locked ON, non-toggleable, but described.
- Consent model: opt-in (global, all visitors).
- Multi-language: define languages, auto-detect visitor locale, fallback language.
- Link to privacy/cookie policy.
- Preview before publish; versioning of banner config.

### 4.5 Consent Script / Enforcement (client SDK)
- Lightweight JS loaded first on the page.
- Renders banner if no valid consent record present.
- **Blocks non-necessary scripts** until consent. Developer tags scripts
  (`type="text/plain" data-cmp-category="marketing"`) which the SDK activates only on
  consent. Plus auto-blocking of known third-party tags where feasible.
- **Google Consent Mode v2:** SDK pushes default + updated consent signals
  (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`) to
  `gtag`/dataLayer, defaulting all to `denied` before consent.
- Stores consent state in a first-party cookie/localStorage (consent string +
  version + timestamp + categories).
- Re-prompts on: expiry, policy version bump, no prior record.
- Exposes API: `getConsent()`, `onConsentChange()`, `showSettings()`.
- Sends consent event to backend log endpoint.
- **Fail-safe:** if backend unreachable, banner still renders and blocks (never
  silently allow tracking).
- Performance: small footprint, async, CDN-served, minimal layout shift.

### 4.6 Consent Logging & Proof (managed, EU-hosted)
- Each consent action → record: pseudonymous consent ID (not PII where avoidable),
  truncated/hashed IP, timestamp, categories accepted/rejected, banner version,
  consent text shown, method (accept-all / reject-all / custom).
- Append-only / immutable store, EU region.
- Exportable (CSV/JSON) for DPA audits.
- Retention: fixed 24 months, then auto-purge.

### 4.7 Cookie Declaration / Policy Generator
- Auto-generated, embeddable cookie declaration table from scan results, kept in
  sync.
- Embed snippet for the site's privacy/cookie policy page.
- Multi-language declaration.

### 4.8 Dashboard & Analytics
- Per domain: consent rate (accept/reject/partial), banner impressions, opt-in by
  category, trend over time.
- Scan summary: total cookies by category, unclassified count.
- Compliance health checklist (banner live? reject button present? policy linked?
  scan recent?).
- Recent consent log preview + export.

### 4.9 Settings
- Banner appearance/content, languages, consent expiry duration, policy version
  control (bump triggers re-consent), notification prefs (new-cookie alert email).

---

## 5. Consent Flow (runtime)

1. Page loads SDK first; non-necessary tags held; Consent Mode defaults = `denied`.
2. SDK checks for valid consent record (exists, not expired, version matches).
3. If valid → apply stored prefs, activate permitted categories, update Consent
   Mode signals, no banner.
4. If invalid/absent → show banner; nothing non-necessary fires.
5. Visitor chooses → SDK writes consent record, activates matching categories,
   updates Consent Mode signals, POSTs log event.
6. Persistent widget allows re-open → withdraw/change anytime.

---

## 6. Data Model (sketch)

- **User**(id, email, password_hash, verified_at, created_at)
- **Domain**(id, user_id, hostname, verify_status, created_at)
- **Scan**(id, domain_id, started_at, status, pages_crawled)
- **Cookie**(id, domain_id, name, provider, category, purpose, expiry, source,
  first_seen, last_seen)
- **BannerConfig**(id, domain_id, version, layout, theme, languages, content_json,
  published_at)
- **ConsentRecord**(id, domain_id, consent_id, timestamp, categories_json,
  banner_version, method, ip_hash, user_agent)
- **PolicyVersion**(id, domain_id, version, effective_at)

---

## 7. Non-Functional Requirements

- **Privacy by design:** minimize PII in logs; pseudonymize; consent data hosted
  in EU.
- **Security:** encryption in transit + at rest, per-domain access control, admin
  access audit.
- **Availability:** SDK fail-safe (down backend never enables tracking).
- **Performance:** SDK small + CDN-served; fast banner render.
- **Data retention & deletion:** configurable; support data-subject and account
  deletion.
- **Accessibility:** banner WCAG-conformant (keyboard, contrast, screen reader).

---

## 8. Resolved Decisions

| Topic | Decision |
|-------|----------|
| Ad-tech standards | Google Consent Mode v2 at launch; IAB TCF v2.2 deferred to v2. |
| Geo-targeting | Global opt-in (banner shown to all visitors). |
| Consent log storage | Managed, EU-hosted; export for audits; no self-host. |
| Free-tier limits | 1 domain, ~50k pageviews/mo, scanner ≤100 pages/domain. |
| Log retention | Fixed 24 months, then auto-purge. |
| Multi-user / teams | Deferred to v2; single owner per account at launch. |
| Cookie classification DB | In-house, seeded from Open Cookie Database, curated. |

### Remaining to define later
- Paid-tier structure (when free limits exceeded).
- WCAG conformance target level (A / AA).
- Scanner engine choice (headless browser vs. crawler).
- Pageview metering enforcement (soft warn vs. hard block at 50k).
