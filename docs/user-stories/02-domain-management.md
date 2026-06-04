# User Stories — Domain Management

Module ref: spec §4.2

---

## US-DOM-1 — Add a domain

**As an** account owner
**I want** to add my website domain
**So that** I can configure consent for it.

### Acceptance Criteria
- **Given** I have no domain yet (free tier = 1), **when** I submit a valid hostname, **then** the domain is created in unverified state.
- **Given** I already have 1 domain on free tier, **when** I try to add another, **then** I am blocked with an explanation of the free-tier limit.
- **Given** I submit an invalid or malformed hostname, **when** I submit, **then** the form rejects it with a clear reason.
- **Given** a domain is added, **then** a unique domain ID and embed snippet are generated.

---

## US-DOM-2 — Verify domain ownership

**As an** account owner
**I want** to prove I control the domain
**So that** consent config and scanning are authorized.

### Acceptance Criteria
- **Given** an unverified domain, **when** I open verification, **then** I am offered DNS TXT, meta tag, and file-upload methods with instructions.
- **Given** I placed the DNS TXT / meta / file token, **when** I trigger verification, **then** the platform checks and marks the domain verified on success.
- **Given** the token is absent or wrong, **when** I verify, **then** verification fails with the detected vs. expected value shown.
- **Given** a domain is unverified, **then** scanning and banner-live status are disabled.

---

## US-DOM-3 — Get embed snippet

**As an** account owner
**I want** a copy-paste script snippet
**So that** I can install the consent SDK on my site.

### Acceptance Criteria
- **Given** a domain exists, **when** I view its install page, **then** I see a `<script>` snippet containing the unique domain ID and a copy button.
- **Given** I copy the snippet, **then** it references the CDN-served SDK and requires no further config to render the banner.

---

## US-DOM-4 — View domain status

**As an** account owner
**I want** to see my domain's state at a glance
**So that** I know what's pending.

### Acceptance Criteria
- **Given** a domain, **when** I view it, **then** I see status indicators: verified (yes/no), scanning state, banner live (yes/no), last scan date.
- **Given** the SDK has been detected loading on the live site, **then** banner-live shows true; otherwise false.

---

## US-DOM-5 — Delete a domain

**As an** account owner
**I want** to remove a domain
**So that** I can free my slot or stop using the platform.

### Acceptance Criteria
- **Given** a domain, **when** I delete it, **then** I am warned this removes banner config and scan data, and asked to confirm.
- **Given** I confirm deletion, **then** the domain, its config, scans, and cookie records are removed; consent logs follow the retention policy (not deleted early).
- **Given** deletion completes, **then** the embed snippet for that domain stops serving an active banner.
