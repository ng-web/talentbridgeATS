# Retention and disposition

This feature is processor-side technical capability, not legal certification. It is inactive by default: the registry is empty, no destructive command is scheduled, and no approved governing rule means no disposition.

## Responsibility boundary

Likeslocale implements technical rule versioning, deny-by-default authorization, deterministic eligibility, hold enforcement, audit evidence, safe execution, and reconciliation. Kairox decides and approves retention periods, legal/regulatory meaning, categories and methods, backup policy, government or regulator requirements, and whether disposition should occur.

The registry distinguishes the technical trigger from Kairox's approved retention decision. Approved versions are immutable. A future-effective replacement retires its predecessor at the replacement's effective time, so it does not prematurely remove the current governing rule. No real retention periods are seeded.

MySQL runs at `REPEATABLE READ`, so destructive authority never relies on an ordinary transaction snapshot. A stable per-category registry row serializes rule approval, retirement, replacement and authoritative plan/execution reads. Historical version advancement is checked against every prior version, including retired rules.

Repository-derived categories are defined in `RetentionDataCategories`. Only `applicant_document` is executable, using `record_created` and `detach_and_delete_file`. Other actual categories are recognized but fail closed as unsupported. Payments, entitlements, audit/security records, policy acknowledgements, sensitive-processing evidence, privacy requests/exports, accounts, profiles, applications, application files, notifications, and assistance requests are not disposed by this implementation.

Grant the capability only after named approval:

```sh
php artisan privacy:grant-retention-manager USER_ID
```

This audited command requires an existing active administrator and grants direct narrow permissions. Pass 3 authority is deliberately direct-user-only: HTTP middleware, UI visibility, bootstrap, services, and revocation checks all use direct permission semantics. A role-derived Pass 3 permission grants no Pass 3 access. Generic administrators and controller managers do not receive the bundle.

Dry-run eligibility is the default:

```sh
php artisan privacy:retention-plan applicant_document --user_id=USER_ID
```

`--execute` on this command only persists a reviewable plan; it never disposes subject data. Authorization and destructive execution remain separate password-confirmed UI actions protected by the existing security-session and administrator-MFA middleware. Destructive execution is HTTP-only and binds the request's initiating security-session/MFA version; no CLI or queued entry point performs logical disposition.

No scheduler entry performs eligibility scanning or disposition.

Each plan binds the governing rule ID, version and canonical hash. Each applicant-document artifact has a UUID revision that changes on normal replacement, and plans also bind the row, revision and a SHA-256 path fingerprint; raw paths are not displayed or audited. A rule or artifact replacement therefore makes an older plan stale even when the logical document row ID and original creation timestamp remain unchanged.
