# Reports review and implementation evidence

Date: 2026-10-04. Scope: FR-18, with FR-14–17 and FR-19 source integrity.

## Delivery state

The intended project is `/Applications/AMPPS/www/barber_app`, serving the user's
application on port 8000. The prepared Reports patch has been applied directly to
that existing checkout, preserving its concurrent changes. The isolated
`work/barber_app` copy was used for preparation and synthetic review only; it was
not selected by the user as the intended project. Original-project checks and
browser verification are recorded below. No environment or
synthetic database was copied, and no review seeder was run in the original.

The patch contains only Reports changes and related private-file streaming
support, tests and documentation. Existing AppLayout and route changes are
preserved; Reports reuses their navigation, named endpoints and shared controls.

The source report screen, operational services, permissions, financial ledgers, Calendar availability, shared components and accepted decisions were inspected before implementation. The original screen was a report selector, manual date form and broad detail table; the resulting workspace has an analytical overview, scoped comparisons and investigation paths. Before/after screenshots are supplied in the delivery evidence folder. All populated screenshots and the sample export use an isolated synthetic business; they are not evidence of actual shop performance.

## Findings and changes

| Finding | Implemented outcome |
| --- | --- |
| Flat report selection and no historical overview | Searchable grouped directory, Business overview default and recent reports scoped to the current user/business session |
| Manual date editing and no meaningful comparison | Local-calendar presets, explicit custom dates, prior equal-length period or prior year, exact comparison dates and neutral absolute/percentage changes |
| Wide tables and limited investigation | Compact default columns, optional columns, SQL search/sort/pagination, record drawer, source links and date/service/team drilldowns |
| Ambiguous money and return bases | Separate sale-cohort receipts from transaction-date payment activity; frozen sale-line prices, explicit tax/return caveats, one recorded currency and currency-specific precision |
| Totals could depend on visible detail limits | Complete filtered SQL aggregates independent of the 30-row page; weighted rates and average sale value computed on the server |
| Limited charts and management context | Adaptive hourly/daily/weekly/monthly trend, ranked breakdown and weekday pattern; deterministic volume/value observations without invented causes |
| Crude capacity risk | Shared Calendar bookable windows, breaks, leave, closures, cross-branch occupancy and unioned service segments; explicit retained-configuration limitation |
| Historical/catalogue and client-definition risk | Immutable line labels/prices/performers; first completed sale across accessible branches/currencies for client classification; distinct client counts |
| Export/print scale and scope risk | Chunked private CSV streaming, meaningful filenames, duplicate active request reuse, progress feedback, CSV formula-injection protection, authorization rechecked at generation and download |
| Scope leakage risk | Independent own-calendar and own-compensation scopes, permitted locations, finance gates and protected cancellation reasons; contacts omitted |
| Small-screen/long-name defects | Stacked analysis and compact record cards, 44px mobile controls, fixed long ranking-name overflow and keyboard/touch chart points |

## Analytical contracts

- Sale-period net receipts = recorded paid amount + deposits applied − all recorded refunds on the selected sales. This preserves Dashboard/Checkout's completion-cohort basis. Optional open-sale filters use creation time and expose recorded outstanding balances.
- Payment activity uses successful payment/refund/void occurrence dates, including partial sales. Deposit collection is excluded; these totals are not interchangeable with sale-period receipts.
- Line sales after discount = frozen quantity × frozen unit price − frozen discount. Stored inclusive/exclusive tax basis is retained. Item returns use recorded allocations; unallocated returns are never assigned to a service or performer.
- Average sale value = frozen subtotal less discount divided by matching sale count, before returns and tips. It is not net receipts divided by sales.
- Original sale tax and tips are shown separately. Tax-return allocations are absent, so the report does not invent net tax or statutory filing figures. Tip and commission statements use append-only ledgers and their existing permissions.
- New/returning clients are distinct identified paying clients. Prior history is the first completed sale within accessible branches across recorded currencies; selected-currency money and visits are scoped separately. This is not appointment retention or a cohort conversion funnel.
- Utilisation = unioned occupied service minutes inside configured bookable windows ÷ configured bookable minutes. Overlapping appointments count once; cancellations, no-shows and rescheduled originals do not occupy capacity. Breaks, leave, closures and blocks reduce availability. Raw recorded occupied time and time outside windows remain visible.
- Cancellation and no-show denominators are documented beside the metric. Empty denominators are unavailable; a valid empty monetary/count bucket is zero. Missing prior comparison buckets remain unavailable, including unmatched repeated daylight-saving hours.

Dates use strict location-local calendar boundaries converted to half-open UTC ranges. Each comparison recomputes its own boundaries. Reports permit at most 367 calendar days. A report combining different branch time zones must choose a branch rather than silently mix local calendars. Monetary reports never sum unlike currencies.

## Preparation verification (isolated copy)

| Check | Verified result |
| --- | --- |
| Full backend suite | 458 passed, 28 existing skips, 4,962 assertions; 38.02 seconds on local SQLite |
| New Reports feature coverage | 18 passed, 125 assertions; final rerun after making optional performance evidence output explicit |
| Frontend suite | 52 passed, zero failures/skips |
| Production client and server build | Passed using the supported installed Node 24 runtime; no dependencies added |
| Larger sales fixture | 5,001 sales; correct complete totals, 30 details, 10 SQL queries, 20,041-byte response; measured local query/projection time about 10ms |
| Filtered export reconciliation | Native browser CSV download: 29 rows for Amelia, line sales after discount 58,170 INR, matching report/drawer scope |
| Browser pagination | September overview page 2 shows records 31–60 of 212; headline totals remain unchanged |
| Browser source navigation | Team → frozen lines preserves dates/location/staff/search; trend date → sale records preserves the selected date; Open source loads the original completed sale and payment history |
| Comparison boundary | September 1–30 compares with August 2–31 for equal-length prior period; zero comparison base displays absolute change without infinity |
| Native print | Dedicated print route loads as HTML, without application navigation or an Inertia error modal; 100 of 212 records with complete filtered totals and an explicit cap |
| Desktop/tablet/mobile | Inspected at 1440×1000, 768×1024, 390×844 and 360×800; final mobile document width matches viewport, with long staff/service names |
| Keyboard/empty states | Chart Enter opens dated records; Escape closes detail dialog; no-match search recovers with Clear filters; no-comparison hides changes |

The new tests cover complete aggregation vs pagination, exact export scope, refunds and partial payments, frozen tax/deposit/discount values, archived performers/services, DST boundaries and comparison alignment, zero data, mixed currencies including JPY/KWD precision, distinct client classification, cancellation denominators, missing wait measurements, capacity overlaps/breaks/leave, location restrictions, own-only role scope and authorization loss between export request/generation/download. Existing full-suite skips remain skips; target-database concurrency coverage is not implied by SQLite results.

The larger-fixture check demonstrates bounded detail/response/query count for this sales projection, not production load capacity for every report. Calendar capacity reads appointments and blocks in batches and merges intervals, but should still be profiled on the target database with representative staff/date/branch volumes.

## Final review from six perspectives

| Perspective | Assessment and implemented refinement |
| --- | --- |
| Salon owner | Four overview metrics distinguish receipts, sales volume, average value and discounts. Period trend and service ranking lead into supporting records. Metric definitions prevent a tax-inclusive amount being mistaken for tax-exclusive revenue. |
| Manager | Booking outcomes, recorded walk-in waits, weekday patterns and capacity expose operational facts. Staff capacity includes raw time outside configured windows, so an overbooked or changed schedule is visible without a personal performance score. |
| Finance | Sale-date receipts, transaction-date collections, refunds, original tax, item-return allocation and compensation ledgers keep their separate bases. Full totals, CSV scope and printed scope are explicit and reconcile. |
| Multi-location owner | Same-zone permitted branches can be compared in one recorded currency; branch selection preserves date/filter context. Cross-zone selection is rejected, and a branch is selected by default where needed. |
| Product designer | Shared typography, surfaces, dialogs and controls match the redesigned product. Compact metrics, restrained current/previous series, adaptive labels and small default column sets keep analytical hierarchy clear; long mobile labels and print navigation were corrected during QA. |
| Engineer | Shared scope feeds SQL aggregates, details and chunked exports; fixed filters/order whitelists, stable pagination, coherent read transactions and authorization rechecks protect correctness. Capacity batches its interval inputs. No chart dependency or stale financial cache was introduced. |

## Deliberate limits

Memberships, packages, gift cards/credit liabilities and recurring revenue have no approved Phase 1 transaction model; their reporting is deferred. Inventory/retail navigation remains hidden under ADR-036; existing immutable retail lines remain supported internally. Saved views and scheduled report delivery were not added without an existing reviewed product contract.

Capacity uses retained schedule configuration, not a historical attendance or schedule-snapshot ledger. Historical configuration changes therefore cannot establish actual past attendance, and prior-period utilisation comparison is disabled. Tax-return allocations and deposit collection reconciliation are not invented. Failed payment attempts are not shown as cash received. Client “retention,” lifetime spend and rebooking conversion are not claimed.

The browser evidence comes from the local in-app browser. A formal independent WCAG audit and Safari/Firefox/Edge matrix were not run. Production queue/storage topology, replica isolation, sustained load and deployment were not exercised. Local original-project MySQL checks are recorded separately below. The existing wider project launch gates remain unchanged.

See [Reports specification](../../modules/reports.md) and ADR-051. The delivery README provides patch checking/application, migration, build and validation instructions. Do not run the synthetic review seeder against a real business database.

## Original-project verification — 2026-10-04

Implemented directly in `/Applications/AMPPS/www/barber_app` and verified against
Pine & Palm Studio at `http://127.0.0.1:8000/businesses/01KZS4HVNQZXX1CFZACZZBXR6R/app/reports`.

- Full backend suite: **469 passed, 28 existing skips, 5,054 assertions**;
  39.78 seconds, isolated SQLite `:memory:` with fake communications.
- Focused Reports/management/shell regression: **41 passed, 405 assertions**,
  including all 18 new Reports feature tests.
- Frontend helpers: **56 passed**, including four Reports tests.
- Production client and SSR builds pass using installed **Node 24.19.0**.
  Existing CSS `@property` and unresolved font-path build warnings remain.
  All 17 front-site asset budgets, scoped PHP formatting and whitespace checks pass.
- Application caches cleared. Only
  `2026_10_04_000001_add_report_activity_indexes` was migrated; all three indexes
  verified. No other migration, seeder, environment replacement or database copy
  was run.
- Local MySQL: all **22 visible owner report projections** passed in a database
  transaction that prohibited writes and was rolled back; no ancillary chart
  errors. [Read-only check results](original-verification/mysql-checks.json).
- Chrome with the existing sign-in confirmed the new default Business overview,
  Sales detail navigation with retained dates/currency/comparison, charts,
  complete filtered totals, underlying sale records and record detail/source link.
  At 1512px, document width equals viewport width; no captured console warning/error.
  Existing real records were read only. CSV/print generation in the real business
  was not repeated; those flows retain automated and isolated synthetic evidence.
- Pre-application hashes matched every patch target; immediately after application,
  all 26 delivered file hashes matched and no existing file outside the patch changed.
  Later concurrent Communications edits remain intact. `.env` hash remains unchanged;
  AppLayout and routes were not replaced. Final production assets were rebuilt after
  those concurrent edits were observed.

Original-app screenshots: [overview](original-verification/01-overview.jpg),
[Sales detail](original-verification/02-sales.jpg),
[underlying records](original-verification/03-sales-records.jpg).
The record drawer was also verified through the live accessibility state.
These screenshots show port 8000, not the isolated preview. Wider production
queue/storage/load and independent accessibility gates remain unchanged.
