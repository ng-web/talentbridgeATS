# Potential breach operations

## Controller decisions are evidence, not software conclusions

Incident technical status, severity, and potentially affected scope do not set breach assessment or notification decisions. Only a currently active administrator with the direct `privacy.incidents.controller-decision` permission can append controller-provided evidence. Previous versions remain immutable and show actor, timestamp, authority security version, controlled reason, and decisions for assessment, authority notification, and data-subject notification.

Allowed values intentionally include `pending` and `requires_further_review`. They do not encode legal thresholds. There are no hardcoded legal deadlines, legal risk scores, jurisdiction rules, automatic messages, or regulator integrations.

## Escalation and acknowledgement

Processor escalation and controller acknowledgement are separate one-time transitions. Escalation records its actor and time. Acknowledgement requires controller-decision authority and records a distinct actor and time. No mail is sent in Pass 4; if minimal portal-alert mail is added later, delivery must remain separate from acknowledgement.

## Preservation and closure

Incident operations do not create a second hold system. A doubly authorized operator can explicitly invoke the existing legal-hold service, after which the incident stores only an immutable link and semantic timeline/audit evidence. Incident close/reopen cannot release or duplicate a hold. Releases remain solely within the Pass 3 authorized workflow.

Closure is a technical state. It requires resolved lifecycle state, a current authorized owner, at least one explicit controller-decision evidence version, and acknowledgement when escalated. It does not require or imply regulator notification. Reopening preserves closure and all controller-decision history.

## Data minimization and activation

No attachment or export subsystem was added because controlled structured evidence and the authenticated summary view satisfy this pass. Operators must never enter credentials, tokens, documents, full individual records, raw webhooks, request payloads, or database dumps. The audit allowlist accepts controlled codes and counts only; encrypted summaries and rationales are excluded from audit metadata and application logs.

Deployment alone remains dormant: no default grants, automatic manager assignment, scheduler, deadline, incident ingestion, message dispatch, controller decision, hold creation/release, or containment action is activated.
