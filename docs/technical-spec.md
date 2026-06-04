# Technical Requirements Specification — Cookie Consent Management Platform

**Version:** 0.1
**Date:** 2026-05-27
**Companion docs:** [functional-spec.md](functional-spec.md), [user-stories/](user-stories/README.md)

Covers infrastructure architecture, application architecture, data architecture,
API contracts, the client SDK, security, and cross-cutting technical requirements.
Decisions inherit the launch constraints in functional-spec §1 / §8.

---

## 1. Tech Stack (fixed by existing project)

| Layer | Choice |
|-------|--------|
| Backend framework | Laravel 13, PHP 8.3 |
| Auth | Laravel Fortify + `@laravel/passkeys` (WebAuthn) |
| Dashboard UI | Inertia 3 + React 19 (React Compiler) + TypeScript |
| UI kit | Radix / shadcn, Tailwind 4, Sonner (toasts), Lucide icons |
| Build | Vite 8, Wayfinder (typed routes, pre-1.0) |
| Tests | Pest 4 + `pest-plugin-laravel` (PHP), tsc + ESLint (front) |
| Lint/format | Pint (PHP), Prettier/ESLint (TS) |
| Dev tooling | Laravel Boost, Pao, Chisel, Pail (log tail), Sail |
| Dev DB | SQLite |
| Queue (dev) | `queue:listen` (run via `composer dev` concurrent stack: serve + queue + pail + vite) |

The **dashboard** is the existing Laravel + Inertia app. The **SDK** and **consent
ingest** are new components with different runtime constraints (see §4).

---

## 2. System Context

Five logical components:

1. **Dashboard app** (Laravel + Inertia) — owner-facing: auth, domains, banner
   builder, scan results, analytics, exports, settings.
2. **Consent SDK** (standalone TS bundle, CDN-served) — runs on customer sites;
   renders banner, blocks scripts, captures consent, emits Consent Mode v2 signals.
3. **Consent Ingest API** (Laravel API routes, separately scalable) — serves banner
   config, receives consent + impression events. High read + write, public.
4. **Scanner service** (headless-browser workers) — crawls domains, detects cookies.
5. **Background jobs** (Laravel queue) — scan orchestration, retention purge,
   notifications, change detection.

```
Visitor browser ──loads──> [CDN: SDK bundle]
       │                          │
       │ GET config / POST consent│
       ▼                          ▼
[Consent Ingest API] ◀──reads/writes──▶ [Primary DB] + [Consent Log store (EU)]
       ▲
Owner browser ──> [Dashboard app (Inertia)] ──jobs──> [Queue] ──> [Scanner workers]
```

---

## 3. Infrastructure Architecture (Azure)

Cloud provider: **Microsoft Azure**. All resources provisioned in EU regions.

### 3.1 Regions & residency
- Primary region **West Europe**; paired region **North Europe** for backup/geo-
  redundancy (consent logs must be EU-hosted, spec §4.6).
- Single active region at launch; design allows read replicas / paired-region DR later.

### 3.2 Component → Azure service mapping
| Concern | Azure service | Notes |
|---------|---------------|-------|
| App hosting (dashboard + API) | **Azure Container Apps** | Same image, role via env; ingress + built-in autoscale; dashboard and ingest as separate apps/revisions, scaled independently. |
| Container registry | **Azure Container Registry (ACR)** | Build/push app + scanner images. |
| Edge / CDN / WAF | **Azure Front Door (Standard/Premium)** | Global edge for SDK bundle + ingest read cache; built-in WAF for OWASP rules + rate limiting. |
| SDK + export storage | **Azure Blob Storage (EU)** | Immutable, versioned containers; SDK bundles long-cached, export files short-lived. |
| Primary DB | **Azure Database for PostgreSQL — Flexible Server (EU)** | Replaces SQLite for prod; SQLite stays local dev. |
| Consent log store | Same Postgres Flexible Server, partitioned table (see §5.3) | High-availability (zone-redundant) tier; can split to dedicated server later. |
| Queue + cache | **Azure Cache for Redis (EU)** | Laravel queue (Horizon) + banner-config cache for ingest hot path. |
| Scanner workers | **Azure Container Apps Jobs** (event-driven, KEDA queue scaling) | Playwright headless Chromium; isolated from web tier; outbound-only; scale-to-zero when idle. |
| Secrets | **Azure Key Vault** | App pulls via Managed Identity; nothing committed. |
| Identity / resource auth | **Managed Identity (Entra ID)** | App → Key Vault / Blob / Postgres / Redis without static creds where supported. |
| Transactional email | **Azure Communication Services — Email** | Verification, password reset, cookie alerts (US-AUTH-2/4, US-SET-3). |
| Observability | **Azure Monitor + Application Insights + Log Analytics** | See §10. |

### 3.3 Environments
- `local` (SQLite, `composer dev` concurrent stack), `staging`, `production` — each
  its own Azure resource group.
- **IaC: Bicep** (or Terraform) for all Azure resources; secrets in Key Vault,
  never committed (`.env` gitignored).
- CI/CD: GitHub Actions → build image → push ACR → deploy Container Apps revision.

### 3.4 CDN strategy for SDK (Azure Front Door + Blob)
- SDK bundle stored in Blob Storage, fronted by Azure Front Door.
- Path: `https://cdn.<platform>/sdk/v1/cmp.js` (loader) — small, long-cached at edge.
- Loader fetches per-domain config from Ingest API via Front Door (cacheable, short
  TTL) so banner changes propagate without re-deploying the bundle.
- Front Door WAF applies rate limiting + bot rules to public ingest endpoints.

---

## 4. Application Architecture

### 4.1 Dashboard app (Laravel + Inertia)
- Module boundaries mirror functional spec: `Auth`, `Domains`, `Scanner`,
  `Banner`, `Consent` (logs/export), `Declaration`, `Analytics`, `Settings`.
- Suggested layout: action/service classes per module under `app/`, Inertia React
  pages under `resources/js/pages/<module>`.
- Auth via Fortify (login, register, verify email, password reset) — maps to
  US-AUTH-1..5. Passkey enrollment + sign-in via `@laravel/passkeys` (WebAuthn);
  passkeys complement password auth at launch.
- Authorization: policies scope every resource to the owning user; a user can only
  touch their own domain and its children.
- Typed front-end routes via Wayfinder.

### 4.2 Consent Ingest API
- Stateless Laravel routes under `/v1/c/*` (no session; public).
- Separate rate limits + caching from dashboard.
- Read path (`config`) served from Redis cache, invalidated on banner publish.
- Write path (`consent`, `impression`) validates, enqueues/inserts to log store.
- CORS: permissive for `GET config` and `POST consent` (called from any customer
  origin), but payloads validated and bound to a known domain ID + origin check.

### 4.3 Consent SDK (standalone)
- Built as a separate Vite/TS target → single minified ES bundle, **no React**
  (keep footprint small; vanilla TS + tiny DOM rendering).
- Responsibilities: load config, render banner (multi-language), block/unblock
  tagged scripts, manage first-party consent record, push Consent Mode v2 signals,
  POST events, expose JS API (`getConsent`, `onConsentChange`, `showSettings`).
- Fail-safe: if config/ingest unreachable → render banner, keep non-necessary
  blocked, queue events (US-SDK-6).
- Versioned (`/sdk/v1/`); breaking changes ship under new major path.

### 4.4 Scanner service
- Triggered by queue job (on-demand US-SCAN-1, scheduled US-SCAN-5).
- Playwright headless Chromium: load up to 100 pages/domain, capture
  `document.cookie`, storage, and network (3rd-party hosts).
- Classifies against in-house cookie DB (seeded Open Cookie Database); unmatched →
  "Unclassified".
- Persists `Scan` + `Cookie` rows; computes diff vs prior scan (US-SCAN-4);
  triggers notification job on new/unclassified.

### 4.5 Background jobs
- `RunScanJob`, `ClassifyCookiesJob`, `DetectChangesJob`, `SendCookieAlertJob`,
  `PurgeExpiredConsentJob` (daily; 24-month cutoff), `BuildExportJob`.

---

## 5. Data Architecture

### 5.1 Core tables (Postgres)
Mirrors functional-spec §6:
`users`, `domains`, `domain_verifications`, `scans`, `cookies`,
`cookie_overrides`, `banner_configs` (versioned), `policy_versions`,
`consent_records`, `notification_settings`.

### 5.2 Key relationships
- `users 1─N domains` (free tier enforces max 1 active domain).
- `domains 1─N scans 1─N cookies`.
- `domains 1─N banner_configs` (one published version flagged).
- `domains 1─N consent_records`.

### 5.3 Consent log store
- High write volume; **append-only** (no UPDATE/DELETE except retention purge).
- Table partitioned by month on `created_at` → cheap 24-month purge by dropping
  old partitions.
- Indexed by `(domain_id, created_at)` for export/range queries.
- Stored fields: `id`, `domain_id`, `consent_id` (pseudonymous UUID), `created_at`,
  `categories` (JSONB granted/denied), `method`, `banner_version`,
  `policy_version`, `consent_text_hash`, `ip_hash` (salted), `user_agent`.
- **No raw IP, no PII** beyond what's listed.

### 5.4 Retention
- `PurgeExpiredConsentJob` drops partitions older than 24 months (spec §4.6).
- Domain deletion removes domain-scoped tables' rows but **not** consent logs
  early (US-LOG-4 / US-SET-4) — they age out by partition.

---

## 6. API Contracts

Two surfaces: **Public Ingest API** (`/v1/c/*`, used by SDK) and **Dashboard API**
(Inertia, session-authed). Only the public surface needs a stable wire contract.

### 6.1 Public Ingest API

Base: `https://api.<platform>/v1/c`

#### GET `/{domainId}/config`
Returns published banner config + categories for the SDK. Cacheable.

**Response 200**
```json
{
  "domainId": "dom_8f3a...",
  "bannerVersion": 7,
  "policyVersion": 3,
  "consentExpiryDays": 365,
  "defaultLanguage": "en",
  "languages": ["en", "pt", "de"],
  "policyUrl": "https://example.com/cookies",
  "layout": { "type": "box", "position": "bottom-left", "theme": "light" },
  "categories": [
    { "id": "necessary", "required": true,  "name": {"en":"Necessary"}, "description": {"en":"..."} },
    { "id": "preferences", "required": false, "name": {"en":"Preferences"}, "description": {"en":"..."} },
    { "id": "statistics", "required": false, "name": {"en":"Statistics"}, "description": {"en":"..."} },
    { "id": "marketing", "required": false, "name": {"en":"Marketing"}, "description": {"en":"..."} }
  ],
  "consentModeMap": {
    "analytics_storage": ["statistics"],
    "ad_storage": ["marketing"],
    "ad_user_data": ["marketing"],
    "ad_personalization": ["marketing"]
  }
}
```
**Errors:** `404` unknown/unverified domain; `410` domain deleted.

#### POST `/{domainId}/consent`
Logs a consent action. Called on choice + change.

**Request**
```json
{
  "consentId": "c_b21f...",          // SDK-generated pseudonymous UUID; new if absent
  "method": "custom",                 // accept_all | reject_all | custom
  "bannerVersion": 7,
  "policyVersion": 3,
  "categories": {
    "necessary": true,
    "preferences": true,
    "statistics": false,
    "marketing": false
  },
  "consentTextHash": "sha256:...",    // hash of text shown
  "language": "en",
  "ts": "2026-05-27T09:32:11Z"
}
```
**Response 201**
```json
{ "consentId": "c_b21f...", "stored": true, "expiresAt": "2027-05-27T09:32:11Z" }
```
**Server adds:** salted `ip_hash`, `user_agent`, server `created_at`.
**Validation:** category keys must match config; `bannerVersion`/`policyVersion`
must be known. **Errors:** `422` invalid payload; `404` unknown domain;
`429` rate-limited.

#### POST `/{domainId}/impression` (optional beacon)
Fire-and-forget banner-shown count for analytics.
```json
{ "bannerVersion": 7, "language": "en", "ts": "..." }
```
**Response:** `204`.

#### GET `/{domainId}/declaration.js`
Returns embeddable script that renders the live cookie declaration table
(US-DECL-2). Cacheable; reflects latest scan.

### 6.2 Dashboard API (Inertia, session-authed)
Not a public contract; rendered via Inertia controllers. Representative routes:

| Method | Route | Story |
|--------|-------|-------|
| POST | `/domains` | US-DOM-1 |
| POST | `/domains/{id}/verify` | US-DOM-2 |
| GET | `/domains/{id}/install` | US-DOM-3 |
| DELETE | `/domains/{id}` | US-DOM-5 |
| POST | `/domains/{id}/scans` | US-SCAN-1 |
| PATCH | `/cookies/{id}` | US-SCAN-3 |
| GET/PUT | `/domains/{id}/banner` | US-BAN-* |
| POST | `/domains/{id}/banner/publish` | US-BAN-6 |
| GET | `/domains/{id}/consent/export` | US-LOG-3 |
| GET | `/domains/{id}/analytics` | US-DASH-1 |
| PUT | `/domains/{id}/settings` | US-SET-* |

All scoped by policy to the authenticated owner.

---

## 7. Client SDK Design Detail

- **Loader** (`cmp.js`): tiny; reads `data-domain` attr, fetches `/config`, boots
  core. Async, non-blocking.
- **Script gating:** site tags use `type="text/plain" data-cmp-category="marketing"`;
  on grant the SDK rewrites to executable `type="text/javascript"`. Known 3rd-party
  hosts auto-blocked via a blocklist where feasible.
- **Consent Mode v2:** before any choice, push
  `gtag('consent','default',{ ad_storage:'denied', analytics_storage:'denied',
  ad_user_data:'denied', ad_personalization:'denied' })`; on choice push `update`
  derived from `consentModeMap`.
- **Storage:** first-party cookie + `localStorage` mirror holding
  `{consentId, categories, bannerVersion, policyVersion, ts}`; invalid when expired,
  or version mismatch → re-prompt.
- **Budget target:** loader < ~15 KB gzip (hard target to be validated in build).

---

## 8. Security Requirements

- **Transport:** TLS everywhere; HSTS on dashboard.
- **AuthN:** Fortify; password policy + throttling (US-AUTH-3); email verification
  gate; passkey (WebAuthn) sign-in via `@laravel/passkeys` as a phishing-resistant
  alternative to password.
- **AuthZ:** per-resource policies; deny cross-tenant access; ingest API binds
  every write to a valid `domainId` and checks `Origin`/`Referer` against the
  domain's verified hostname.
- **Input validation:** Form Requests for dashboard; strict schema validation for
  ingest payloads (reject unknown category keys, oversized bodies).
- **Rate limiting:** per-IP + per-domain throttles on `POST /consent` and
  `/impression`; protects against log flooding.
- **PII minimization:** salted `ip_hash` only; no raw IP; pseudonymous `consentId`.
- **Secrets:** Azure Key Vault, accessed via Managed Identity; never committed.
- **CORS:** open for public ingest GET/POST but no credentials; dashboard CORS
  locked to platform origin.
- **Headers:** CSP on dashboard; the embeddable SDK documented for customers'
  CSP (`script-src` allowance for the CDN host).
- **Audit:** admin access + retention purges are auditable.

---

## 9. Scalability & Performance

- **Hot path = `GET /config`:** cached in Redis + CDN micro-cache; invalidated on
  publish. Target single-digit ms server time on cache hit.
- **Write path = `POST /consent`:** validate fast, write to partitioned table;
  consider buffered/queued insert if volume spikes.
- **Stateless API tier:** horizontal scale behind LB; SDK from CDN absorbs most
  read load.
- **Scanner isolation:** workers separate from web tier; concurrency-capped; per-job
  timeout; 100-page cap bounds cost.
- **Free-tier metering:** count impressions per domain/month; soft-warn approaching
  50k (hard-block policy = "to define", spec §8).

---

## 10. Observability (Azure Monitor stack)

- **Logs:** structured app logs (Laravel Pail in dev) → **Log Analytics** in prod
  via Container Apps diagnostic settings.
- **Metrics + tracing:** **Application Insights** — ingest QPS, cache hit ratio,
  consent accept/reject counts, scan success/fail, queue depth, job latency;
  distributed traces SDK → ingest → DB via request IDs.
- **Alerting:** **Azure Monitor alerts** on ingest error rate, queue backlog,
  scanner failure rate, retention-job failures → action groups (email/webhook).
- **Health:** Container Apps liveness/readiness probes for web + worker tiers.
- **Dashboards:** Azure Monitor Workbooks for ops + compliance views.

---

## 11. Compliance & Data Residency (cross-cutting)

- All consent data stored/processed in EU — Azure West/North Europe only; no
  resource provisioned outside EU regions (spec §4.6).
- 24-month retention enforced by partition purge (§5.4).
- Data-subject/account deletion supported (US-SET-4); consent proof ages out by
  partition, not deleted early.
- Fail-safe default-deny in SDK (US-SDK-6) — no tracking without consent even
  during outages.
- Equal-prominence + no-pre-tick enforced at banner publish validation (US-BAN-2/3).

---

## 12. Technical Decisions & Open Items

### Decided
- Cloud = **Azure**, EU regions (West Europe primary, North Europe paired).
- Hosting = **Azure Container Apps** (web + ingest separate apps; scanner as
  Container Apps Jobs).
- Data = **Postgres Flexible Server** (prod) + **Azure Cache for Redis** + **Blob
  Storage**; **Azure Front Door** for CDN/WAF.
- Secrets in **Key Vault**, accessed via **Managed Identity**.
- Email via **Azure Communication Services**.
- IaC = **Bicep**; CI/CD = GitHub Actions → ACR → Container Apps.
- SDK = standalone vanilla-TS bundle, not React.
- Consent logs = monthly-partitioned append-only table.

### Open
- Container Apps Consumption vs Dedicated plan — pick per load/cost.
- Postgres tier sizing + zone-redundant HA on/off at launch.
- Scanner concurrency limits + cost ceiling (Container Apps Jobs parallelism).
- Free-tier overage enforcement (soft warn vs hard block at 50k pv).
- Buffered consent ingestion (direct insert vs queue) — decide under load test.
- Multi-region read replicas / active-active — post-launch if latency demands.
- Front Door Standard vs Premium (Premium adds Private Link + richer WAF).
