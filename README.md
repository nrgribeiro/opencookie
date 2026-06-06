# OpenCookie

A self-serve **Cookie Consent Management Platform (CMP)** for website owners.
OpenCookie lets you scan a site for cookies, configure a consent banner, serve a
lightweight consent SDK to visitors, enforce that consent by gating tracking
scripts, and keep an auditable log of every choice — built **GDPR-first**
(Reg. 2016/679) and aligned with the ePrivacy Directive 2002/58/EC.

> **Scope:** web targets only. Category-based consent with Google **Consent
> Mode v2**. IAB TCF v2.2 and native mobile SDKs are out of scope at launch.

---

## Features

- **Domain management & verification** — register a domain, verify ownership via
  a token, get a ready-to-paste embed snippet.
- **Cookie scanner** — crawl a site (multi-page, same-host) to detect
  first/third-party cookies and trackers, grouped by category. Two drivers: a
  dependency-free HTTP crawler, or a headless Chromium crawler that executes JS
  and captures client-side cookies (see [Cookie Scanning](#cookie-scanning)).
- **Cookie classification** — cookies auto-classified from an in-house database
  seeded from the public [Open Cookie Database](https://github.com/jkwakman/Open-Cookie-Database),
  with per-domain overrides.
- **Banner builder** — configure layout, theme, colors, and multi-language
  content for the consent banner; preview and publish versioned banners.
- **Consent SDK** (`resources/sdk/cmp.ts`) — a standalone, framework-free script
  served from `public/sdk/v1/cmp.js`. It:
  - renders the banner and captures Accept / Reject / per-category choices,
  - **gates tracking scripts** until consent (`type="text/plain"
    data-cmp-category="..."` tags + auto-blocking of known vendors),
  - emits **Google Consent Mode v2** signals,
  - persists consent and exposes a floating re-open button so visitors can
    change preferences at any time (Art. 7(3)).
- **Consent logging & export** — every choice stored as proof, exportable for
  audits; fixed 24-month retention.
- **Policy versioning** — bump policy/banner versions to re-prompt visitors.
- **Dashboard** — domains, scans, banner impressions, and consent stats.
- **Auth** — email/password via Laravel Fortify, with passkey support.

---

## Tech Stack

| Layer      | Tech |
|------------|------|
| Backend    | PHP 8.3, Laravel 13, Inertia (server adapter) |
| Frontend   | React 19 + TypeScript, Inertia, Tailwind CSS v4, Radix UI, lucide-react |
| Consent SDK| Vanilla TypeScript, bundled with Vite (no framework) |
| Auth       | Laravel Fortify + `@laravel/passkeys` |
| Database   | SQLite (default); any Laravel-supported driver |
| Queue      | Database driver (scans run as queued jobs) |
| Build      | Vite |

---

## Public Ingest API

Endpoints consumed by the SDK on customer sites (prefix `/v1/c`):

| Method | Path | Purpose |
|--------|------|---------|
| GET  | `/{domainUid}/config`        | Banner + category config (JSON) |
| POST | `/{domainUid}/consent`       | Record a consent choice |
| POST | `/{domainUid}/impression`    | Record a banner impression |
| GET  | `/{domainUid}/declaration.js`| Embeddable cookie declaration |

---

## Getting Started

### Prerequisites

- PHP **8.3+** and Composer
- Node.js **20+** and npm

### Install

```bash
git clone <repo-url> opencookie
cd opencookie

# PHP deps
composer install

# JS deps
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database (SQLite by default)
touch database/database.sqlite
php artisan migrate

# Seed baseline data (cookie classifications, demo records)
php artisan db:seed
```

### Run (development)

```bash
# Build the consent SDK once (outputs public/sdk/v1/cmp.js)
npm run build:sdk

# App assets (Vite dev server) + Laravel
npm run dev
php artisan serve

# Process queued jobs (scans, etc.)
php artisan queue:work
```

App: http://localhost:8000

### Build (production)

```bash
npm run build       # app assets
npm run build:sdk   # consent SDK
# optionally: npm run build:ssr
```

> **Note:** `public/sdk/v1/cmp.js` is served as a static asset. When deploying
> behind a CDN (e.g. Cloudflare), purge or version-bust the SDK URL after each
> rebuild so visitors receive the latest version.

---

## Cookie Scanning

Scans run as queued jobs (`RunScanJob`, up to 100 pages per domain). The active
scanner is chosen by `SCANNER_DRIVER`:

| Driver | JS executed? | Detects | Dependencies |
|--------|:---:|---------|--------------|
| `http` *(default)* | ❌ | `Set-Cookie` headers, third-party `<script src>` hosts | none |
| `playwright` | ✅ | full browser cookie jar incl. **client-side cookies** (`_ga`, `_fbp`, …), `localStorage`/`sessionStorage` keys, third-party script/pixel hosts | Node + Playwright/Chromium |

The HTTP driver is fine for a first pass, but most analytics/marketing cookies
are written by JavaScript and are **only** seen by the `playwright` driver.

### Enabling the headless (Playwright) driver

```bash
npm install                  # installs the `playwright` package
npm run scan:install         # downloads Chromium (npx playwright install chromium)
```

Then set in `.env`:

```dotenv
SCANNER_DRIVER=playwright
# optional overrides:
SCANNER_NODE_BINARY=node
SCANNER_TIMEOUT=180          # whole-crawl wall-clock cap (seconds)
SCANNER_PAGE_TIMEOUT_MS=15000
```

The PHP scanner ([`PlaywrightSiteScanner`](app/Services/Scanner/PlaywrightSiteScanner.php))
shells out to [`scanner/crawl.mjs`](scanner/crawl.mjs) and parses its JSON. If
the crawl process fails (e.g. Chromium missing), the scan is marked **failed**
with a clear error rather than silently returning partial data. The queue worker
(`php artisan queue:work`) must run on a host where Node + Chromium are available.

## Embedding the SDK on a Site

After verifying a domain, copy the snippet from its detail page. It looks like:

```html
<script src="https://your-opencookie-host/sdk/v1/cmp.js" data-domain="<DOMAIN_UID>" async></script>
```

### Gating tracking scripts

Mark any tracking script `type="text/plain"` with a category. The SDK activates
it only after the visitor grants that category:

```html
<!-- Runs only after "statistics" consent -->
<script type="text/plain" data-cmp-category="statistics" async
        src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX"></script>
<script type="text/plain" data-cmp-category="statistics">
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXX');
</script>
```

Categories: `necessary` (always on), `preferences`, `statistics`, `marketing`.
Load the SDK **before** tagged scripts. Google Consent Mode v2 signals are
emitted automatically.

---

## Useful Commands

```bash
npm run lint          # ESLint (autofix)
npm run format        # Prettier
npm run types:check   # tsc --noEmit
php artisan test      # test suite
```

---

## Documentation

See [`docs/`](docs/) for the functional spec, technical spec, data model, and
user stories.
```