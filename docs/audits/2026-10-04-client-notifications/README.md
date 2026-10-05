# Client notifications review — 2026-10-04

Status: Implemented in the original project and verified locally. Requirements:
FR-13, FR-06, FR-08, FR-14, FR-19; decision ADR-052. This review preserves
ADR-039/041 and existing launch gates. [Specification](../../modules/communications.md).

## Evidence environment and boundary

The initial review inspected the existing workspace. Populated, mutation and
responsive checks then used a separate SQLite database named
`notification-workspace-review.sqlite`, synthetic Sarah/Emma/Aria records and
33 synthetic message outcomes. Fixture statuses are deliberately mixed; they
are not actual provider delivery evidence. A guarded review seeder prepares this
fixture without modifying the main business database. No live message, payment,
consent bypass, credential change, provider subscription or schema migration was
performed. SES/Twilio credentials were checked for presence without printing them.

New client email uses SES with the existing configured mail sender. SMS uses
Twilio's existing fake/test/live promotion contract. The local environment still
uses SMS simulation, and the client SES SNS topic is not configured. Sending
configuration is shown separately from verified delivery. Open country, domain,
legal, retention and public-payment gates remain open.

## Journey review

1. **Find a notification — Good.** Automations separates the actual event, timing
   and channel template actions. Search/categories and a short client journey
   replace the previous configuration-heavy page. Ten connected rules are counted;
   two deposit templates are explicitly prepared with no automatic trigger.
   [Desktop](26-final-desktop.jpg), [tablet](31-final-tablet-768.jpg),
   [phone](27-final-phone-390.jpg).
2. **Edit and preview — Good.** Context, plain content, labeled variable insertion,
   raw/sample preview and confirmed defaults support confident changes. Draft and
   publish use actual immutable versions. Existing published custom text is
   preserved; new default confirmations include the professional. SMS shows sample
   character/segment estimates. The shared drawer has guarded closing, focus
   containment and a compact accessible footer. [Email](33-final-email-editor.jpg),
   [tablet editor](32-final-tablet-editor.jpg), [SMS phone](34-final-phone-sms-editor.jpg).
3. **Investigate delivery — Good locally.** History uses 25-record pages and
   explicit filters. Sent, delivered, failed, suppressed and simulated outcomes
   are distinct; secure links/bodies and provider exceptions are not exposed.
   Failures show readable setup/action context and a reasoned retry only when safe.
   [Client-filtered history](25-client-filtered-history.jpg). Live provider
   certification remains outstanding.
4. **Save channel/timing choices — Configuration accurate; live confirmation
   pending.** Current configuration revisions prevent stale saves. Sender choice,
   one 24-hour reminder, quiet hours and fallback locale are separated from
   credentials. No additional reminder, pause or test-send infrastructure is
   implied. SES configuration and missing delivery connection are distinguished;
   Twilio simulation is explicit. [Final readiness](37-final-channel-readiness.jpg).
5. **Investigate from operations — Good.** Calendar details display the latest
   meaningful statuses and an appointment-filtered history link. Client records
   show six recent notifications with full-history handoff. A rendering failure
   for historical preference maps and a missing Calendar Link import were found
   and fixed during this step. [Appointment](22-appointment-context.jpg),
   [appointment history](23-appointment-history.jpg), [client](24-client-history.jpg).
6. **Read the client message — Good in deterministic tests; live inbox unverified.**
   New defaults include the service, location, professional and appointment time
   where available. Secure action links expire relative to intended delivery;
   receipts open the issued immutable sale snapshot. SMS consent and STOP/START
   take precedence; explicit client preferences survive profile edits and are
   checked again before queued sends. No real recipient was used for testing.

## Six requested perspectives

| Perspective | Review result and resulting improvement | Practical limit |
| --- | --- | --- |
| Salon owner | Three clear sections, trigger/timing labels, editable variables and publication boundaries make setup understandable. Prepared deposits no longer masquerade as connected automations. | Per-rule pause and two-reminder controls have no approved operational contract. |
| Receptionist | Assigned-branch history, compact Calendar outcomes and client-filtered links support “Was the reminder sent?” without editing templates. | Financial types require RevenueView; restricted contacts and own-calendar scope remain enforced. |
| Manager investigating failure | Friendly reasons, recorded attempts/timestamps, bounded retries and duplicate-command checks prevent blind resends. | Unknown SES acceptance requires reconciliation; it is intentionally ineligible for replay. |
| Client receiving a message | Professional defaults, business identification, current appointment details, working issued-receipt links and late-reminder suppression improve clarity. | Actual carrier/email inbox rendering and country registration need live rehearsal. |
| Senior product designer | Shared product tokens, dense automation rows, a real-content preview, contained mobile history scrolling and compact editor actions align with the redesigned workspace. | Sampled checks do not constitute an independent WCAG certification or every-device matrix. |
| Senior engineer | Tenant/branch/finance scope, credential-free projections, stale-version locks, immutable snapshots, signed callbacks and unknown-outcome holds protect reliability. | Local SQLite/mocked provider tests do not certify production load, multi-worker contention or live provider behavior. |

## Reliability changes verified

- Reserve the published system default while saving a first custom draft; reject
  stale drafts and stale settings; publishing never rewrites queued snapshots.
- Scope history, failure investigation, retries, client recent records and
  Calendar summaries to the same tenant/location/role boundary.
- Serialize manual retry, require a reason, bound total attempts and reject
  accepted/delivered/queued/suppressed/exhausted messages.
- Recheck client channel withdrawals, obsolete reminders and active captured SMS
  sender immediately before provider access. Release applicable reserved allowance
  after pre-send suppression. Past 24-hour reminders are not shifted into a later
  quiet-hours window.
- Anchor future action expiry to intended send time. Secure receipt links serve
  an existing immutable receipt, including legacy appointment targets, with
  private/no-store/no-referrer/noindex headers and no new financial record.
- Use SES SendEmail without SDK automatic retries. Explicit throttling alone is
  automatically retryable; unknown acceptance and uncertain old-provider attempts
  are held to avoid duplicates. Configuration/authentication failures can receive
  reasoned retry after repair.
- Validate the exact SNS topic, certificate endpoint and RSA envelope signature;
  constrain signed subscription confirmation. Process duplicate and out-of-order
  callbacks safely; bounce/complaint follows existing suppression. A callback
  racing message-ID persistence returns 503 for provider retry.

Provider behavior references:
[SES SendEmail](https://docs.aws.amazon.com/ses/latest/APIReference/API_SendEmail.html),
[SNS signature verification](https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html),
[SES SNS event publishing](https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns.html).

## Responsive and interaction evidence

Saved screenshot files were opened and visually inspected. Final page samples
report equal document and viewport width at 320, 360, 390, 768, 1024, 1280 and
1440 pixels. At 390, the SMS dialog width/scroll width is 388/388. History's
620-pixel table scrolls within its 356-pixel container; it does not widen the page.
These are observations of the sampled views, not an assertion about all states.

| View | Evidence |
| --- | --- |
| Desktop 1440 | [26](26-final-desktop.jpg), [33 email editor](33-final-email-editor.jpg), [37 readiness](37-final-channel-readiness.jpg) |
| Desktop 1280 / laptop 1024 | [36](36-final-desktop-1280.jpg), [35](35-final-laptop-1024.jpg) |
| Tablet 768 | [31](31-final-tablet-768.jpg), [32 editor](32-final-tablet-editor.jpg) |
| Phone 390 | [27 automations](27-final-phone-390.jpg), [28 filters](28-final-phone-history.jpg), [34 SMS editor](34-final-phone-sms-editor.jpg), [38 records](38-final-phone-history-records.jpg) |
| Small phones 320 / 360 | [29](29-final-phone-320.jpg), [30](30-final-phone-360.jpg) |
| Operational handoffs | [22 appointment](22-appointment-context.jpg), [23 filtered history](23-appointment-history.jpg), [24 client recent](24-client-history.jpg), [25 client-filtered](25-client-filtered-history.jpg) |

Interaction review also exercised variable insertion, sample/raw switching,
unsupported-variable validation, field focus, dirty discard, saved draft then
publication, settings success, filtering/reset, server pagination, empty results,
long names and delivery detail. Publication/preferences used only the isolated
fixture. No provider-bound retry or test-send was submitted in the browser.

Files 01–21 are **historical baseline/iteration evidence**, not the final
acceptance layout. In particular, 03 includes an early preview-heading mismatch;
09/10/11 predate the corrected ten-rule count and SES readiness; 13/15/17 predate
mobile width/header corrections. They are retained to document review iterations.
06 captures the isolated published v2 transition; 11 captures an isolated saved
preference transition. Use files 22–38 for the final review.

## Automated verification

- Full repository backend: **479 passed / 5,132 assertions**, with 28 existing
  intentional legacy skips; final run after client preference withdrawal coverage.
- Communications suite: **44 passed**. The dedicated SES tests mock the AWS SDK
  and certificate HTTP path: payload/message IDs, no hidden SDK retries, explicit
  throttling, unknown acceptance, duplicate Delivery/Bounce, invalid signatures,
  topic/certificate restrictions, constrained subscription confirmation, callback
  race and uncertain legacy provider attempts. No external request is needed.
- Frontend helpers: **57 passed**, including event variable validation, sample
  rendering, GSM-7/UTF-16 segmentation, status labels and legacy preference formats.
- Client/SSR production builds pass with Node 24.19.0. The 17 front-site route
  asset budgets, scoped PHP formatting and repository whitespace checks pass.
- No new dependency or application migration was added by this task. Concurrent
  Reports and existing workspace changes were preserved.

Reproduce with `php artisan test`, `node --test tests/Frontend/*.test.mjs`,
`npm run build`, and `npm run check:frontsite-budgets` using the manifest-required
Node version. Run browser fixture preparation only with APP_ENV=testing and its
separately named SQLite database. Local preview records are synthetic.

## Remaining scope and release evidence

OPEN-21 records the absence of deposit domain dispatch; OPEN-16 retains the
public deposit interface gap. Prepared templates cannot imply automatic collection
or confirmed paid booking. Marketing, WhatsApp/push, inbox, memberships/packages,
multiple reminders, coalescing delays and free-form send/test features were not
added. Existing retention and regional release decisions were not waived.

Credentials are present, but actual SES identity/sandbox eligibility, SNS delivery
subscription and actual Twilio delivery/registration/STOP rehearsal remain
external evidence. The implemented SNS endpoint is ready for the configured,
verified topic; the environment still needs AWS_SES_CLIENT_SNS_TOPIC_ARN and the
corresponding SES event destination to populate confirmed email delivery.
