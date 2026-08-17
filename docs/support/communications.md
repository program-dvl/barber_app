# Communications support playbook

Status: Implemented diagnostic and safe-replay contract; production provider
credentials and sender certification remain launch gates.

## Diagnose without message content

An authorized tenant user with `settings.manage` uses the Business
communication diagnostic surface. A platform role alone cannot inspect or
replay a tenant message. Support enters an approved ADR-009 grant with the
`communications` scope; the named, ticketed session is time-limited, visible to
the tenant, and accepts only that Business's message identifiers. Search by the internal
message ID, provider message ID, Business, correlation ID, or source record.
Confirm:

1. intent and source record;
2. channel and recipient fingerprint (never the full destination);
3. queued/sending/sent/delivered/retried/failed/suppressed state;
4. attempt count, safe error code/class, and next-attempt time;
5. provider request/message ID and callback evidence; and
6. whether the event was ignored as a duplicate or out of order.

Do not ask for or paste message bodies, action URLs, API keys, webhook secrets,
or full destinations into tickets, chat, general logs, or audit reasons.

## Recovery decisions

- `invalid_destination`, hard bounce, complaint, or opt-out: do not replay.
  Have an authorized shop user correct contact/consent. A later source event
  creates a new destination-scoped message.
- `marketing_consent_missing`, `whatsapp_opt_in_missing`, or
  `destination_suppressed`: do not bypass. Consent must be collected through a
  normal product flow and applies only to future eligible delivery.
- `template_not_published`, rejected, paused, or disabled WhatsApp template:
  approve or replace the provider template before replay. Never change the
  historical template/message row to disguise what was attempted.
- transient outage/rate limit: allow bounded retries. If the message becomes
  terminal only after the provider is healthy, use reasoned safe replay.
- `provider_not_configured`: restore secret-manager/provider configuration,
  verify callback health, then replay with an operational reason.
- cancelled/rescheduled source: no replay. Confirm the replacement Appointment
  has its own communication intent and reminders.

## Safe replay

Replay only failed, retried, or interrupted messages whose source, destination,
consent, template approval, and action expiry remain valid. Enter a concise
reason. Replay preserves the same message and provider idempotency key, appends
a new attempt, and writes an audit event; it never creates another intended
message or edits prior provider evidence.

Automatic delivery is capped at four attempts. Each eligible reasoned replay
authorizes only one additional provider attempt, and the absolute message cap
is eight attempts. Provider configuration and safely idempotent Resend 429/5xx,
transport, and missing-ID errors are eligible. Twilio permits only definite 429
rejection retries; an ambiguous transport/5xx or missing-SID result must be
reconciled with Twilio and is not replayable. Invalid destinations, consent
blocks, hard bounces, complaints, and other terminal failures are also ineligible.

If the action link expired or the destination/consent changed, generate a new
source event through the normal business workflow instead of replaying stale
content. Database edits are not an approved recovery path.

From platform operations, use a unique operation key for each intended replay.
Repeating that key returns the first replay result and does not dispatch another
attempt. A message from another Business returns not found even if its numeric
identifier is known. Exit or revoke the support session when the ticket is
resolved.

## Escalation

Escalate a provider incident when retryable failures spike, callback delay
prevents status convergence, signature failures occur, or accepted-to-delivered
performance risks the FR-13 reliability target. Include only tenant-safe IDs,
counts, timestamps, provider error codes, and correlation IDs. Security owns
signature anomalies; Operations owns queue/provider health; Product/Privacy
owns consent/template classification questions.
