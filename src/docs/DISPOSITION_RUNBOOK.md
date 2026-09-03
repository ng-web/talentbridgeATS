# Controlled disposition runbook

Technical capability does not equal legal certification. Obtain Kairox's documented decisions before configuring a rule or hold and before authorization.

1. Confirm the named administrator has the audited retention-manager bundle and an MFA-assured, current security session.
2. Register a draft rule with the controller-approved category, technical trigger, duration and supported method. No statutory periods are supplied by the application.
3. Approve the immutable version. For future-effective replacements, verify both current and future governing windows.
4. Review active subject/category/resource holds.
5. Run `php artisan privacy:retention-plan applicant_document --user_id=USER_ID` and review aggregate output. It is dry-run by default and contains no names, email addresses, filenames, paths, document content, payment payloads, or other subject data.
6. Add `--execute` only to persist a non-destructive review plan. Review it in the admin UI.
7. A separately permitted administrator explicitly authorizes the plan. Authorization expires and is invalidated if the authorizer is deleted, loses role/permission, or changes security version.
8. A separately permitted HTTP execution action captures the authenticated session's immutable executor ID and `security_version`, claims the plan, and revalidates rule/version, artifact revision, path fingerprint, trigger, eligibility timestamp, snapshot hash, hold state, category/method, plan window, authorizer and executor. The locked executor version must still equal the initiating session version. Any change prevents destruction and produces a review-required outcome. CLI disposition execution is not supported.

Destructive locking uses one canonical prefix order: category registry fence → subject fence → resource → authorizer/executor user rows in ascending ID order → plan/item. Rule operations stop after the category prefix, hold operations use the subject prefix, and authority invalidation uses the user row. Current/locking reads under these fences make the protocol independent of older MySQL `REPEATABLE READ` snapshots. Permission/role revocation and security-version rotation advance the locked user authority version; a prior authorization cannot survive that change.

## MySQL concurrency regression

`PrivacyPass3MySqlConcurrencyTest` is an opt-in destructive test for an isolated MySQL schema. It configures two independent Laravel connections to that schema and verifies `REPEATABLE-READ` before running. A test-bound synchronization barrier pauses the real `DispositionService` at named production lock boundaries. Connection B either commits its hold/rule/authority change first and the real execution preserves the document, or encounters a one-second InnoDB lock wait while execution owns the applicable fence. Execution then commits through the real service and B retries successfully. The no-op production barrier has no configuration or synchronization behavior.

Set `MYSQL_CONCURRENCY_DATABASE` and the optional `MYSQL_CONCURRENCY_HOST`, `MYSQL_CONCURRENCY_PORT`, `MYSQL_CONCURRENCY_USERNAME`, and `MYSQL_CONCURRENCY_PASSWORD` variables to a disposable schema, then run:

```sh
php artisan test --group=mysql-concurrency
```

The test recreates that schema. Never point it at a shared, staging, or production database.

Applicant-document disposition deletes the database reference within the transaction. The same transaction stores the exact artifact revision/fingerprint and an encrypted, hidden cleanup locator on the plan item; raw paths remain absent from audit, UI and CLI output. Existing observers capture subject/category/resource context before logical deletion and queue physical deletion only after commit. Context-free cleanup fails closed. The retry-safe cleanup job re-locks the subject, current-reads subject/category/resource holds, validates resource binding and cleanup identity, and rechecks all profile, document, application snapshot and application-file references before deleting. A hold issued after logical deletion but before cleanup still blocks physical deletion; a rollback retains the file; a shared or newly authoritative reference prevents deletion; cleanup failure retains debris for retry. Exhausted cleanup jobs retain only a path fingerprint in application logs.

The legacy private-document migration may copy and verify a private destination while an applicable hold exists, but it retains the authoritative legacy reference and old verified location. Once the hold is released, a later run may update the reference and queue context-rich removal of the old location.

## Recovery and reconciliation

Hard failure can leave a retained file or a stale execution claim. This is safer than lost authoritative data. Run:

```sh
php artisan privacy:reconcile-disposition --user_id=USER_ID
```

The command is aggregate-only and dry-run by default. `--execute` current-locks the category rule and administrator authority before changing provably stale execution claims to `review_required`; it may redispatch only disposed items whose exact plan rule remains governing and whose encrypted locator, UUID revision and fingerprint agree. It does not make new legal/controller decisions or dispose authoritative subject data. The cleanup job still performs current hold and shared-reference checks. Ambiguous or stale identity remains untouched and report-only.

## Backups and restoration

Deleting or anonymizing live application data does not necessarily remove it from historical backups. This pass does not manipulate backups. Kairox owns backup-retention and hold decisions; Likeslocale owns implementation of the approved operational controls. After a restore, reconcile live state, restore holds and approved rule evidence, and reapply only dispositions that can be proven authorized and technically valid. Never assume a prior live deletion automatically applies to restored backup data.
