# Admin User Operations

## Applicant Current Program

`job_seekers.program_id` is the authoritative source for the Kairox Program an applicant is currently pursuing.

It is intentionally separate from:

- `jobs.program_id`, which classifies an opportunity;
- `payments.plan_id`, which identifies a purchased Payment Plan; and
- entitlement types, which control platform access.

Existing applicants are not automatically backfilled because none of those records proves an applicant's current Program. A missing relationship is displayed as `Not selected` and can be corrected by an administrator.

Applicants select their initial Program during registration. A legacy applicant without a Program can make one initial selection from their profile; subsequent corrections are controlled by administrators and audited.

If Kairox later needs multiple, simultaneous, completed, or historical Programs, replace the singular current-Program model with a dedicated `program_enrollments` design containing lifecycle and enrollment dates. That history is deliberately outside the present scope.

## User Retention

The normal removal action soft-deletes only the User and keeps operational history available for restore. Permanent deletion remains blocked for financial, entitlement, application, audit, employer-job, payment-assistance/contact, and applicant-document history.
