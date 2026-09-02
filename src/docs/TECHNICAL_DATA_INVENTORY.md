# Technical Data Inventory

This repository-evidence inventory supports Kairox controller review. It is not a legal classification, retention decision, vendor certification, or representation that a contract or transfer mechanism exists.

Status vocabulary:

- **VERIFIED** — directly verified by the implemented repository control.
- **REPOSITORY EVIDENCE ONLY** — code or configuration identifies the handling, but production operation remains for Kairox to confirm.
- **REQUIRES KAIROX CONFIRMATION** — not established by the repository.

| Category | Primary association/location | Export posture | Retention/deletion/restore posture | Evidence status |
|---|---|---|---|---|
| Account and authentication | `users`, authentication/session stores | Minimized account fields eligible; password, MFA, recovery, token, and session material excluded | Account deletion is separately controlled; restored databases may restore historical records | VERIFIED for export exclusion; production retention requires Kairox confirmation |
| Job-seeker profile | `job_seekers`, `programs` | Explicit profile allowlist eligible; private paths excluded | Application lifecycle controlled; no automated Pass 2 erasure | VERIFIED |
| Applications | `applications`, `jobs` | Explicit application allowlist eligible; internal notes/files excluded | No automated Pass 2 retention execution | VERIFIED |
| Private applicant files | private disk: job-seeker and application-document subtrees | Excluded from structured Pass 2 exports | Transaction-aware lifecycle exists; restored storage or backups may restore deleted bytes and must remain private | VERIFIED for private application handling; backup behavior requires Kairox confirmation |
| Payments | `payments` | Minimized payment fields eligible; raw provider payload and external secrets are not selected | Payment retention is outside Pass 2; restored databases may contain historical records | VERIFIED for export minimization; retention requires Kairox confirmation |
| Entitlements | `entitlements` | Explicit operational fields eligible | Operational lifecycle; no automated Pass 2 deletion | VERIFIED |
| Policy acknowledgements | `policy_documents`, `policy_acknowledgements` | Type, version, canonical reference hash, acknowledgement type/time eligible | Evidence rows are intentionally restricted from casual deletion; restoration must preserve referential integrity | VERIFIED |
| Sensitive-processing evidence | `sensitive_processing_evidence` | Manual review; not included in default structured export | Withdrawal state is preserved; no automated deletion | VERIFIED |
| Privacy requests and history | `privacy_requests`, `privacy_request_events` | Minimized request fields eligible; internal actor, reason, and safe metadata excluded | Terminal workflow history remains as accountability evidence; retention decision is controller-owned | VERIFIED |
| Generated DSAR exports | `data_exports`, dedicated private `privacy-exports/{uuid}` subtree | Never recursively exported | Short-lived expiry plus two-phase purge; retryable `purging` state; restored artifacts must not become downloadable unless current DB authority, state, scope digest, expiry, and SHA-256 checks pass | VERIFIED for application controls; production backup restoration requires Kairox confirmation |
| Audit logs | `audit_logs` | Internal review only; raw audit metadata excluded | Accountability records are not automatically erased in Pass 2; restore must preserve access controls | VERIFIED |
| Email processing | configured application mail transport/provider | Not included by default | Provider delivery logs, message retention, and deletion behavior are outside repository control | REPOSITORY EVIDENCE ONLY |
| Notifications | application notification storage/provider | Manual review | Retention and provider-side copies require Kairox confirmation | REPOSITORY EVIDENCE ONLY |
| Support/payment assistance | assistance/contact records | Manual review; not included by default | Technical correlation may use email; retention is not established here | REPOSITORY EVIDENCE ONLY |
| Database backups | infrastructure-managed database backups | Not directly exportable by the application | May retain rows after operational deletion; restore procedures must reapply authorization, expiry, and deletion state before service resumes | REQUIRES KAIROX CONFIRMATION |
| Private-file backups/snapshots | infrastructure-managed storage backups | Not directly accessible through public routes | May retain deleted applicant files or exports; restore must preserve private modes and must not resurrect expired/purged disclosure authority | REQUIRES KAIROX CONFIRMATION |

## Vendor and subprocessor unknowns

For every production hosting, database, object/file storage, email, monitoring, backup, and support vendor, Kairox must record the following outside this repository unless independently verified:

| Item | Current technical status |
|---|---|
| Processing region | REQUIRES KAIROX CONFIRMATION |
| Executed DPA status | REQUIRES KAIROX CONFIRMATION |
| International transfer mechanism | REQUIRES KAIROX CONFIRMATION |
| Vendor-side retention and deletion | REQUIRES KAIROX CONFIRMATION |
| Backup region, retention period, encryption, and restore controls | REQUIRES KAIROX CONFIRMATION |

Retention/restriction markers remain `not_assessed`, `manual_review_required`, or another technical state until Kairox approves operational rules. Automated erasure, legal holds, and scheduled future policy activation are deliberately outside Pass 2.

The canonical policy reference hash proves the exact registered metadata tuple used by this application. It does not prove the bytes at a remote URL. Kairox must use an immutable approved reference or separately retain an approved content digest/snapshot when byte-level evidence is required.
