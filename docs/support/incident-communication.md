# Incident communication playbook

Status: release template; external channel and accountable incident team are
not yet configured.

For a customer-impacting incident, name an incident commander, operations lead,
communications lead, start time, affected regions/tenants, correlation IDs, and
next-update time. Never include client identity, message content, payment
credentials, secure links, or raw provider payloads.

Initial update (within 15 minutes of confirmed impact): state the affected
capability, customer-visible symptom, start time/time zone, current containment,
and next update. Do not guess a cause or recovery time.

Progress updates (at least every 30 minutes for critical incidents): state what
changed, current impact, safe workaround if one exists, and the next update.

Resolution update: state restored capability and time, advise customers about
any required reconciliation, and promise a follow-up only when an owner/date
exists. Link the internal incident to audit/correlation evidence and schedule a
blameless review.

Security/privacy incidents must use the counsel-approved notification plan;
public status text must not disclose an exploit path before containment.
