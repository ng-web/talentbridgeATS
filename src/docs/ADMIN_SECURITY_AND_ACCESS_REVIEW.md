# Administrator Security and Access Review

> Technical operating procedure. It does not replace Kairox management, DPO, legal, personnel, or access-approval decisions.

## Authentication architecture

Kairox uses Laravel's stateful `web` guard, database-backed password reset broker, server-side sessions, Spatie permissions, and Laravel Fortify 1.38 for administrator TOTP and recovery-code primitives. Fortify is used for maintained two-factor generation, verification, encrypted-at-rest secrets, recovery-code rotation, and challenge handling. Existing Breeze controllers continue to own registration, ordinary password reset, profile changes, and session login views.

Fortify's passkey feature and duplicate authentication routes are not enabled. Applicant MFA is not required. Employer MFA is not required by this pass.

## Mandatory administrator MFA

An administrator who has authenticated with a password but has not confirmed MFA is redirected away from protected application features. Protected administrator routes require a per-session MFA assurance marker bound to the account's current `security_version`. MFA enrollment, an ordinary login event, a remembered identity, and a security-version stamp are not MFA assurance. A remembered administrator is logged out of the restored identity session and handed to the Fortify challenge; only a valid TOTP or unused recovery code establishes assurance.

Enrollment requires:

1. an authenticated administrator account;
2. the `admin.security.self` permission;
3. recent password confirmation;
4. TOTP secret generation by Fortify;
5. successful authenticator-code confirmation before MFA becomes active.

The QR response and recovery-code response use `Cache-Control: private, no-store`. Recovery codes are displayed immediately after confirmation or explicit regeneration. They are not emailed, flashed into the session, or placed in audit metadata. Administrators must store them in an approved password manager.

### Recovery

An administrator should first use an unused recovery code. If that is unavailable, a different authenticated, MFA-verified administrator with the explicit `admin.security.manage` permission may reset the affected administrator's MFA after recent password confirmation. Self-reset is prohibited.

An administrative reset:

- clears the Fortify-encrypted MFA secret and recovery codes;
- increments the account security version;
- rotates the remember token;
- removes matching stored session rows where the configured session connection permits;
- records a minimized `admin_mfa_reset` audit event; and
- leaves the account unable to enter protected areas until enrollment is completed again.

Completing MFA enrollment and regenerating recovery codes also rotates the remember token, invalidates other sessions, and clears current MFA assurance. The administrator must complete an actual login challenge before returning to protected routes.

Do not copy MFA secrets or recovery codes into support tickets, chat, logs, or audit notes.

## Account provisioning and setup links

New employer accounts and administrator-initiated credential recovery no longer use a shared or temporary plaintext password. The account is placed in an unusable-password/setup-required state, existing credentials and sessions are invalidated, and Laravel's password broker creates an opaque token. The emailed URL is also temporarily signed.

The setup link is:

- opaque and stored only as a hash by the password broker;
- signed for the intended URL parameters;
- limited by the configured password-broker expiry;
- single-use because a successful reset deletes the broker token; and
- invalidated if delivery fails.

Successful setup establishes the recipient's password, clears the legacy forced-change flag, rotates the remember token/security version, invalidates sessions, and records a safe audit event. Raw setup tokens and passwords must not be logged. Public password-reset requests return the same response whether or not an account exists.

Production setup delivery recursively resolves direct, failover, and round-robin mailers with cycle detection. Any reachable `log` or `array` transport fails closed before credentials or sessions change. Local/testing environments may use Mailpit or other development transports.

### Token URL protection layers

**Code control:** setup and password-reset responses use `Referrer-Policy: no-referrer`, Caddy preserves that route-specific policy, Nginx suppresses direct access logging for token-bearing setup/reset paths, and the rendered page replaces the browser-history URL with the token-free form endpoint after load. Laravel logs and audit metadata must not contain the raw token.

**Production manual control:** Cloudflare edge request logs, WAF/security events, Logpush datasets, analytics, Workers logs, and any upstream load-balancer logging must redact or exclude token-bearing URI paths and query strings. Repository controls cannot guarantee Cloudflare redaction. **Cloudflare edge logging is a separate production gate** and must be verified with a controlled request before sending a production setup link. Do not claim this control is configured until that verification is recorded.

The initial `app:create-admin` console command still accepts a password interactively through a hidden prompt; it does not email or display that password. The administrator must enroll MFA at first web sign-in.

## Session invalidation

Every user has a monotonically increasing `security_version`. A successful login stamps the current value into the server-side session. Authenticated requests are rejected if the stamp is absent or stale. This provides logical invalidation even when session storage uses a connection that cannot participate atomically in the primary database transaction.

Password reset, account setup/recovery, administrator MFA reset, forced password change, and account suspension increment the version and rotate the remember token. The service also deletes matching rows through `SESSION_CONNECTION` and `SESSION_TABLE` where available. A physical deletion failure is recorded only by exception class; logical invalidation remains authoritative. Session identifiers are never logged or audited.

Deployment of the new security stamp intentionally expires pre-existing unstamped sessions once. Users must sign in again.

## Privileged reauthentication

Laravel's `password.confirm` middleware protects permanent deletion, account suspension, account restoration, employer provisioning, account setup-link issuance, password-security administration, MFA enrollment/recovery changes, administrative MFA reset, access grant/revocation, and entitlement grant/revocation. Later privacy passes should reuse the same middleware for exports, legal-hold changes, retention execution, and incident-evidence access.

## Permission model

The following Spatie permissions are defined:

| Permission | Intended use | Default |
|---|---|---|
| `admin.security.self` | Own MFA enrollment and recovery-code rotation | Granted to the administrator role |
| `admin.security.manage` | Account setup/recovery, employer provisioning, other-admin MFA reset | Unassigned; Kairox approval required |
| `entitlements.manage` | Access and entitlement grant/revocation | Existing administrator-role permission; route enforcement is explicit |
| `admin.access-reviews.manage` | Future administrator access reviews | Unassigned |
| `privacy.requests.manage` | Future privacy-request administration | Unassigned |
| `privacy.exports.manage` | Future sensitive exports | Unassigned |
| `privacy.legal-holds.manage` | Future legal holds | Unassigned |
| `privacy.retention.manage` | Future retention configuration | Unassigned |
| `privacy.retention.execute` | Future destructive retention execution | Unassigned and separate from configuration |
| `privacy.incidents.manage` | Future incident operations | Unassigned |

Creating a permission does not authorize an administrator. Kairox must identify the smallest set of named administrators who receive each direct permission. Do not grant all future privacy permissions to the generic administrator role.

## Security audit events

`PrivacyAuditService` is the approved interface for privacy/security-sensitive audit writes. It adds a correlation UUID, outcome, reason code, event timestamp, actor, subject/resource identifiers, and event-specific allowlisted metadata. Unknown metadata keys and nested objects are rejected.

Current security events include administrator login success or known-admin credential failure, MFA enrollment/confirmation/challenge failure/recovery rotation/reset, secure setup-link delivery, password establishment/reset/change, account suspension/restore/deletion, session revocation context, privileged password confirmation, sensitive document access, Program correction, access grant/revocation, entitlement deletion, and private-document migration.

Audit metadata must never contain passwords, tokens, MFA secrets, recovery codes, session identifiers, document contents/paths, request bodies, provider payloads, contact details, API keys, or unrestricted exception messages.

Critical same-database security mutations and their audit records execute in one transaction. Audit failure rolls back MFA reset, account suspension/reactivation, permanent deletion, access changes, entitlement deletion, initial security-manager grant, and employer provisioning. Account-setup preparation—including credential rotation, session invalidation, broker token creation, and an issuance-intent audit—commits before external email delivery. Delivery outcome is recorded separately. If email succeeds but its outcome audit fails, the link remains valid and the UI reports success, preventing an unsafe duplicate-send retry; the audit persistence failure is logged only with minimized identifiers and exception class.

## Initial security-manager bootstrap

Do not run `RolesAndPermissionsSeeder` in production for this operation and do not grant `admin.security.manage` to the administrator role.

1. Obtain written approval for one active administrator and record its numeric user ID in the restricted change record; do not place an email address or MFA material in command output.
2. Deploy and run migrations so the permission exists.
3. Run `php artisan security:grant-manager APPROVED_ADMIN_ID` in the application container.
4. The command rejects non-numeric, missing, deleted, and non-admin users; grants the direct permission and `admin_permission_changed` audit row atomically; clears the Spatie permission cache; verifies the result; and is idempotent.
5. Confirm the command reports `Security-manager permission granted and audited.` or the idempotent already-granted result. Independently verify the audit row under the approved change procedure.
6. Have that administrator complete password authentication and MFA enrollment. Verify a different administrator without the direct permission receives `403` when attempting another administrator's MFA reset.

## Access-review expectations

At a Kairox-approved interval, an authorized reviewer should reconcile:

- active administrator accounts against current personnel and duties;
- direct sensitive permissions against written approval;
- whether every administrator has confirmed MFA;
- accounts in setup-required or soft-deleted state;
- recent MFA resets, credential recovery, permission changes, and session revocations; and
- whether a departed or suspended administrator's sessions were invalidated.

The future access-review workflow remains a later P1 pass. Until then, preserve the approval and review record in Kairox's approved restricted system and do not include passwords, factors, or recovery material.

## Operational checks

After deployment, confirm migrations completed, route caches rebuilt, all administrators are redirected to enrollment until confirmed, a designated security manager has explicit written approval and permission, the production mailer is non-logging, edge/origin logs do not capture setup-token URLs, setup email delivery works, expired/reused setup URLs fail, session invalidation works through the configured session connection, and audit rows contain only allowlisted metadata.
