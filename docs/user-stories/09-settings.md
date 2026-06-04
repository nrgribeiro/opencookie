# User Stories — Settings

Module ref: spec §4.9

---

## US-SET-1 — Configure consent expiry

**As an** account owner
**I want** to set how long consent stays valid
**So that** visitors are re-prompted appropriately.

### Acceptance Criteria
- **Given** settings, **when** I set a consent expiry duration (≤12 months recommended), **then** the SDK treats records older than that as invalid and re-prompts.
- **Given** I set a value above the recommended max, **then** I am warned about GDPR guidance.

---

## US-SET-2 — Manage policy versioning

**As an** account owner
**I want** to bump my policy version
**So that** material changes trigger fresh consent.

### Acceptance Criteria
- **Given** a policy change, **when** I publish a new policy version, **then** existing consent records become invalid and visitors are re-prompted on next visit.
- **Given** version history, **then** I can see effective dates of each version.

---

## US-SET-3 — Notification preferences

**As an** account owner
**I want** to control alerts
**So that** I'm informed without noise.

### Acceptance Criteria
- **Given** settings, **when** I enable "new cookie" alerts, **then** I receive an email when a scan finds new/unclassified cookies.
- **Given** I disable alerts, **then** no such emails are sent.

---

## US-SET-4 — Manage account and data deletion

**As an** account owner
**I want** to delete my account and data
**So that** I can exercise data-subject rights.

### Acceptance Criteria
- **Given** account settings, **when** I request account deletion, **then** I am warned of consequences and asked to confirm.
- **Given** I confirm, **then** account, domains, configs, scans, and cookie records are removed; consent logs persist until the 24-month retention elapses, then purge.
- **Given** deletion completes, **then** I am logged out and can no longer authenticate.

---

## US-SET-5 — Edit banner content and languages

**As an** account owner
**I want** to manage banner text and languages from settings
**So that** I keep messaging current.

### Acceptance Criteria
- **Given** settings, **when** I edit banner content or add/remove languages, **then** changes flow into a new draft banner config (publish per US-BAN-6).
- **Given** a required language/text gap, **when** I attempt publish, **then** validation blocks it.
