# Applicant Export Security Runbook

Exports are controller-authorized, private, short-lived artifacts. They are not public links and are not automatically sent to applicants.

## Required controls

- Request state is `approved` or `partially_approved` and identity verification is recorded as completed.
- Authorization, generation, and download require distinct explicit permissions, administrator MFA, a valid security session, and recent password confirmation.
- Scope is allowlisted. High-risk documents, application files, passwords, remember tokens, MFA material, sessions, API keys, raw gateway payloads, and internal audit metadata are excluded in Pass 2.
- Files use the private disk, the canonical `privacy-exports/{export-uuid}/{export-uuid}.zip` path, `0700` root/export directories, `0600` file mode, atomic finalization, and SHA-256 integrity hash. Noncanonical and symlinked paths are rejected.
- Generation uses an opaque database claim token and attempt counter. Claim tokens are internal concurrency controls and must never appear in UI, logs, audit metadata, or export content.
- Build data is isolated under `privacy-exports/{export-uuid}/staging/{generation-token}/`; both staging levels are verified `0700` and every temporary file is verified `0600`.
- Download responses use `no-store`; no public or signed persistent URL exists.

## Operational sequence

1. Kairox approves the exact structured scope.
2. Authorize the export in the request detail.
3. Queue generation only after queue workers and private storage are healthy.
4. Verify status, hash, expiry, and expected scope before controlled download.
5. Record delivery using the Kairox-approved process. **REQUIRES KAIROX APPROVAL.**
6. Use `php artisan privacy:exports:reconcile --dry-run` (or the default with no option) for a read-only aggregate inspection after worker or storage incidents. Use `--execute` only in an approved change window; execution performs deterministic cleanup/state reconciliation and never regenerates or reauthorizes a disclosure.
7. Run `php artisan privacy:exports:purge-expired` manually under an approved change window. Automated scheduling is deliberately not enabled.

## Generation lease and crash recovery

`authorized → generating` is claimed with a row lock, a new UUID token, `generation_started_at`, and an incremented attempt. The database claim is authoritative; queue uniqueness is only an additional duplicate-delivery control. A fresh `generating` lease cannot be stolen. A lease is stale after `PRIVACY_EXPORT_GENERATION_STALE_MINUTES` (default 10, controller-owned configuration). A later worker may reclaim only after row-locked disclosure, subject, scope, and current-authorizer validation; reclaim assigns a new token, fencing the old worker from publication.

Reclaim uses a deterministic rebuild policy. It deletes only the old token's canonical staging subtree and any unpublished canonical ZIP, then builds from current minimized database selections. It never adopts a ZIP left by a crashed worker. A crash before or during ZIP creation therefore leaves claim-scoped artifacts that reclaim can identify; a crash after rename but before the `READY` commit leaves an untrusted canonical ZIP that reclaim removes. Stale recovery atomically moves such a ZIP into the stale claim's private staging subtree while the database row still proves that claim owns recovery, then performs recursive cleanup after releasing the row lock. A newer lifecycle operation therefore fences out the old recovery: canonical pathname reuse never authorizes stale cleanup to delete an artifact published by a later claim. `READY` requires the current token, renewed disclosure checks, verified final `0600` mode, and a fresh SHA-256.

Canonical logical validation is independent of filesystem existence: the configured export root, export UUID, and internally generated filename must match exactly even when the UUID directory is absent. Physical containment and symlink checks are then applied to every component that currently exists. A missing canonical UUID directory is a valid recovery condition, not a path escape; an existing traversal, absolute/cross-export path, source-document path, malformed UUID, symlink, or physically escaped component remains rejected.

Ordinary exceptions clean the entire current claim staging subtree and unpublished ZIP and transition the owned attempt `generating → failed`. Automatic queue delivery and direct `generate()` calls cannot claim `failed`. A password-confirmed administrator holding `privacy.exports.generate` must use the explicit retry operation, which revalidates the request and original authorization, audits the action, and resets `failed → authorized` before queueing. Transport recovery of an active/stale lease is distinct from this privileged business retry.

The original authorizer must remain non-deleted, retain the `admin` role, and retain `privacy.exports.authorize` immediately before publication and at download. If not, publication/download is denied until a current controller administrator with that permission performs the password-confirmed reauthorization action. Reauthorization never silently changes scope.

## Expiration, cleanup, and purge

The successful lifecycle is exactly `authorized → generating → ready → expired → purging → purged`; controlled build failure is `generating → failed`. A past-due `ready` row is first transitioned and audited as `expired`. Only `expired` may claim `purging`; `ready`, `failed`, `authorized`, and `generating` cannot enter normal purge. Physical deletion occurs only after the committed `purging` claim, and a missing artifact or finalization retry is idempotent. Failed-attempt staging is technical cleanup and does not relabel a failed disclosure as purged.

`privacy:exports:reconcile` reports aggregate counts only—no names, emails, paths, UUIDs, or tokens. Default dry-run does not mutate. It distinguishes stale generation with a canonical ZIP, a fresh generation ZIP pending publication, orphan canonical ZIPs in `AUTHORIZED`, `FAILED`, or `PURGED`, missing `READY` artifacts, expired artifacts awaiting purge, committed `PURGING`, failed-generation debris, and other orphan staging.

With `--execute`, canonical ZIPs may be removed as unpublished orphans only after a row lock, strict canonical validation, and confirmation that the current state is `AUTHORIZED`, `FAILED`, or `PURGED`. A fresh `GENERATING` canonical ZIP is report-only because it may be between rename and `READY`; a stale claim uses the existing stale-generation recovery. `READY` canonical artifacts are never orphan-cleaned, and a missing `READY` artifact remains a reported integrity problem without automatic recreation or state promotion. `EXPIRED` and `PURGING` always use the normal two-phase purge lifecycle, including when the entire UUID directory is already absent. FAILED cleanup with an absent directory is an idempotent no-op. The command never regenerates, reauthorizes, or schedules itself.

Reconciliation iterates database export records and does not broadly scan or delete UUID-like directories with no matching database record. Unknown or unmapped directories require restricted operational investigation; no-record or unknown-file cleanup is outside this command.

Request decision, identity verification, subject binding, current authorizer, approved scope, and export state are rechecked immediately before publication and again before every download. The stored scope digest and SHA-256 must match the current record and exact artifact.

Backup retention for exports is **REQUIRES KAIROX APPROVAL** and **REQUIRES LEGAL REVIEW**; private export paths must not be copied to public or analytics storage, and restoration must not revive expired disclosure authority. Live purge does not claim to remove historical backup copies.
