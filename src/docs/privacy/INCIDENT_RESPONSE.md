# Incident response operations

## Boundary

A technical incident is not a legal personal-data-breach determination. Kairox is the controller and supplies controller acknowledgement, breach assessment, and notification decisions. Likeslocale may support technical investigation, containment, recovery, evidence preservation, and processor-to-controller escalation. The software does not determine regulatory notification obligations.

No statutory deadline, regulator contact, affected-person notification, automated incident creation, or destructive containment is implemented. Deploying the code does not grant authority; an approved administrator must be explicitly bootstrapped.

## Lifecycle and evidence

The service-controlled lifecycle is `open → triage → investigating → contained → recovering → resolved → closed`. A separately authorized operator may reopen a closed incident to `investigating` using a controlled reason. Invalid, duplicate, and stale operations fail closed or resolve idempotently under row locks.

Technical severity (`informational` through `critical`) prioritizes operations only and is never legal materiality. The encrypted, 500-character technical summary must contain no PII, secrets, raw payloads, or evidence dumps. Potential scope is recorded as immutable versions using Pass 3 data categories, controlled system codes, and an optional aggregate subject count.

The incident timeline is append-only operational history. Privacy audit records are separate semantic evidence; privileged changes write both in the same transaction. Controller decisions and scope changes append immutable versions rather than overwriting prior evidence.

Lifecycle progress accepts only controlled technical evidence combinations. Triage and investigation may be requested, performed, or verified; containment and recovery require performed or verified evidence; resolution requires verified recovery. Failed, incomplete, unrelated, or unsupported evidence cannot advance completion timestamps or lifecycle state.

Incident creation keys are globally unique operation identifiers but are safe to retry only by the original currently authorized creator with the same normalized creation payload. Authority and security-version checks occur before lookup. Same-key concurrent retries converge on one incident and one creation event/audit; a changed payload or different creator is rejected without returning the existing incident.

## Operational runbook

1. Explicitly grant a Kairox-approved administrator the direct bundle with `php artisan privacy:grant-incident-manager USER_ID`.
2. Record the suspected incident with minimized technical information and potential scope.
3. Assign a currently authorized owner and progress only through controlled states.
4. Record processor escalation when required. Email delivery is not implemented and could never constitute acknowledgement.
5. A controller-authorized operator explicitly records acknowledgement and versioned decision evidence.
6. If evidence preservation is authorized, issue a hold through the incident view. This calls the existing Pass 3 `LegalHoldService`; closure and reopening never release it.
7. Close only after resolution, valid ownership, decision evidence, and acknowledgement of any escalation.
8. Use `php artisan privacy:reconcile-incidents USER_ID` for a dry run. Add `--execute` only to append a review marker for deterministic owner/timestamp inconsistencies. It cannot close, decide, notify, or release holds.

All mutations require authenticated admin routing, an active security-version session, completed password-change requirements, administrator MFA, direct permission, and password confirmation. Applicants, employers, generic administrators, and role-only grants have no incident access.
