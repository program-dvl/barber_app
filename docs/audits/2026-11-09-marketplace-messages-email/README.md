# Business Messages, Marketplace email and private-page continuity

Date: 2026-11-09, Asia/Kolkata. Local working-tree implementation only.
Scope: MP-01/02/04, FR-13, MPG-07; approved refinements recorded in PRD/decisions.
Existing unrelated changes were preserved. No deployment, live email, campaign,
production indexation, customer acquisition or revenue result is claimed.

## Reported behavior and source audit

The owner’s screenshots showed Business Messages beneath a Marketplace marketing
hero, listing controls and booking-entry counters, with an ivory Marketplace canvas.
`BusinessMarketplaceController::index` returned all of these in one page;
`Marketplace/Business.vue` applied `.market`. Its unread count ran once per row.
Business conversations reused the customer palette and a large context column.
`BookingMessages::send` saved encrypted/replay-safe messages but had no email alert.

Marketplace mail used a remotely referenced development-host PNG, while its theme
still inherited blue wrapper/body/footer colours. Secure Manage booking and related
self-service pages used the old Business booking ProductMark/canvas. Public receipt
HTML retained its Business document branding. These are baseline gaps, not earlier
proof that the final refinement had already shipped.

## Implemented contract and source paths

- `Layouts/BusinessMessagesLayout.vue`, `Marketplace/BusinessInbox.vue`, shared
  `Conversation.vue`, `Layouts/AppLayout.vue`: ordinary Business header/tokens,
  white/slate surfaces, compact two-pane inbox/selected conversation, mobile return,
  customer/reference search, All/Unread, latest preview and explicit Refresh.
  Booking context is collapsed. No sockets, presence or automatic realtime claim.
- `BusinessMarketplaceController`, CustomerBooking relationships: 25-row pages with
  eager latest message and aggregate unread counts. Calendar, tenant, branch and
  client-contact scope precede projection. Direct thread/reply authorization remains.
  No customer email, CRM fields or message content reaches public analytics.
- Existing `/app/marketplace` opens Messages. New `/app/marketplace/settings`, linked
  from Business setup, retains listing/publication/outcome controls behind
  BusinessSetupAccess. Existing listing/preview/publication/report routes survive.
- `BookingMessages`, `SendMessageEmail`, `BookingMessageNotification`,
  `SweepMarketplaceMessageEmails`, migration
  `2026_11_09_000002_add_marketplace_message_email`: one nullable unique notification
  UUID per new message, atomic delivery-ledger row and after-commit dispatch. Replay
  and rollback remain safe. New-message email only; historical messages are untouched.
- Customer → lowest-ID active verified owner/main account; Business → currently
  verified booking customer whose email still matches the appointment. Delivery
  rechecks active business, tenant, recipient, pause/read state. No fallback to public
  contact, foreign tenant or unverified email. Already read/paused messages skip mail.
- Alerts contain a generic notice and signed-in conversation URL, no message body,
  client identity, CRM details or bearer link. `CustomerConversationReturn` and
  MarketplaceSession preserve only a validated same-site conversation path for
  30 minutes across email/Google authentication. Ownership is checked on arrival.
- Existing EmailNotificationDelivery records queued/sending/accepted/skipped/unknown
  outcomes. Provider acceptance is not inbox delivery or a read receipt. Duplicate
  jobs cannot re-send a claimed alert. Failures/uncertain sends preserve messages;
  stale sending becomes unknown after 15 minutes. No blind replay.
- `MarketplaceEmailBrand`, mail partial/theme/layout, SignInLink view and SES adapter:
  packaged raster CID image, text wordmark fallback, uniform ivory surround, white
  content/forest-sage actions, no redundant pill/kicker. Actual MIME keeps PNG/text/
  PDF parts, sender/tags and uncertainty semantics. Business mail theme is unchanged.
- `PublicBookingLayout` self-service mode, `Handler`, `Marketplace/Unavailable`:
  Marketplace shell for management/action pages, waitlist and existing forms/completion,
  including invalid/expired-link recovery. Existing guest tokens/action purposes,
  timezone/DST, booking commands and private headers remain authoritative.
- `CommunicationActionController` and `receipts/sale.blade.php`: Marketplace branding
  only for signed customer receipt HTML; immutable snapshot, owner print and PDF
  presentation remain intact.
- Raw-HTML verification found the shared AppLayout used a browser-global route helper.
  It now injects Ziggy's per-app helper, allowing real Business SSR without a
  process-global URL configuration or cross-request identity state.

## Verification evidence

| Check | Local result |
| --- | --- |
| Serial targeted PHP regression: accounts/refinement, inbox/email, owner workflow, notification workspace, SES, visit mail, Business email, public booking/waitlist/reviews | 174 passed, 1 skipped, 1,622 assertions; skip was the separately run live SSR case |
| Built live SSR inbox/thread/private management/actions plus directory/profile/robots/cache/fallback class | 28 passed, 383 assertions |
| Complete frontend suite, including mounted search/unread/refresh and failed-reply draft/replay tests | 243 passed |
| Synthetic 100-booking inbox | 25 returned, total100, at most3 message-bearing SELECTs; aggregate unread/latest eager loading; no per-row count/availability fan-out |
| Client + SSR build | Passed on installed Node24.19.0; existing font/CSS warnings remain |
| Frontsite budgets | Passed for25 route entries |
| Local migration | Applied additively to verified local MySQL development database; no preview/production database migrated |
| Whitespace/scoped PHP formatting | Passed |

Tests cover rollback, duplicate send/job, queue recovery, changed recipient,
read/pause/inactive suppression, missing verified owner, revoked owner, multi-owner
main selection, real array mail/PNG MIME, failed/uncertain email, disabled alerts,
foreign tenant/assigned branches, settings denial, login resume/open-redirect
rejection and private headers. Existing booking/review/manual collection regressions
remain green. SQLite tests do not certify MySQL lock contention or live deliverability.
Local migration success is not production migration evidence.

Sanitized logs: [backend](backend.txt), [raw HTML/SSR](ssr.txt),
[frontend](frontend.txt), [build](build.txt), [budgets](budgets.txt).

## Preview artifacts and precise visual gap

- [Sign-in HTML](sign-in-preview.html), [sign-in MIME sample](sign-in-sample.eml).
- [Message alert HTML](message-preview.html), [message MIME sample](message-sample.eml).

These are rendered actual templates with synthetic recipients and inert example.test
links, produced using array transport only. They are review artifacts, not screenshots,
real private links or evidence of delivery. PNG MIME bytes and self-contained preview
bytes match the packaged logo. No remote image download is needed.

CUA inventory returned exactly **“Local browser use is disabled in Work settings”**.
Actual desktop/mobile Messages interactions, long notices, final private-page visuals
and Gmail/Outlook rendering remain unverified. No substitute screenshot is presented.
No existing user tab/draft was reloaded. Before release, an operator must visually
check inbox/selected conversation/search/unread, empty/paused/read-only/send failure,
mobile back/navigation, secure actions and a delivered synthetic email in supported
mail clients. The source/build/SSR checks do not replace this gap.

## Rollout and recovery

1. Review this working tree and shared dependencies; build/deploy application code,
   Blade/CSS and `public/images/brand/clipperdesk-marketplace-mark.png` together.
2. Apply the additive migration in the authorized target environment:
   `php artisan migrate --path=database/migrations/2026_11_09_000002_add_marketplace_message_email.php`.
   Do not backfill old messages. It was applied locally; production is not authorized.
3. Confirm the configured `BRAND_WEBSITE_URL`/APP_URL is the reachable first-party
   origin and transactional sender/mail transport is authorized. Never use request
   Host headers or a development hostname as the production mail destination origin.
4. Use the existing queue worker consuming `emails` (or ACCOUNT_EMAIL_QUEUE)
   and scheduler. Local queue is currently sync; that is after-commit inline work,
   not evidence of asynchronous production operation. Restart long-running workers
   and the SSR renderer after deploying the built bundle.
5. `php artisan marketplace:message-email-sweep` repairs missed queue dispatches for
   queued new alerts. This can send real email with a live mailer: run only in the
   authorized environment. It does not replay historical/accepted/failed/unknown mail.
6. Inspect EmailNotificationDelivery by stable notification UUID for support. Keep
   message bodies/addresses/private links out of exported diagnostics. An unknown
   send requires provider investigation before any separately authorized replay.
7. To disable alerts, set `MARKETPLACE_MESSAGE_EMAIL_ENABLED=false`, refresh config
   and restart workers. Creation/processing stops; messages and queued/accepted
   history remain. Read/paused/recipient checks apply again if later resumed. No
   schema down, deletion or financial-history rewrite is an operational rollback.
8. Keep all growth/indexation flags, exact-origin and owner-selected pilot gates as
   recorded in the [stage-E package](../../release/2026-11-08-marketplace-local-growth/README.md).
   Both enable and rollback must retain private noindex/no-store. No production
   authorization, Search Console connection or indexing claim follows from these tests.

## Weekly operator check

Check eligible owner/customer delivery destinations without publishing their email
addresses; review queued-age, skipped and unknown counts; verify scheduler/queue
health and one authorized synthetic exchange in each direction; compare saved
conversation messages with alert UUIDs; inspect representative desktop/mobile states
and supported inbox clients. Record actual acceptance/delivery separately. Use the
existing empty pilot reporting templates for acquisition outcomes; message alerts
are operational events, not evidence of incremental bookings/revenue.
