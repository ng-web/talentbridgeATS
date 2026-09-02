# Processor / Controller Responsibility Matrix

This is a technical operating reference, not a legal certification or guarantee of compliance.

| Area | Likeslocale technical responsibilities | Kairox controller responsibilities |
|---|---|---|
| Policy documents | Version registry, immutable evidence, hashes, access controls | Approve Privacy Notice/Terms wording and effective versions |
| Sensitive processing | Controlled evidence fields and configurable presentation/enforcement | Determine lawful basis/condition, purpose, wording, and whether collection may continue |
| Privacy requests | Secure intake, UUID isolation, state controls, history, auditability | Determine validity, deadlines, outcome, exceptions, and communications |
| Identity verification | Record a configured method code and completion state without identity-document contents | Approve and perform the verification process |
| Data inventory | Technical category/count inventory and export eligibility defaults | Interpret scope, retention obligations, and required searches |
| Exports | Private generation, isolation, integrity, expiry, access controls, purge tooling | Authorize disclosure, recipients, scope, timing, and delivery method |
| Operations | Host/operate agreed controls, execute approved technical instructions, escalate incidents | Direct processing, approve changes, own DPO/OIC/regulatory decisions |
| Access | MFA, sessions, granular deny-by-default permissions, technical audit trail | Name controller personnel, approve grants, review access and shared identities |

Likeslocale technical-support identities are separate named accounts and receive no controller privacy permissions automatically. Support activity occurs only under documented Kairox instruction and narrow technical access. Kairox should maintain at least one named controller/security manager. Shared mailboxes such as `info@kairoxexchange.com` may remain notification destinations but should not be the long-term actor for privileged decisions.

Future break-glass access, if approved, must be named, MFA-protected, normally disabled where practical, tightly controlled, audited, and reviewed after every use. No routine shared super-admin is implemented.
