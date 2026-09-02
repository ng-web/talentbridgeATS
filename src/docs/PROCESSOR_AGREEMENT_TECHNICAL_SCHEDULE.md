# Processor Agreement Technical Schedule — Draft Reference

This document is not an executed data processing agreement. Contractual and legal fields are **REQUIRES LEGAL REVIEW**.

## Processing outline

- Subject matter and duration: operation and support of the Kairox applicant/employer administration platform — **REQUIRES LEGAL REVIEW**.
- Data subjects: applicants/job seekers, employer contacts, administrators, and support correspondents — **REQUIRES LEGAL REVIEW**.
- Data categories: account/contact data, applicant profile and application data, private uploaded documents, payment/entitlement records, notifications, policy evidence, privacy-request records, and security/audit references.
- Processing operations: hosting, storage, retrieval, controlled disclosure, support, backup/recovery, security monitoring, export, deletion/return under approved instruction, and technical DSAR assistance.

## Technical measures

- Named accounts, least privilege, default-deny controller permissions, MFA for administrators, recent password confirmation, session-version invalidation, and audited bootstrap.
- Private applicant-document and export storage; no public export URL; restrictive file/directory permissions; integrity hashing; short-lived export artifacts and manual purge tooling.
- IDOR-resistant authorization, withdrawn-employer restrictions, controlled state machines, append-only DSAR history, centralized allowlisted audit metadata, and minimized logging/email.
- Transaction-safe critical mutations and private-document lifecycle/migration controls.
- Backup safeguards and incident preservation/escalation documented in repository runbooks.
- Technical subprocessor inventory maintained in `TECHNICAL_SUBPROCESSOR_REGISTER.md`; contractual approval/notice terms are **REQUIRES LEGAL REVIEW**.
- DSAR assistance includes policy evidence, request workflow, technical inventory, private structured export, integrity/expiry, and purge capability.
- Deletion, return, termination assistance, retention exceptions, backup expiry, and certification language are **REQUIRES KAIROX APPROVAL** and **REQUIRES LEGAL REVIEW**.

Likeslocale implements and operates agreed technical controls and executes documented controller instructions. Kairox determines lawful basis, legal outcomes, retention decisions, disclosure authority, policy content, and regulatory action.
