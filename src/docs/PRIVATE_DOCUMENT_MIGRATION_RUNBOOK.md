# Private Applicant Document Migration Runbook

This runbook is a production plan only. Do not execute production migration through deployment automation. Kairox must authorize the maintenance window/evidence approach, and an operator must review dry-run counts before `--execute`.

## Command behavior

```bash
php artisan kairox:migrate-private-documents --dry-run
php artisan kairox:migrate-private-documents --dry-run --user=123 --limit=50
php artisan kairox:migrate-private-documents --execute
```

The command reports category/status counts only—never applicant names, contact details, original filenames, paths, contents, or content hashes. Destinations are deterministic per database reference, allowing safe retries. It compares source and destination byte size and streamed SHA-256 hashes before changing a reference. A pre-existing destination with different content, including same-size corrupt content, is removed and recopied while the public source remains intact.

The database reference update and minimized audit event commit in one transaction. The public source is never deleted inside that transaction. Only after commit does the command enqueue the same idempotent, reference-aware cleanup job used by runtime document lifecycle operations. A rollback leaves the database reference and public source intact and removes an unreferenced private attempt. Queue delay or cleanup failure leaves both valid copies temporarily; it never removes the committed private copy. The queued job retries and rechecks every supported reference table before deleting the old source. If a record still points to a public source, rerunning the command verifies/reuses the deterministic private copy and retries the transaction. A private copy whose public source is unexpectedly missing is reported for investigation rather than trusted without a source hash.

`missing_source`, `missing_private`, or `failed` returns a non-zero exit. Preserve evidence and investigate counts without opening real files unless Kairox authorizes it.

## Production sequence

1. Preserve logs, Git/deployment history, timestamps, and storage inventory under `PRIVACY_INCIDENT_PRESERVATION.md`. Decide with Kairox whether the migration must wait for evidence capture.
2. Confirm the approved commit and record current production commit/config state. Verify `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://portal.kairoxexchange.com`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and the expected trusted proxy path without printing secrets.
3. Create and verify a restricted database backup. Workflow deployments serialize through the production concurrency group and create a random-suffixed temporary file, then use atomic no-clobber hard-link finalization. Record backup filename, size, permissions, time, and restore owner. Do not delete historical backups.
4. Consider a brief maintenance/read-only window between code deployment and file execution so legacy public URLs do not remain exposed while records change. If uninterrupted writes are allowed, run a second dry-run before execution.
5. Manually deploy code through the approved GitHub Actions `workflow_dispatch`; run database migrations if present; clear and rebuild Laravel caches.
6. Run `php artisan kairox:migrate-private-documents --dry-run`. Record category totals only. Stop on unexpected categories, missing files, orphan private copies, or count drift.
7. Run `--execute` only after review. Then rerun `--dry-run`; expected legacy `migratable` count is zero and all referenced private files exist.
8. Confirm sampled old synthetic/approved `/storage/...` applicant URLs return 404/denied. Do not paste real applicant URLs into shared systems. Confirm private paths are not under `public/storage` and Caddy/Nginx expose no `storage/app/private` alias.
9. Run authenticated smoke tests: applicant own/other, related/unrelated employer, employer high-risk denial, admin access, high-risk audit event, replacement, and removal.
10. Verify application, queue, HTTP, and authorization logs contain no file path/content, callback payload, hash, secret, or unnecessary customer contact data.
11. Exit maintenance/read-only mode only after smoke tests. Monitor safe identifiers and error counts.

## Rollback and recovery

- Code rollback does not make private files public. Prefer correcting the secure download/migration code rather than restoring direct public links.
- If execution stops, do not delete either copy manually. When the public reference/source still exists, `migratable_private_copy_present` is SHA-256 verified and safe to retry. An `orphan_private_copy` requires investigation because the source is unavailable for comparison.
- If post-commit cleanup is delayed or fails, the database already points to the verified private object and the public duplicate remains. Allow the queued cleanup retry to run; it will delete the public object only after confirming that no supported database reference uses it.
- If a migrated reference is wrong, restore the database from the restricted backup only under an approved incident/change plan. Preserve private objects until references and counts are reconciled; never move the only copy back to a public disk.
- A public source shared by several legacy records remains until the final reference migrates. Do not treat the retained shared source as command failure.
- Rollback does not include regulator notification, legal erasure, log purge, or automatic retention cleanup.

## Verification commands

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan about --only=environment
docker compose -f docker-compose.prod.yml exec -T app php artisan config:show app
docker compose -f docker-compose.prod.yml exec -T app php artisan config:show session
docker compose -f docker-compose.prod.yml exec -T app stat -c '%a %n' storage/app/private
curl -sS -D - -o /dev/null https://portal.kairoxexchange.com/
curl -sS -D - -o /dev/null https://portal.kairoxexchange.com/storage/synthetic-retired-applicant-file.pdf
```

The private storage command must report mode `700`; sampled applicant subdirectories must be `700` and files `600`. Expected edge headers include HSTS, `nosniff`, strict-origin referrer policy, disabled camera/microphone/geolocation, and frame denial. `Server`/`X-Powered-By` version details should be absent. Confirm HTTPS and `Secure`, `HttpOnly`, `SameSite=Lax` session cookies.

## Backup controls still requiring decisions

New workflow backups use a `0700` directory and `0600` files. Maintain an access-controlled inventory and test restoration. Kairox/DPO must approve retention duration. Encryption is recommended only after an approved key-management and recovery design; do not store an encryption key beside the backup.
