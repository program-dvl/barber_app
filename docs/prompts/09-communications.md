# Prompt 09: Reliable communications

Work in `/Applications/AMPPS/www/barber_app`.

Implement FR-13 for reliable, local-time, consent-aware transactional and
marketing communication. Launch must support email and one approved mobile
channel.

Read root `AGENTS.md`, the documentation index and status,
`docs/modules/communications.md`, related scheduling/client modules,
`docs/architecture.md`, `docs/quality-and-testing.md`, decisions, FR-13, and
notification evidence requirements in PRD Section 7. Resolve OPEN-06 and the
relevant OPEN-02 locale/consent rules before selecting the mobile provider.

Implement:

- application-owned email and mobile-channel contracts;
- notification intents for confirmation, pending/approval/rejection, reminders,
  changes, cancellation, deposits, receipts, waitlist, queue, feedback, and
  rebooking;
- tenant templates with allow-listed variables, preview, validation, locale,
  and safe fallback;
- configurable reminder offsets and quiet hours calculated in the correct local
  time zone;
- transactional-versus-marketing consent, unsubscribe, suppression, and legal
  basis;
- stable idempotency across event, channel, recipient, and attempt;
- queued/sent/delivered/failed/retried/suppressed states and provider IDs;
- bounded retries, terminal-failure handling, callbacks/webhooks, support
  diagnostics, and safe replay; and
- communication history linked to the client and source record.

Avoid putting message bodies or secrets in general logs. Secure action links
must be purpose-bound, short-lived, and revocable. Jobs must carry explicit
tenant context and correlation IDs.

Test duplicate domain events, queue retries, provider callbacks delivered twice
or out of order, missing variables, quiet-hour boundaries, daylight saving,
cancelled/rescheduled reminders, consent changes, invalid destinations,
cross-tenant template access, and provider outage recovery. Prove that one
intended event creates at most one message per channel.

Update the communications module, architecture/provider decisions, project
status, and support guidance. End with the event/channel matrix, consent matrix,
provider contract, delivery evidence, failure recovery, and tests.

