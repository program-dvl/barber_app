# Communications module

Status: Client notification workspace, SES email and SMS-only mobile release
implemented and locally verified (2026-10-04);
live provider, country-registration and legal certification remain launch gates.

Authoritative scope: [FR-13](../product-requirements.md#fr-13-notifications-and-communication),
[subscription packaging](../product-requirements.md#9-subscription-packaging-and-commercial-rules),
[ADR-039](../decisions.md#adr-039-multi-region-messaging-uses-safe-transport-promotion-and-optional-business-owned-senders),
and [ADR-041](../decisions.md#adr-041-launch-client-mobile-notifications-as-essential-sms-only),
and [ADR-052](../decisions.md#adr-052-client-notifications-is-an-operational-workspace-with-ses-and-twilio).

## Product outcome

ClipperDesk sends calm, useful appointment notifications by email and SMS. The
owner-facing **Client notifications** workspace offers either the shared
ClipperDesk text sender or an entitled, provider-approved branded text line.
Every text names the business. Pricing for extra credits is outside this phase.

The initial mobile release is deliberately SMS-only. Deferred mobile channels,
fallback selection, promotional texting and a conversational inbox are not
presented in the owner or public-booking experience. Their internal provider
adapters may remain dormant behind the application contract, but a configuration
allow-list prevents them from creating or delivering messages.

Business-account, security, team and billing email is a separate system described
in [business-email-notifications.md](business-email-notifications.md).

## Essential-notification policy

Texts are created only when the client explicitly selected SMS and valid consent
evidence exists. The supported service events are:

- booking confirmation, pending, approval or rejection;
- a material appointment change, reschedule or cancellation;
- a payment receipt;
- one reminder, normally 24 hours before the appointment;
- a time-sensitive waitlist opening requested by that client; and
- a turn-approaching queue update deliberately triggered by the business.

Deposit request/received templates and intent handlers exist, but no deposit
domain event currently calls them. They are labeled **Prepared**, excluded from
the ten connected rules and have no active toggle. OPEN-16/21 governs connecting
these events and a usable public payment action. A deposit-information link must
not be presented as a working payment interface.

Feedback and automated rebooking intents remain classified as marketing and are
suppressed because mobile marketing is disabled in this release. A booking does
not create marketing permission. SMS is not preselected on the public form.

One reminder is the default to avoid confirmation-plus-multiple-reminder fatigue.
Appointments created less than 24 hours in advance receive their confirmation but
not a late extra reminder. Rescheduling suppresses obsolete reminders and uses the
new appointment time in the replacement reminder's idempotency key. Quiet hours
are applied in the appointment's IANA time zone. The unadjusted 24-hour instant
must still be in the future before quiet-hours adjustment; overdue reminders
cannot be resurrected by moving them into a later quiet-hours window.

## Consent and suppression

- Public booking records the SMS selection, displayed wording, source, policy
  version, appointment and occurrence time as append-only evidence.
- Selecting SMS for a waitlist is an explicit request for that waitlist offer.
- Recognised STOP-family keywords create an all-purpose SMS suppression and
  append a withdrawal; START/UNSTOP releases it and appends a new grant.
- Consent, destination validity, sender readiness and suppression are checked
  again immediately before provider access.
- Email transactional delivery remains on when an address exists unless the
  appointment or client has explicitly opted out.

## Sender experience

**ClipperDesk text notifications** use a platform sender and identify the
business in the message body. **Your branded text line** requires the relevant
country's provider registration and an active tenant-owned SMS sender profile.
Provider credentials are never accepted or returned by the owner workspace.

Inbound SMS is retained only as needed for STOP/START compliance in this release;
the client inbox and free-form owner replies are not exposed. Historical sender,
conversation, consent and delivery records are preserved and remain tenant scoped.

## Reliability and invariants

- `CommunicationIntent` is the immutable event and correlation root.
- `CommunicationMessage` is unique per event, channel and encrypted destination.
- Appointment, waitlist and queue transactions write an idempotent operational
  event in the same database transaction as the domain change.
- An after-commit job processes new events immediately. The every-minute
  `communications:process-events` schedule is a recovery sweep for missed events
  and due retries, not a prerequisite for the first attempt.
- Only due `queued` or `retried` messages may enter a provider call. Bounded retry,
  idempotency, ordered callbacks and tenant-aware job context remain mandatory.
- A delivery worker suppresses any non-email channel outside
  `communications.client_mobile_channels`; old queued deferred-channel work
  therefore cannot reach a provider.
- Usage is reserved atomically for provider-bound SMS and released after a
  terminal pre-send suppression where applicable.
- Support diagnostics omit destination and message content.

## Transport promotion

| Mode | Network behavior | Delivery/cost posture |
| --- | --- | --- |
| `fake` | No provider request | Local deterministic simulation; no delivery or provider cost |
| `twilio_test` | Provider test API | SMS request validation only; no real client delivery |
| `live` | Approved production SMS sender | Real delivery and provider charges; requires `COMMUNICATIONS_LIVE_SEND_ENABLED=true` |

Any older transport value is treated as an unsupported legacy mode by the owner
screen. Copying credentials alone cannot enable live sending. Live authentication
prefers a restricted API key; the Auth Token or dedicated webhook token remains
available for callback-signature verification.

```dotenv
COMMUNICATIONS_TRANSPORT_MODE=fake
COMMUNICATIONS_IMMEDIATE_EVENT_DISPATCH=true
COMMUNICATIONS_LIVE_SEND_ENABLED=false

TWILIO_TEST_ACCOUNT_SID=
TWILIO_TEST_AUTH_TOKEN=
TWILIO_TEST_SMS_FROM=+15005550006

TWILIO_ACCOUNT_SID=
TWILIO_API_KEY_SID=
TWILIO_API_KEY_SECRET=
TWILIO_WEBHOOK_AUTH_TOKEN=
TWILIO_SMS_FROM=
```

Public HTTPS callbacks remain:

- delivery status: `POST /communications/webhooks/twilio`
- inbound compliance messages: `POST /communications/webhooks/twilio/inbound`

## Branded text-line onboarding

`fake` simulates an active branded SMS profile. Provider test mode uses a shared
identity and cannot activate branding. Live onboarding creates a pending profile
and keeps platform notifications active until an audited process verifies the
business, country, sender registration, webhook routing, STOP/START handling and
delivery evidence. Only then may the profile become active.

## Verification and launch gates

Automated coverage must prove SMS-only channel creation, explicit consent,
STOP/idempotency, one-reminder behavior, quiet hours, message uniqueness, bounded
retry, allowance enforcement, signed callbacks, deferred-channel suppression,
tenant isolation, credential non-exposure and explicit live locks.

Before enabling `live`, complete OPEN-11 and OPEN-15 plus an end-to-end rehearsal
for each supported country, including actual delivery, STOP/START, registration,
duplicate callbacks, number reassignment, usage reconciliation, support recovery
and emergency send-disable.

## Operational workspace (ADR-052)

**Automations** presents ten connected rules plus two visibly prepared deposit
handlers, searchable by name/purpose/group. Each shows actual trigger, timing,
channel readiness and independent email/SMS previews. A compact journey explains
confirmation, one reminder, material changes and receipt; upcoming cards use
actual queued records. No per-rule pause, multiple-reminder control, free-form
client sender or test-send endpoint is implied by a button. Provider connection
and consent are distinct from automation availability.

**Delivery history** uses a 25-record server page with channel/status/type,
business-local date range and exact client/appointment deep-link filters. Name
and reference searches escape SQL wildcards; contact matching uses normalized
identity hashes rather than decrypting every destination. Overview counts cover
seven days; failed attention counts cover unresolved failures, and simulated
sends are counted separately. Delivery drawers show permissioned destination,
source reference, safe explanatory reason and recorded timestamps. Raw provider
exceptions, credentials, message bodies, template variables and secure URLs are
excluded. Sent means provider acceptance, not confirmed recipient delivery.

**Channels & timing** supplies explicit, revision-checked saves for the existing
one-reminder policy, quiet hours, locale and sender preference. Owners/settings
managers edit and retry; receptionists with CalendarViewAll, ClientView and
ClientContactView can investigate only assigned locations. Finance types require
RevenueView. Tenant/source scope is enforced for history, overview, retry and
Calendar summaries. Provider credentials remain environment configuration.

Client profiles show six recent notifications with a link to filtered history.
Calendar appointment drawers show latest confirmation/reminder/change states
without message contents and link to appointment-filtered history. Historical
client preferences may be selected-channel lists or boolean maps; the profile
handles both. Saved checkbox choices persist explicit email/SMS booleans,
preserving withdrawn channels and deferred historical preferences. SMS selection
never substitutes for consent. Queued delivery rechecks withdrawal.

## Template editing and publication

Templates use existing immutable versions. Opening an editor shows the trigger,
plain email subject/body or SMS body, event-specific labeled variables/examples,
raw/sample preview and a confirmed restore-default action. SMS segment estimates
apply GSM-7 extension/UTF-16 counting to sample substitutions and are labeled
estimates. Email preview mirrors escaped text/line-break transport; no unsupported
branding, preview-text or button contract is claimed. New templates retain the
published system default as v1 while the first custom draft becomes v2.

Draft saves require the currently viewed version; publishing requires the latest
draft. A stale editor cannot silently overwrite another manager. Publication only
affects newly created intents: queued messages retain their captured version and
content. Meaningful saves/publication/preferences/retry commands record tenant
activity metadata without bodies or secure links. Dirty close/navigation and
validation focus protect work. Duplicate publish produces no extra audit event.

## SES email transport and signed delivery evidence

The product owner selected AWS SES on 2026-10-04. Client email uses the existing
AWS SDK and `services.ses` credentials, distinct from the account/security email
ledger. Shared mail configuration supplies the sender; the old `.local` client
placeholder is not used. Credentials are never returned in workspace projections.
Optional client-only overrides are supported without duplicate secrets:

```dotenv
COMMUNICATIONS_EMAIL_TRANSPORT_MODE=ses
# Set fake for isolated, network-free verification.
# SES_CLIENT_FROM_ADDRESS=verified-sender@example.com
# SES_CLIENT_FROM_NAME=ClipperDesk
# AWS_SES_CONFIGURATION_SET=client-notifications
# AWS_SES_CLIENT_SNS_TOPIC_ARN=arn:aws:sns:REGION:ACCOUNT:TOPIC
```

SES configuration presence is not identity verification, sandbox promotion or
actual delivery certification. For delivery evidence, route SES events through
the configured SNS topic to public HTTPS
`POST /communications/webhooks/ses`. Configure Send, Delivery, Bounce, Complaint,
Reject, Rendering Failure and Delivery Delay events. An AWS configuration set with
an SNS event destination preserves the `client_message` tag used to reconcile a
callback that races SendEmail persistence. Sender-region and topic-region must
match. No AWS topic/subscription/configuration set was created during local review.

Validate the exact configured topic, SNS RSA signature and canonical envelope.
Certificate downloads accept only the regional AWS SNS HTTPS certificate path,
with redirects disabled and bounded size/time. Signed subscription confirmation
is accepted only when its AWS endpoint, action, topic and token match the signed
envelope. Callbacks are hash-idempotent, tenant-bound through the provider message,
out-of-order guarded and do not retain their full body. Bounces/complaints apply
existing suppression. A callback racing a still-sending tagged record receives
503 for SNS retry rather than being marked irretrievably unknown. Legacy Resend
callbacks remain for retained historical records; new client email uses SES.

[SES SendEmail](https://docs.aws.amazon.com/ses/latest/APIReference/API_SendEmail.html)
has no application idempotency token. SDK retries are disabled. Only explicit
throttling receives bounded automatic retries. A timeout/unknown acceptance is
held for investigation without automatic or manual resend; this prevents a
possibly accepted email being duplicated. Explicit configuration/authentication
rejections permit a reasoned manager retry after repair. Uncertain historical
attempts cannot automatically cross from Resend to SES. Already accepted/delivered,
queued, suppressed and exhausted records cannot be replayed; retry claims lock
and recheck before one audited after-commit job is scheduled.

See [SNS signature verification](https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html)
and [SES SNS events](https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns.html).

## Secure actions and receipt handoff

Link expiry starts from the later of creation and intended delivery, so a future
reminder cannot ship an already expired action. Existing tenant/revocation/expiry
checks remain. Receipt intents target the issued immutable SaleReceipt and render
its recorded sale snapshot through a private, no-store, no-referrer, noindex
response. Older appointment-target receipt links resolve that appointment's
existing issued receipt. Receipt viewing is reusable until expiry; it does not
mutate a sale or generate another receipt. Other supported appointment actions
keep their existing single-use rules.

## Review evidence

[2026-10-04 review](../audits/2026-10-04-client-notifications/README.md) records
responsive screenshots, six product perspectives, deterministic provider tests,
and practical launch limits. No real client email/SMS was sent, live SMS was not
enabled, and no production history/schema or credentials were changed.

## 2026-10-05 capacity subscription metering (ADR-054)

For explicitly migrated capacity subscriptions, included texts reset monthly even
on annual subscriptions. Count rendered GSM/Unicode segments and destination
multipliers. Reserve the entire included/prepaid amount before sending; release
only the original reservation window on failed pre-send attempts. Unsupported
destinations suppress sending. Preserve SMS-only client channels, consent and
existing provider gates. Routine SES account emails remain included. Optional
prepaid purchases require verified payment, with automatic purchases off. See
`subscription-billing.md` for financial, release and refund/dispute boundaries.
