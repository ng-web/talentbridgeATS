# Privacy Incident Evidence Preservation

Technical procedure for Likeslocale personnel. This procedure preserves evidence while Kairox assesses a suspected privacy or security incident.

Likeslocale does **not** determine whether an incident is legally reportable. Kairox's controller, DPO, and legal representative decide reportability and any regulator or data-subject notification.

## Immediate safeguards

1. Record who identified the concern, the exact Jamaica time and UTC time, the affected environment, and the observed behavior without copying applicant content into tickets or chat.
2. Restrict the response group to authorized Kairox and Likeslocale personnel. Use incident-specific access and record every action taken.
3. Do not delete or modify current public applicant files until Kairox has approved the migration/evidence decision. Do not open files merely to inventory them.
4. Do not purge, rotate early, edit, or truncate Cloudflare, Caddy, Nginx, Laravel, database, host, or authentication logs.
5. Do not rewrite Git history or remove GitHub Actions/deployment history. Capture the deployed commit, workflow run, operator, and deployment timestamps.
6. Preserve the current storage and database state before remediation. Inventory object counts, relative storage areas, sizes, ownership, permissions, and timestamps without displaying filenames, paths tied to a person, or contents in shared output.

## Evidence capture

- Record system clocks and timezone configuration for Cloudflare, the host, containers, database, and application.
- Export relevant logs to a restricted evidence location using read-only collection where possible. Record source, collection command/tool, start/end time, byte size, and SHA-256 hash.
- Preserve Cloudflare request/security events, Caddy and Nginx access/error logs, Laravel logs, database/audit records, host authentication logs, GitHub deployment history, and the applicable configuration snapshot.
- Record the public-storage and private-storage inventory by category and count. Do not inspect file contents unless Kairox explicitly authorizes it for the assessment.
- Keep original evidence immutable. Analyze working copies and maintain a simple chain-of-custody log.

## Remediation coordination

- Separate preservation from cleanup. A fix may be deployed while preserved evidence remains restricted and unchanged.
- Run `kairox:migrate-private-documents --dry-run` before any execution and retain its non-PII summary.
- Do not purge historical files, logs, backups, or payment payloads as part of this sprint.
- Escalate technical facts promptly to Kairox. Avoid conclusions about legal breach thresholds, affected-person notification, or regulator notification.

## Action record template

Record: timestamp (Jamaica and UTC), operator, approved instruction, system, read/write action, result, evidence identifier/hash, and reviewer.
