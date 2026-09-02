# Privacy Request Operations Runbook

This runbook describes the Kairox controller workflow supported by the Likeslocale platform. It does not determine legal rights, deadlines, lawful basis, or request outcomes.

## Controller prerequisites

- **REQUIRES KAIROX APPROVAL:** named controller/security manager and backup approver.
- **REQUIRES LEGAL REVIEW:** identity-verification process, decision codes, response deadlines, retention exceptions, and regulator escalation.
- Privileged users must be named individuals, use MFA, and receive only explicit permissions.
- Shared mailboxes may receive notifications but should not act as the identity making controller decisions.

## Workflow

1. The applicant submits a category through `/privacy`. Submission does not delete, restrict, or disclose data.
2. A permitted Kairox administrator assigns the request while it is `submitted`, `under_review`, `identity_verification_required`, `verified`, or `decision_required`. Assignment is locked after any controller decision (`approved`, `partially_approved`, or `refused`) and after `fulfilled` or `closed`.
3. Kairox performs its approved identity process outside free-form request fields, then records only the configured method code and completion state.
4. The technical inventory reports categories, counts, default export eligibility, and unresolved retention flags without making a legal conclusion.
5. A user with `privacy.requests.decide`, MFA, a valid security session, and recent password confirmation records the Kairox decision.
6. For approved access scope, a separate authorized user approves a structured export scope. Generation and download remain separate privileged actions.
7. Kairox fulfills and closes the request only after its approved operational and legal checks.

Invalid state transitions and post-decision/terminal assignments are rejected without mutation, request event, or audit event. Request events are append-only and meaningful actions also enter the centralized privacy audit log. Never enter passports, document images, medical details, or applicant narratives into reason-code fields.

## Recovery

Correct the operational/configuration problem and retry the same allowed transition. Do not edit request history or database state manually. Escalate suspected unauthorized access under the incident-preservation procedure.
