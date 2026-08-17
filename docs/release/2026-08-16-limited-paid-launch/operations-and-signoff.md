# Operations, recovery, metrics, and sign-off

## Commands and environments

| Exercise | Environment | Command/evidence | Result |
| --- | --- | --- | --- |
| Complete regression | PHP 8.4.8; SQLite `:memory:`; array cache/session; sync queue | `php artisan test` | 201 pass / 1,450 assertions / 28 intentional skips / 17.27 s |
| Production-engine races | Local MySQL 8/InnoDB; dedicated `barber_app_booking_test`; `pcntl` parallel workers | `BOOKING_MYSQL_INTEGRATION=1 BOOKING_MYSQL_DATABASE=barber_app_booking_test php artisan test tests/Feature/SchedulingOperations/MySqlBookingConcurrencyTest.php --compact` | 12 pass / 48 assertions |
| Frontend production build | Node 24.19.0 | `npm run build` | Client and SSR pass |
| Dependency security | Packagist/npm registries, 2026-08-16 | `composer audit`; `npm audit --audit-level=moderate` | 0 / 0 advisories |
| Browser | Local server with `APP_ENV=production`, `APP_DEBUG=false`, isolated `barber_app_launch_test`; in-app Chromium | public and authenticated route exercise at 1280×720 and 390×844 | Partial pass; other browser families not run |
| Migration/import rehearsal | Fresh `barber_app_launch_test` | `php artisan migrate:fresh --seed --force`, then demo/front-desk seeder | 45 migrations; representative Business and eight Appointments |
| Restore | Local MySQL; new `barber_app_restore_test` | single-transaction dump and restore; count comparison and SHA-256 | Local restore passes |

The local server used synchronous queues and local storage. It is deliberately
not described as the production topology.

## Load/performance evidence

| Surface | Observed local sample | PRD interpretation |
| --- | --- | --- |
| Availability search | 212.75 ms, 786 SQL statements, 20 returned slots | Under 500 ms local guard but within 14 queries of limit; optimization and peak load required |
| Booking commit | 20.79 ms, 63 SQL statements | Under 2 s local guard; parallel atomicity passed |
| Calendar | 32 events, 9.52 ms, 9 SQL statements | Under 500 ms local guard |
| Checkout | Functional/service tests and browser screen only | No concurrent/peak load result; blocked |
| Webhook bursts | Duplicate/out-of-order contract tests only | No production queue/provider burst result; blocked |

No p75/p95 under target concurrency, saturation point, queue lag, database CPU,
lock-wait distribution, failure rate, or webhook throughput was collected on a
production-equivalent topology. Section 12 performance is therefore partial.

## Observability and diagnostics

Implemented locally: request/job correlation, immutable audit IDs,
queue/failed-job counts, communication failure/age counts, billing and payment
webhook failure counts, reconciliation exceptions, safe replay, support access
alerts, and content-minimised diagnostics. The health response honestly reports
backup `not_configured`.

Missing: production structured log sink, error monitor configuration,
trace/metric backend, dashboards, alert routes and paging test, queue-worker
SLO, provider synthetic checks, backup-age source, on-call roster, and external
status page. These are blockers; repository health screens are not substitutes.

## Backup, restore, RPO/RTO, disaster recovery, rollback

The local dump/restore completed in the 8.7-second command window and matched
key source/restored counts: Business 1/1, Appointments 8/8, Sales 0/0,
Migrations 45/45. The dump is synthetic and stored at
`/tmp/good_hours_launch_2026-08-16.sql`; it is not a production backup.

Target RPO ≤24 h and RTO ≤4 h remain unproven because no automated backup
schedule, encrypted off-site retention, point-in-time recovery, regional
failure exercise, DNS/queue/object-store recovery, or deployment rollback was
available. Production exercise must restore into an isolated environment,
validate counts/hashes and signed provider reconciliation, run smoke tests, and
record start/end time and incident commander before sign-off.

## Metric baselines

The versioned `MetricCatalog` definitions and privacy-safe instrumentation
catalogue are automated. Local baselines are limited to:

- conflict leakage: 0 in the executed MySQL race set;
- payment reconciliation exceptions: 0 in the clean synthetic fixture;
- demo first-bookable setup: recorded fixture duration 24 minutes;
- sampled public availability: 32 slots for the chosen date; and
- build/test reliability: all executed final checks pass.

Qualified trial starts, activation rate, trial-to-paid conversion, online
completion, notification delivery, no-show trend, utilisation, logo retention,
weekly paid usage, and setup-contact rate have no representative production
cohort. Their PRD targets remain hypotheses, not baselines.

## Rollback/traffic-stop criteria

These are proposed exact safety thresholds; they still require a named launch
owner and working monitors:

1. Immediate traffic stop for any confirmed cross-tenant access, secret/card
   exposure, duplicate charge, lost/rewritten financial evidence, or booking
   conflict leakage above zero.
2. Disable the affected provider path for any signature-verification bypass,
   payment mismatch that remains after one safe replay, or webhook processing
   failure above 1% for five consecutive minutes.
3. Roll back the application release if booking/checkout HTTP 5xx exceeds 1%
   for five minutes, booking commit p95 exceeds 2 s for 15 minutes, or typical
   authenticated p75 exceeds 2.5 s for 15 minutes after capacity is confirmed.
4. Stop new writes if oldest critical queue work exceeds five minutes, database
   lock/deadlock behavior no longer converges within configured retries, or a
   restore verification fails.
5. Roll forward only with a tested fix when rollback would violate a forward-only
   migration or append-only financial invariant; otherwise restore the prior
   artifact/config and reconcile all provider events received during the window.

## Compliance and accountable sign-offs

| Review | Accountable reviewer | Status | Required evidence |
| --- | --- | --- | --- |
| India tax/GST and receipts | **Unassigned named accountant/tax adviser** | BLOCKED | Tenant GST posture/rate, invoice/receipt fields, credit/refund treatment |
| Privacy/consent/retention | **Unassigned named Indian privacy counsel/DPO** | BLOCKED | OPEN-10 schedule, anonymisation authority, DSAR SLA, processor terms |
| Payments/subscription | **Unassigned named Finance owner** | BLOCKED | Paddle/Stripe live certification, fees, settlements, refund/cancellation wording |
| Messaging | **Unassigned named Legal/Operations owner** | BLOCKED | Resend domain, Twilio sender/templates, opt-in/marketing wording, unsubscribe |
| Security | **Unassigned named Security owner** | BLOCKED | external penetration test, upload scanning, production secrets/session review |
| Reliability/DR | **Unassigned named Operations owner** | BLOCKED | production load, backup/PITR/restore, alerts/on-call/status page |
| Accessibility/browser | **Unassigned named QA/Design reviewer** | BLOCKED | WCAG audit and required browser/version matrix |
| Product launch | **Unassigned named Product owner** | BLOCKED | Section 17 all pass plus acceptance of metrics and rollback criteria |

No person was named in repository context, so names and signatures are not
fabricated. Approval requires name, role, date, evidence link, decision, and
expiry for any waiver.

## Support, incident communication, and status page

The implemented playbooks are `docs/support/communications.md`,
`docs/support/payment-recovery.md`, and `docs/support/platform-operations.md`.
The incident/status templates added in this release are not proof of an
operational external status service. Import templates and automated preview/
commit errors exist; export CSV/print and business export tests inspect scope,
hashes, and reconciliation, but a real-shop migration remains required.
