# User Stories — Platform Administration

Module ref: spec §4.10

Super-admin–only. All stories require the `super_admin` role; non-admins get
`403` on every `/admin/*` route.

---

## US-ADMIN-1 — Access the admin area

**As a** super admin
**I want** a dedicated admin area separate from the owner dashboard
**So that** I can administer the platform.

### Acceptance Criteria
- **Given** I have the `super_admin` role, **when** I sign in, **then** an "Admin" entry appears in the nav and `/admin` is reachable.
- **Given** I am a normal account owner, **when** I request any `/admin/*` route, **then** I get `403` and see no admin nav.
- **Given** the admin area, **then** it is gated by `auth` + `verified` + `role:super_admin` middleware.

**Status:** ✅ Implemented — `routes/web.php` groups all `/admin/*` routes under `['auth', 'verified', 'role:super_admin']`; `resources/js/components/app-sidebar.tsx` conditionally renders the "Admin" nav item on `auth.isSuperAdmin` (shared by `HandleInertiaRequests`).

---

## US-ADMIN-2 — Platform overview & statistics

**As a** super admin
**I want** a platform dashboard with key statistics
**So that** I understand how the platform is being used.

### Acceptance Criteria
- **Given** the admin dashboard, **then** I see totals: users, domains, verified domains, banners live, scans run, consent records logged.
- **Given** the admin dashboard, **then** I see how many domains are fully compliant vs. not, and users grouped by tier.
- **Given** recent activity, **then** I see recent signups and recent scans.

**Status:** ✅ Implemented — `App\Http\Controllers\Admin\DashboardController` returns users/domains/verified domains/banners live/scans/consent record totals, compliant vs. non-compliant counts, users-by-tier breakdown, and the 10 most recent signups and scans.

---

## US-ADMIN-3 — Signal non-compliant domains

**As a** super admin
**I want** non-compliant domains surfaced
**So that** I can spot at-risk accounts and reach out.

### Acceptance Criteria
- **Given** domains across all accounts, **then** the dashboard lists the ones that are **not** fully compliant.
- **Given** a non-compliant domain, **then** I see which health checks fail (banner live, Reject button, policy linked, scan recent, no unclassified cookies — reuses US-DASH-3 logic).
- **Given** the list, **then** each row links to the owning user and the domain.

**Status:** ⚠️ Partially implemented — missing: `DashboardController` computes the non-compliant list (reusing `DomainCompliance`, shared with US-DASH-3) with owner name/email and failing-check labels, and `admin/dashboard.tsx` links each row's owner to `admin.users.edit`, but there is no link to the domain itself (no admin-side domain detail route/page exists).

---

## US-ADMIN-4 — Manage users

**As a** super admin
**I want** to view and manage user accounts
**So that** I can support and administer them.

### Acceptance Criteria
- **Given** the users list, **then** I see each user's name, email, tier, role, domain count, and signup date, with search and pagination.
- **Given** a user, **when** I edit them, **then** I can change their tier and grant/revoke the `super_admin` role.
- **Given** I am the last super admin, **when** I try to revoke my own role or delete my account, **then** I am blocked (platform must keep ≥1 super admin).
- **Given** I delete a user, **then** their domains and domain-scoped data are removed; consent logs follow the retention policy (not deleted early).

**Status:** ✅ Implemented — `UserController` provides paginated/searchable index (name, email, tier, role, domain count, signup date), edit (tier + `is_super_admin` toggle), and delete, with `isLastSuperAdmin()` blocking both role revocation and account deletion for the last super admin. Cascade is enforced at the schema level: `domains.user_id` and all domain-scoped tables cascade on delete, while `consent_records.domain_id` is nullable with no FK (migration `2026_06_04_000001_drop_consent_records_domain_fk.php`), so consent logs survive and are purged only by the 24-month retention job.

---

## US-ADMIN-5 — Manage tiers

**As a** super admin
**I want** to define account tiers and their limits
**So that** I can control what each account can do.

### Acceptance Criteria
- **Given** the tiers list, **then** I see each tier's name, limits (max domains, max scan pages, monthly pageview cap, scheduled scans allowed), default flag, and user count.
- **Given** a tier, **when** I create or edit it, **then** I can set its limits; an unlimited value (e.g. max domains) is expressed as null/∞.
- **Given** exactly one tier is marked default, **when** I mark another default, **then** the previous default is cleared (always exactly one default).
- **Given** a tier with users assigned, **when** I try to delete it, **then** I am blocked until those users are reassigned; the default tier cannot be deleted.
- **Given** a user's tier limits, **then** domain creation and scanner caps are enforced from the tier (replaces the hard-coded free-tier constants).

**Status:** ✅ Implemented — `TierController` lists tiers with limits, default flag, and user count; `StoreTierRequest`/`update` allow setting limits (nullable columns express unlimited); `keepSingleDefault()` clears the previous default whenever a new one is saved; `destroy()` blocks deleting the default tier or a tier with assigned users. `RunScanJob` reads `max_scan_pages` (falling back to `DEFAULT_PAGE_LIMIT`) and domain creation is capped via `max_domains`/`User::resolveTier()`, replacing hard-coded constants.
