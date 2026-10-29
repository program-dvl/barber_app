# Communications module

Status: Client notification workspace, SES email and SMS-only mobile release
implemented and locally verified; platform-managed simplification 2026-10-14 (ADR-067);
live provider, country-registration and legal certification remain launch gates.

Authoritative scope: [FR-13](../product-requirements.md#fr-13-notifications-and-communication),
[subscription packaging](../product-requirements.md#9-subscription-packaging-and-commercial-rules),
[ADR-039](../decisions.md#adr-039-multi-region-messaging-uses-safe-transport-promotion-and-optional-business-owned-senders),
and [ADR-041](../decisions.md#adr-041-launch-client-mobile-notifications-as-essential-sms-only),
and [ADR-052](../decisions.md#adr-052-client-notifications-is-an-operational-workspace-with-ses-and-twilio),
and [ADR-067](../decisions.md#adr-067--platform-managed-client-notifications-with-simple-channel-controls).

## Product outcome

ClipperDesk sends calm, useful appointment notifications by email and SMS. The
owner-facing **Client notifications** workspace offers simple email/SMS channel
choices, quiet hours, sample messages and delivery history. ClipperDesk manages
all new message wording and sender identity. Every text names the business.
SMS credits follow the capacity subscription contract below.

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
domain event currently calls them. They stay internal, excluded from
the ten connected rules and owner examples, and have no active toggle. OPEN-16/21 governs connecting
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

**ClipperDesk text notifications** use the shared platform sender and identify
the business in each message. New notifications never select an owner-branded
profile. Retained branded sender records remain tenant scoped for historical
callbacks and compliance routing; there is no owner onboarding or activation.
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
TWILIO_AUTH_TOKEN=
TWILIO_MESSAGING_SERVICE_SID=
TWILIO_SMS_FROM=
TWILIO_WEBHOOK_BASE_URL=https://clipperdesk.com
```

Public HTTPS callbacks remain:

- delivery status: `POST /communications/webhooks/twilio`
- inbound compliance messages: `POST /communications/webhooks/twilio/inbound`

ADR-073 simplifies production SMS to the same seven-setting block locally and on
the server: the two explicit live controls, account SID, Auth Token, Messaging
Service SID, number and public callback base. Test credentials shown above are
only for the optional test mode and are absent from the launch environment.
API-key and endpoint overrides remain backward-compatible advanced options.
After deployment, `/api/webhooks/twilio/status` and `/api/webhooks/twilio/inbound`
are also signed aliases. Current live Twilio settings use the existing paths.
The public URL must reach the sending application's database for callback and
client-action round trips; a live request from localhost alone does not establish
that boundary. See the [SMS deployment checklist and WhatsApp requirements](../audits/2026-10-29-twilio/README.md).

Platform SMS uses the Messaging Service pool rather than forcing the same `From`
in every market. Sender/transport changes create a new audited profile and retain
the captured old identity; stale queued messages are suppressed before sending
or allowance debit. Signed early callbacks reconcile after the SID is saved and
on the scheduled sweep. Duplicate/late callbacks cannot downgrade delivery.
Unreachable recipients do not become opted out solely from `undelivered`; Twilio
21610 supplies explicit provider opt-out evidence. Incoming shared-number replies
resolve only an unambiguous client/tenant; OPEN-24 retains broader preference
routing. WhatsApp remains disabled; no production fallback is claimed.

## Retained sender history

Owner branded-sender onboarding is retired by ADR-067. The authenticated legacy
endpoint returns 410 without creating or changing a profile. Existing profiles
and inbound routing are preserved; new operational intents use the platform
sender regardless of a retained legacy preference.

## Verification and launch gates

Automated coverage must prove SMS-only channel creation, explicit consent,
STOP/idempotency, one-reminder behavior, quiet hours, message uniqueness, bounded
retry, allowance enforcement, signed callbacks, deferred-channel suppression,
tenant isolation, credential non-exposure and explicit live locks.

Before enabling `live`, complete OPEN-11 and OPEN-15 plus an end-to-end rehearsal
for each supported country, including actual delivery, STOP/START, registration,
duplicate callbacks, number reassignment, usage reconciliation, support recovery
and emergency send-disable.

## Operational workspace (ADR-067; replaces ADR-052 owner editing)

**Notifications** has one platform-managed introduction, email/SMS switches,
quiet hours and three grouped coverage cards. Only the ten connected essential
rules are presented. The existing single 24-hour reminder remains; per-rule
controls, extra reminder offsets, deposit triggers and test sends are not added.
Read-only email/SMS examples use synthetic values and create no provider work.
The HTML example uses the same Blade renderer as delivery in a sandboxed iframe.

**Delivery history** uses a 25-record server page with channel/status/type,
business-local date range and exact client/appointment deep-link filters. Name
and reference searches escape SQL wildcards; contact matching uses normalized
identity hashes rather than decrypting every destination. Overview counts cover
seven days; failed attention counts cover unresolved failures, and simulated
sends are counted separately. Delivery drawers show permissioned destination,
source reference, safe explanatory reason and recorded timestamps. Raw provider
exceptions, credentials, message bodies, template variables and secure URLs are
excluded. Sent means provider acceptance, not confirmed recipient delivery.

Managers save top-level channel choices and quiet hours explicitly with stale
revision protection. Disabling a channel suppresses waiting queued/retried/held
work and releases reserved SMS allowance; already claimed work cannot be recalled.
Dispatch rechecks channel state, consent and client preference. Re-enable never
resends suppressed work. Existing records default to both channels enabled,
without changing provider transport gates. The owner's template-language field
and sender selector are removed; platform content currently uses English and does
not claim automatic translation. Receptionists retain scoped read-only access.
Finance types require RevenueView; tenant/source boundaries cover history,
retries, examples and Calendar handoffs. Credentials remain configuration.

Client profiles show six recent notifications with a link to filtered history.
Calendar appointment drawers show latest confirmation/reminder/change states
without message contents and link to appointment-filtered history. Historical
client preferences may be selected-channel lists or boolean maps; the profile
handles both. Saved checkbox choices persist explicit email/SMS booleans,
preserving withdrawn channels and deferred historical preferences. SMS selection
never substitutes for consent. Queued delivery rechecks withdrawal.

## Platform content and client email

New email/SMS intents lazily resolve an immutable platform content revision,
identified internally in template fallbacks. A tenant lock and version increment
avoid clashes with retained versions; published owner custom content is not
selected for new operational events. Queued messages retain their captured
version. No owner template payload/variable catalog is projected. Retired save,
preview and publish endpoints return authenticated 410; foreign records return
404, and unauthorized roles remain 403.

SES receives responsive, inline-styled HTML and plain text. The structured email
uses ClipperDesk branding, a status/preheader, purpose-specific introduction,
local date/time/timezone, available visit/recorded payment facts, location arrival
information and a labelled action button. Buttons are omitted when no validated
HTTP(S) action exists. Pending bookings never claim confirmation; cancellation
and waitlist messages do not imply a booked or paid state. All values are escaped.
Legacy captured content receives a branded escaped wrapper without changing its
statement. The verified mail address remains configured; display name uses the
platform brand, never the business. Inbox/carrier certification remains separate.

## SES email transport and signed delivery evidence

The product owner selected AWS SES on 2026-10-04. Client email uses the existing
AWS SDK and `services.ses` credentials, distinct from the account/security email
ledger. Shared mail configuration supplies the sender; the old `.local` client
placeholder is not used. Credentials are never returned in workspace projections.
The verified address, configuration set and topic may be overridden without
duplicate secrets; the display name is owned by the platform brand:

```dotenv
COMMUNICATIONS_EMAIL_TRANSPORT_MODE=ses
# Set fake for isolated, network-free verification.
# SES_CLIENT_FROM_ADDRESS=verified-sender@example.com
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

## Appointment arrival details (2026-10-12; ADR-064)

New appointment intents capture assigned-location snapshots, address, optional arrival/parking notes and key-free directions. A captured footer supplies those details to existing templates without rewriting published versions or queued messages. The actual final content is used for SMS segment accounting. [Locations contract](location-intelligence.md). WhatsApp remains disabled under ADR-041.
