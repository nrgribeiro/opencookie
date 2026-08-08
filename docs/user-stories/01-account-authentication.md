# User Stories — Account & Authentication

Module ref: spec §4.1

---

## US-AUTH-1 — Sign up with email

**As a** prospective account owner
**I want** to register with my email and a password
**So that** I can access the platform and manage my domain.

### Acceptance Criteria
- **Given** I am on the signup page, **when** I submit a valid email and a password meeting policy, **then** an account is created in unverified state and a verification email is sent.
- **Given** I submit an email already registered, **when** I attempt signup, **then** I see a non-enumerating message (e.g. "Check your email to continue") and no duplicate account is created.
- **Given** I submit a password below policy (min length, complexity), **when** I submit, **then** the form is rejected with a clear reason.
- **Given** signup succeeds, **then** I cannot access protected dashboard areas until verified.

**Status:** ⚠️ Partially implemented — missing: non-enumeration on duplicate signup. `App\Actions\Fortify\CreateNewUser` validates email with a plain `Rule::unique(User::class)`, so a duplicate email returns the standard "The email has already been taken" validation error instead of a neutral message.

---

## US-AUTH-2 — Verify email

**As a** new account owner
**I want** to confirm my email via a link
**So that** my account is activated.

### Acceptance Criteria
- **Given** I received a verification email, **when** I click the link with a valid unexpired token, **then** my account becomes verified and I am redirected to the dashboard.
- **Given** the token is expired or already used, **when** I click it, **then** I see an error and can request a new verification email.
- **Given** I am unverified, **when** I request a resend, **then** a new email is sent and prior tokens are invalidated.

**Status:** ✅ Implemented — via Laravel Fortify's built-in signed, time-limited verification links and resend flow (`config/auth.php` `verification.expire`).

---

## US-AUTH-3 — Log in

**As a** verified account owner
**I want** to log in with my credentials
**So that** I can reach my dashboard.

### Acceptance Criteria
- **Given** valid credentials for a verified account, **when** I log in, **then** a session is established and I land on the dashboard.
- **Given** invalid credentials, **when** I log in, **then** I see a generic failure message (no hint which field was wrong).
- **Given** repeated failed attempts, **when** a threshold is exceeded, **then** further attempts are rate-limited/throttled.
- **Given** a verified account, **when** the session is idle past timeout, **then** I am logged out and must re-authenticate.

**Status:** ✅ Implemented — generic failure message and idle timeout come from Laravel's default guard/session behavior; login throttling is a dedicated `RateLimiter::for('login', ...)` (5/min per email+IP) in `App\Providers\FortifyServiceProvider`.

---

## US-AUTH-4 — Reset forgotten password

**As an** account owner who forgot my password
**I want** to reset it via email
**So that** I can regain access.

### Acceptance Criteria
- **Given** I request a reset for any email, **when** I submit, **then** I always see the same neutral confirmation (no account enumeration).
- **Given** the email exists, **then** a time-limited reset link is sent.
- **Given** a valid reset token, **when** I set a new compliant password, **then** the password updates, existing sessions are invalidated, and the token is consumed.
- **Given** an expired/used token, **when** I open it, **then** I am prompted to request a new reset.

**Status:** ⚠️ Partially implemented — missing: other active sessions are not invalidated on password reset. `App\Actions\Fortify\ResetUserPassword` only force-fills the new password; there is no `Auth::logoutOtherDevices()` call or session purge for the user, so pre-existing sessions remain valid after a reset.

---

## US-AUTH-5 — Log out

**As an** account owner
**I want** to end my session
**So that** my account is secure on shared devices.

### Acceptance Criteria
- **Given** I am logged in, **when** I log out, **then** the active session is terminated and protected pages redirect to login.

**Status:** ✅ Implemented — standard Fortify logout route/guard behavior.

---

## US-AUTH-6 — Enroll a passkey

**As a** verified account owner
**I want** to register a passkey (WebAuthn) on my device
**So that** I can sign in without a password.

### Acceptance Criteria
- **Given** I am logged in and on the security settings page, **when** I start passkey enrollment, **then** the browser prompts to create a credential and the public key is stored against my account on success.
- **Given** my browser/device does not support WebAuthn, **when** I open the enrollment page, **then** I see a clear unsupported message and the action is disabled.
- **Given** I have one or more passkeys, **then** I can see them listed with a label and last-used timestamp and can revoke each one.

**Status:** ✅ Implemented — via Laravel Fortify passkeys + `@laravel/passkeys/react` (`resources/js/components/manage-passkeys.tsx`, `passkey-register.tsx`, `passkey-item.tsx`); unsupported browsers get a clear message from `usePasskeyRegister().isSupported`.

---

## US-AUTH-7 — Sign in with a passkey

**As an** account owner with at least one enrolled passkey
**I want** to sign in using my passkey
**So that** I get a phishing-resistant, password-free login.

### Acceptance Criteria
- **Given** I have an enrolled passkey, **when** I choose "Sign in with passkey" and complete the platform authenticator prompt, **then** a session is established and I land on the dashboard.
- **Given** the authentication ceremony fails or is cancelled, **when** I retry, **then** no session is created and no enumeration signal is leaked.
- **Given** a passkey was revoked, **when** I attempt to use it, **then** the sign-in fails with a generic error.

**Status:** ✅ Implemented — `resources/js/components/passkey-verify.tsx` on the login page uses `usePasskeyVerify()`, backed by Fortify's passkey login controller and the `passkeys` rate limiter in `FortifyServiceProvider`.
