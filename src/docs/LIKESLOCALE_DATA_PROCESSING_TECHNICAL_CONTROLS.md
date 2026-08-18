# Likeslocale Data Processing Technical Controls

> **Technical controls reference — requires contractual/legal approval.**

This is an engineering control description for a future processor agreement. It is not a signed DPA, does not allocate legal controller roles, and does not replace Kairox legal, DPO, retention, lawful-basis, or transfer decisions.

## Operating commitments

- Process Kairox data only on documented Kairox instructions, except where applicable law requires otherwise and legal advice determines the response.
- Limit production and applicant-document access to authorized personnel with a work-related need; bind personnel to confidentiality obligations.
- Store applicant uploads on Laravel's non-served `private` disk. Use randomized server-side paths and authenticated, authorized streamed delivery rather than public URLs.
- Apply least privilege by role. Applicants access their own records; employers access only explicitly allowed documents for non-withdrawn applications belonging to their employer; admins require authenticated admin authorization. Employer access defaults to deny.
- Keep passports, driver's licences, police records, and medical records unavailable to employers by default. High-risk downloads generate minimized audit events.
- Do not expose an applicant's date of birth or the existence of identity, police, or medical uploads to employers. Direct email and phone remain temporarily available because the current recruitment workflow has no portal messaging feature; Kairox must confirm and document the business/legal need or require their removal.
- Protect traffic with HTTPS and secure session settings. Production must use `APP_ENV=production`, `APP_DEBUG=false`, the production HTTPS `APP_URL`, secure/HTTP-only cookies, and trusted-proxy configuration appropriate to the Caddy/Cloudflare path.
- Minimize logs and new payment-provider metadata. Do not log document contents/paths, callback payloads, customer contact details, hashes, secrets, credentials, or raw provider responses.
- Keep new database backups owner-readable only (`0600`) in a restricted directory (`0700`). Kairox must approve retention duration. Encryption needs an approved key-management design; restore capability must be tested on synthetic or authorized data.
- Maintain supported dependencies, apply security updates under change control, review vulnerability findings, and regression-test authorization and file lifecycle behavior.
- Escalate suspected security/privacy incidents to Kairox without making the legal reportability decision. Preserve evidence according to `PRIVACY_INCIDENT_PRESERVATION.md`.
- Support deletion, return, restriction, export, and restoration when instructed by Kairox and technically/legal permissible. Automated regulatory expiry is outside Sprint 1 pending an approved retention schedule.
- Maintain a current vendor/subprocessor technical inventory. Do not send applicant content to a vendor unless the approved architecture and Kairox instructions require it.
- Use reviewed Git changes, manual GitHub Actions production dispatch, database backup, migration preflight, post-deploy verification, and documented rollback/recovery steps.

## Restoration and testing

- Test private upload location, direct-public denial, role/ownership authorization, high-risk employer denial, replacement/removal deletion, migration idempotency, minimized logs/email, and neutral upload wording.
- Inventory backups without printing secrets. At an approved interval, restore a selected backup into an isolated non-production environment, validate schema/count integrity, record duration/result, then securely dispose of the test copy under Kairox instruction.
- Record control exceptions, failed cleanup, failed migration references, and restoration failures without applicant PII.

## Deferred contractual and governance inputs

Kairox must approve the DPA, documented instructions, retention periods, subprocessors, breach SLA, deletion/return terms, audit rights, international-transfer mechanism, and controller/employer allocations.

## Engineering follow-up beyond Sprint 1

- P1: replace emailed plaintext temporary passwords with Laravel's signed, one-time password-setup/reset flow.
- P1: design a real reviewer decision model before any UI may say a document is verified, valid, approved, passed, or cleared.
- P1: inventory and govern historical raw WiPay payloads without deleting them until Kairox approves retention and evidence handling.
- P1: add an approved backup inventory/rotation schedule and tested encryption key-management design.
- P2: evaluate a report-only CSP against actual asset sources before enforcing a compatible policy.
- P2: implement retention/expiry automation only after Kairox/DPO approves data categories, minimum/maximum periods, holds, and deletion rules.
