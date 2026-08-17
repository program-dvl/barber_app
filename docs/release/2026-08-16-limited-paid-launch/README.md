# Limited paid launch readiness record

Release candidate: Phase 1 / Prompt 13  
Evidence date: 2026-08-16  
Evidence executor: Codex in the local Good Hours workspace  
Target market: India; `en-IN`; INR appointment commerce; USD Good Hours subscription catalogue  
Recommendation: **NO-GO for a limited paid launch or general availability**

This is an evidence ledger, not a claim that code inspection equals release
certification. Local automation, a dedicated MySQL 8/InnoDB database, an
isolated production-mode browser server, and a local restore rehearsal passed.
The actual production database/queue/object-storage topology, live payment and
messaging providers, supported-browser matrix, external penetration test,
automated backup service, monitoring/alert delivery, and Indian legal/accounting
review were not available. Their checkboxes remain open.

## Scope and deferrals

All PRD P0 capabilities and the Phase 1 P1 capabilities already promoted into
this release train are included: waitlist (FR-10), forms/consent (FR-12),
inventory (FR-16), and commissions (FR-17). None is silently deferred.
PRD Section 8 remains the valid Phase 2 deferral list. Compatibility is P1 in
Section 12, but the user made supported-browser verification an explicit launch
task, so missing Safari/Edge/Firefox results are a blocker rather than a
deferral.

OPEN-09 is retained: legacy Larafast cleanup is not authorized and the legacy
surfaces remain quarantined. OPEN-10 is retained and blocks destructive privacy
processing and paid launch. OPEN-11 is retained and blocks public identity,
production senders, and public launch. No risk waiver was approved.

## What changed in hardening

- Updated the PHP and frontend dependency locks from 45 Composer advisories and
  20 npm advisories (2 critical, 15 high) to zero advisories in fresh registry
  checks.
- Set the frontend build baseline to Node 22.12 or later and verified with Node
  24.19.0.
- Added per-request UUID correlation, tenant-safe structured log context,
  baseline browser security headers, HSTS on secure production requests, and
  no-store handling for authenticated and token-protected responses.
- Fixed the production-like demo tenant so it always owns a dated trial and its
  subscription journey does not return 404.
- Restored the Checkout & Sales page to the authenticated application shell and
  a meaningful document title.
- Made the metric-catalogue assertion stable across the upgraded test framework
  without weakening the expected catalogue.

### Follow-up remediation: inline Paddle checkout and inventory drift

Date: 2026-08-16 (Asia/Kolkata). Owner: Engineering/Codex execution; accountable
human owner remains unassigned.

- Restored the missing local MySQL `inventory_levels` table with an idempotent,
  non-destructive repair migration. The original migration was recorded as run
  while this single table was absent; the other nine tables from that migration
  were present. Post-migration query: table present, zero rows, no data removed.
- Replaced the external Paddle checkout redirect with a premium application-owned
  review page and Paddle.js inline payment frame. Popup/overlay checkout is not
  used; a browser event alone never creates paid state.
- Added `application=good_hours` metadata and a pre-persistence filter for the
  shared Paddle account so events explicitly belonging to the second SaaS are
  acknowledged without storing its payload.
- `billing:sync-paddle-catalog --dry-run` resolved both Good Hours products and
  all four active prices in the configured sandbox account without writes.
- Focused evidence: subscription lifecycle 30 tests / 221 assertions; inventory,
  commission, and reporting 8 tests / 93 assertions; client and SSR production
  builds passed. A read-only Paddle Sandbox API inspection found three
  Good Hours-marked transactions: completed USD 100.00 with a subscription,
  completed USD 50.00 with a subscription, and ready USD 50.00 without a
  subscription. The USD 100.00 result corresponds to the operator-reported
  checkout. Local MySQL still had zero Paddle provider events and the demo
  Business remained trialing at that checkpoint, proving the localhost webhook
  destination was not reachable. UI payment completion and provider payment are
  evidenced, but
  signed-webhook provisioning remained open at that checkpoint and was not
  inferred from either.

### Follow-up remediation: checkout totals, completion, and shared branding

- Removed the duplicated legacy Starter/Pro card grid so plan selection appears
  once, followed by one application-owned review and inline payment step.
- Paddle.js checkout event totals are decimal major-unit values; the summary now
  formats them directly while locally stored catalogue prices remain formatted
  from integer minor units. The reported Pro checkout now presents USD 100.00,
  matching Paddle's USD 100.00 transaction.
- Removed the automatic success redirect that hid the completion result. The
  inline page now replaces the payment frame with an explicit successful
  Sandbox/payment result and a return-to-billing action; the billing page also
  shows an allow-listed progress notice. Neither surface activates access.
- Paddle's customer-visible `QRxpress` text is account-wide seller identity in
  its hosted checkout, not repository copy. Paddle documents that legal/display
  name changes require seller-account administration. A neutral Stylnexa name
  would affect both SaaS products in this shared account, so no external account
  mutation was made without the owner's explicit account-wide approval.
- Added an owner-only plan/status badge to the application header and a visible
  subscription-controls section. Provider-request tests prove immediate Paddle
  proration for upgrades, renewal treatment for same-interval downgrades,
  period-end cancellation semantics, and correct removal of Paddle's scheduled
  cancellation. Staff without billing permission do not receive these shared
  subscription details.
- Paddle plan-change, cancellation, and reactivation failures are converted to
  a safe retry response before any local lifecycle record changes. Duplicate or
  status-invalid reactivation requests are rejected before contacting Paddle.

### Follow-up remediation: completed-checkout reconciliation

- Root cause: Paddle completed the payment, but localhost received no webhook;
  no durable checkout attempt or server-side recovery path existed. The blank
  payment area was the hidden inline frame leaving an empty card after the
  browser completion event.
- Added tenant-scoped checkout attempts, background status polling, a populated
  processing state, and a professional in-place success result. Refreshing the
  checkout or Billing page now resumes confirmation instead of returning to an
  unexplained trial state.
- Confirmation retrieves the transaction and subscription with the Paddle API
  key and validates customer, Business public ID, `application=good_hours`,
  local price, provider price, currency, and active subscription before any
  plan or financial record changes. Pending and cross-tenant attempts cannot
  activate access.
- Reconciled the actual Pine & Palm Studio Sandbox purchase on 2026-08-16:
  Good Hours Pro active, USD 100.00 invoice `72804-10140` completed/paid, one
  payment, Visa ending 4242, and the latest Paddle subscription linked. A later
  signed event is proven idempotent and does not duplicate the invoice/payment.
- The authenticated API path makes localhost Sandbox confirmation functional;
  a public signed webhook destination remains a mandatory live-launch control.

## Verified evidence summary

| Gate | Result | Exact evidence |
| --- | --- | --- |
| Repository suite | Pass | 201 passed, 1,450 assertions, 28 intentional skips, 17.27 s |
| MySQL concurrency/performance | Pass for bounded local exercise | 12 passed, 48 assertions, 122.96 s; search 212.75 ms/786 queries; commit 20.79 ms/63 queries; calendar 32 events in 9.52 ms/9 queries |
| Production builds | Pass | Vite client and SSR builds complete on Node 24.19.0 |
| Formatting | Pass | Pint passes after the final changes |
| Dependency advisories | Pass at 2026-08-16 lookup | Composer: 0; npm: 0 |
| Browser smoke | Partial | In-app Chromium at 1280×720 and 390×844; public booking through held-slot details and authenticated core screens; zero horizontal overflow in sampled views |
| Restore rehearsal | Pass locally | `barber_app_launch_test` dump restored to new `barber_app_restore_test`; 1/1 businesses, 8/8 appointments, 0/0 sales, 45/45 migrations; dump SHA-256 `77a80be56bea918eb7ad2d66a2e583ece5979fd4d3b4ec0975c3a53ac9617c49` |
| Paddle Sandbox payment | Pass with API recovery; webhook gate still open | Provider API and local database: Pro active, USD 100.00 invoice paid, one payment, Visa ending 4242; localhost signed webhook not received |
| Live provider certification | Not run | No live Paddle, Stripe, Resend, or Twilio credentials/endpoints used |
| Production topology load/DR | Not run | Local MySQL, synchronous queue, local files; no production queue/storage/failover target |
| Legal/accounting/privacy sign-off | Missing | Named counsel, accountant, privacy reviewer, and accountable launch owner are unassigned |

The detailed Section 7 and Section 17 ledgers are in
[`validation-matrix.md`](validation-matrix.md) and
[`chargeability.md`](chargeability.md). Security and tenant isolation are in
[`security-and-isolation.md`](security-and-isolation.md). Operations, metrics,
recovery, rollback, and sign-offs are in
[`operations-and-signoff.md`](operations-and-signoff.md).

## Blockers

### Critical

1. No live Paddle subscription checkout/webhook/invoice/renewal-failure proof
   and no live Stripe appointment payment/refund/settlement proof. Owner:
   Product/Finance/Engineering, named human unassigned.
2. OPEN-10 has no approved India retention/anonymisation schedule; destructive
   privacy requests cannot complete. Owner: Legal/Privacy, named human
   unassigned.
3. No India tax/GST, receipt, consent, privacy, retention, marketing, payment,
   or messaging legal/accounting sign-off. Owner: Legal/Finance, named human
   unassigned.
4. No production backup service, measured RPO/RTO, failover/DR exercise, or
   deployment rollback exercise. Owner: Operations, named human unassigned.

### High

1. No external penetration test and uploaded client files have MIME/size/private
   storage controls but no real malware scanner; the current service records
   them as clean. Owner: Security/Engineering, named human unassigned.
2. No production-topology peak/load evidence for availability, booking commit,
   calendar, checkout, or webhook bursts. The single search already uses 786
   queries against an 800-query guard. Owner: Engineering/Operations, named
   human unassigned.
3. No production monitoring vendor, alert delivery test, dashboard ownership,
   queue worker topology, or verified status page. Owner: Operations, named
   human unassigned.
4. No Resend domain/deliverability or Twilio WhatsApp sender/template/callback
   certification. OPEN-11 also blocks sender identity. Owner:
   Product/Operations, named human unassigned.
5. WCAG 2.2 AA was not independently audited, and Safari/Edge/Firefox current
   and previous major versions were not run. Owner: Design/QA, named human
   unassigned.

### Medium

1. OPEN-09 leaves non-product Larafast surfaces and tables quarantined but not
   removed. Owner: Product/Engineering.
2. Session secure-cookie and production secret-manager values are environment
   dependent and were not inspected in a deployed environment. Owner:
   Security/Operations.
3. The release has no real-shop metric cohort, so Section 15 commercial and
   reliability targets have definitions but no production baseline.

## Waived risks

None. A waiver is valid only with a named accountable human, scope, rationale,
compensating control, approval date, and expiry date. No such approval was
available, so blockers were not relabelled as waivers.
