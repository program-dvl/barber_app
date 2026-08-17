# Module: Communications

Status: Implemented and verified on 2026-08-14. Production provider
certification and launch-market legal review remain release gates.

Requirement: FR-13. Provider and locale/consent decisions: ADR-019.

## Purpose

Deliver timely transactional communications and permitted marketing messages
without duplication, time-zone errors, hidden failures, or consent confusion.
The launch channels are Resend email and Twilio WhatsApp; both remain behind
application-owned contracts.

## Implemented responsibilities and invariants

- An operational event becomes one immutable `CommunicationIntent`. A unique
  Business/event/intent constraint and a message key derived from event,
  channel, and normalized recipient prove that replay creates at most one
  message per intended channel.
- `EmailChannelProvider` and `MobileChannelProvider` own provider edges.
  Resend receives the stable message idempotency key. Twilio WhatsApp receives
  an approved Content SID and application correlation ID; because its standard
  create API has no documented idempotency input, ambiguous transport/5xx
  results are terminal for reconciliation rather than automatically retried.
- Confirmation, pending, approval, rejection, reminder, change, cancellation,
  deposit request/receipt, payment receipt, waitlist, queue, feedback, and
  rebooking intents are implemented. Existing Appointment, waitlist, walk-in,
  and operational events are consumed by `communications:process-events`.
- Business templates are versioned by intent, channel, and locale. Rendering
  accepts only the documented variable catalogue. Optional values have safe
  fallbacks; missing secure/required values fail validation. WhatsApp drafts
  cannot publish without an approved provider template ID.
- India/`en-IN` is the communications launch profile and safe locale fallback.
  Appointment/Location IANA time zones still govern user-visible time and
  reminder calculation. Configured reminder offsets default to 24 hours and
  two hours; local quiet hours default to 21:00–08:00. A quiet-hours adjustment
  can never move a reminder to or beyond its Appointment; such a reminder is
  placed one minute before quiet hours begin instead.
- Transactional necessity never grants marketing permission. Marketing needs
  active explicit consent, selected channel, enabled Business policy, and no
  suppression. WhatsApp always needs explicit channel opt-in. Eligibility is
  checked both when queued and immediately before send.
- Destinations and variable snapshots are encrypted. Rendered message bodies
  are not persisted or returned in logs/support diagnostics; only content
  hashes are retained after provider acceptance.
- Purpose-bound action records are short-lived, revocable, and consumable.
  Their temporary signed URLs are reconstructed for rendering; raw bearer
  tokens are not stored.
- Delivery records expose queued, sending, sent, delivered, failed, retried,
  and suppressed states; append-only attempts retain provider request/message
  identifiers and safe error codes. Four attempts use bounded 60/300/900
  second backoff.
- Signed provider callbacks are deduplicated by provider event ID and payload
  hash. Provider occurrence time plus state precedence prevents an older
  failure from rewinding a delivered message.
- Communication history is linked to Business, Client, intent, and source
  record. It participates in Client merge, profile history, and privacy export
  without exporting destinations or message content.

## Event/channel matrix

| Intent | Source events | Category | Email | WhatsApp | Action |
| --- | --- | --- | --- | --- | --- |
| Booking confirmation | appointment created/confirmed | Transactional | Yes | With WhatsApp opt-in | View appointment |
| Pending | appointment pending | Transactional | Yes | With opt-in | None |
| Approval | pending to confirmed/approved | Transactional | Yes | With opt-in | View appointment |
| Rejection | booking rejected | Transactional | Yes | With opt-in | Rebook |
| Reminder | configured pre-appointment offsets | Transactional | Yes | With opt-in | View appointment |
| Change | reschedule, move, resize, reassign, service/contact change | Transactional | Yes | With opt-in | View replacement/current record |
| Cancellation | client/shop cancellation | Transactional | Yes | With opt-in | Rebook |
| Deposit | deposit requested/received | Transactional | Yes | With opt-in | Payment status where required |
| Receipt | payment receipt | Transactional/legal | Yes | With opt-in | Receipt view |
| Waitlist | opening offered | Requested service | Selected email | Selected WhatsApp is request-scoped opt-in | Claim opening |
| Queue | turn/estimate update | Requested service | When an email recipient exists | Requires explicit WhatsApp opt-in evidence | None |
| Feedback | visit completed/requested | Marketing | With marketing consent | With marketing + WhatsApp consent | Feedback + unsubscribe |
| Rebooking | rebooking due | Marketing | With marketing consent | With marketing + WhatsApp consent | Rebook + unsubscribe |

SMS, in-app, and browser push remain possible later contracts, not launch
delivery surfaces. Public channel selectors no longer offer unconfigured SMS.

## Consent matrix

| Communication | Required basis | Marketing consent | Channel preference | WhatsApp opt-in | Suppression effect |
| --- | --- | --- | --- | --- | --- |
| Appointment/waitlist/queue operation | Contract performance or explicit requested service | No | Selected/requested channel | Always | `all` blocks; `marketing` does not |
| Deposit operational update | Contract performance | No | Selected channel | Always | `all` blocks |
| Mandatory receipt | Legal obligation/contract record | No | Email can be used when valid | Always for WhatsApp | Marketing unsubscribe does not block |
| Feedback/rebooking | Explicit marketing consent | Yes, active at send time | Yes | Yes | `marketing` or `all` blocks |
| Provider hard bounce/complaint | Not sendable | N/A | N/A | N/A | Creates `all` suppression |

Withdrawal appends consent evidence and leaves historical consent and delivery
records intact. A marketing unsubscribe changes current eligibility only;
transactional receipts and essential appointment operations remain eligible.

## Provider contract

| Boundary | Resend email | Twilio WhatsApp |
| --- | --- | --- |
| Application contract | `EmailChannelProvider` | `MobileChannelProvider` (`whatsapp`) |
| Provider idempotency | `Idempotency-Key` | Application unique message/send guard; provider SID reconciliation (no documented create-message key) |
| Template rule | Published Business template | Published Business template plus approved Content SID |
| Callback verification | Svix HMAC/timestamp | Twilio URL/form HMAC |
| Provider evidence | Email ID and request ID | Message SID and request ID |
| Normalized success | sent/delivered | sent/delivered/read |
| Normalized failure | failed/bounced/complained | failed/undelivered |
| Secret handling | Environment/secret manager only | Environment/secret manager only |

Production activation requires Resend domain authentication, webhook secret,
Twilio sender onboarding, approved utility/marketing Content SIDs, callback
secret validation, and the OPEN-11 sender-identity gate.

## Delivery evidence and support visibility

`communication_intents`, `communication_messages`,
`communication_delivery_attempts`, `communication_provider_events`, and
`communication_suppressions` provide the durable trail. Support diagnostics
show Business/message/source IDs, intent, channel, recipient fingerprint,
state, attempts, provider IDs, safe error code/class, correlation ID, and next
attempt. They explicitly return `content_available_to_support=false` and never
include destination, subject, body, variables, credentials, or action URLs.
The diagnostic and replay routes require the tenant `settings.manage`
permission and explicit Business ownership. A platform role alone cannot use
them; future support personnel require the separate ADR-009 support grant.

Client profile history and privacy export include intent/channel/category,
legal basis, state, and delivery times. Client merge repoints all four Client-
linked communication tables under the existing row-locked merge command.

## Failure recovery

| Failure | Automatic behavior | Safe support action |
| --- | --- | --- |
| Invalid destination | Suppress before provider access | Correct Client contact; do not replay old destination |
| Consent withdrawn while queued | Suppress during delivery preflight | No replay; obtain a new valid consent for a new event |
| WhatsApp template unapproved/paused | Suppress or terminal provider configuration failure | Approve/replace template, then reasoned replay if payload remains valid |
| Rate limit/provider outage | Resend and definite Twilio 429 rejection use bounded retry; ambiguous Twilio transport/5xx is terminal | Reconcile Twilio by SID before any new source event; never blindly replay ambiguity |
| Four failed attempts | Terminal failed with safe error code | Diagnose provider/configuration and use reasoned replay endpoint; operator attempts remain capped at eight total |
| Duplicate callback | Return existing provider-event result | None |
| Older callback after delivery | Retain as ignored evidence; do not rewind | None |
| Cancelled/rescheduled reminder | Suppress before provider access | None; replacement schedules its own reminders |
| Hard bounce/complaint | Terminal failure and destination suppression | Correct destination/consent before a new intent |

The replay path never edits provider IDs or attempt history, keeps the original
message and provider idempotency key, requires a reason, writes a content-free
audit event, and dispatches through the normal tenant-aware job. Only provider
configuration and safely idempotent Resend 429/5xx, transport, and missing-ID
failures are replayable; Twilio permits only definite 429 retries/replay.
Destination, consent, ambiguous Twilio, and other terminal errors are rejected.

See [`../support/communications.md`](../support/communications.md) for the
operator playbook.

## Verification evidence

`tests/Feature/Communications/ReliableCommunicationsTest.php` has 10 tests and
83 assertions covering:

- duplicate domain event convergence across email and WhatsApp;
- allow-listed variables, safe fallbacks, required-variable errors, and
  cross-tenant template denial;
- overnight quiet-hour boundaries, never-after-Appointment adjustment, plus
  spring-gap and repeated-hour DST;
- transactional versus marketing consent, explicit WhatsApp opt-in,
  unsubscribe, and marketing-only suppression;
- bounded Resend outage retries with one stable provider idempotency key and
  terminal handling of ambiguous WhatsApp transport outcomes;
- duplicate and out-of-order delivery callbacks;
- invalid signatures plus valid Resend/Svix and Twilio callback signatures;
- invalid destinations and cancelled/rescheduled reminder preflight;
- consent withdrawal after queueing but before provider access;
- content-minimized diagnostics, bounded reasoned replay, revocable signed
  action links, and explicit tenant/correlation job payloads.

Full SQLite verification on 2026-08-14: 153 passed, 28 deliberately skipped,
1,015 assertions in 14.32 seconds. The skipped MySQL booking concurrency cases
are unrelated to the communications state machine and retain their prior
dedicated production-engine evidence. Provider sandbox certification, callback
burst/load evidence, and real sender/template approval remain Prompt 13 launch
gates; local contract fakes do not claim external delivery certification.
