# Good Hours use-case and problem-page matrix

Status: Accepted Prompt 19 implementation record (2026-08-16)

The four published pages start with an operational problem, offer useful
process guidance that stands without Good Hours, and then identify the exact
verified product workflow and its limits.

| Candidate | Intent / unique answer | Evidence | Overlap disposition | Route |
| --- | --- | --- | --- | --- |
| Reduce scheduling conflicts | Use one constraint model and commit-time revalidation | FR-03–FR-07 | Distinct diagnostic/process answer; product depth remains on calendar feature | `/use-cases/reduce-scheduling-conflicts` |
| Manage walk-ins and appointments | Keep queue evidence separate until capacity-valid conversion | FR-06, FR-08 | Distinct mixed-demand problem; barbershop page owns audience fit | `/use-cases/manage-walk-ins-and-appointments` |
| Protect time with deposits | Make policy visible, versioned and traceable to checkout | FR-09, FR-10, FR-14 | Consolidates no-show/reminder intent without claiming outcomes | `/use-cases/protect-time-with-deposits` |
| Keep client history together | Collect purposefully, protect sensitive context, merge conservatively | FR-11–FR-14, FR-19 | Distinct operating guidance; client feature owns product depth | `/use-cases/keep-client-history-together` |
| Online booking | Product/module intent already answered by feature page | FR-06, FR-07, FR-09 | Consolidated into `/features/online-booking` | — |
| Staff/resource scheduling | Product/module intent already answered by calendar feature | FR-03–FR-07 | Consolidated into `/features/calendar-and-walk-ins` | — |
| Payment management | Broader commerce intent already answered by checkout feature | FR-14–FR-18 | Consolidated into `/features/checkout-and-reporting` | — |
| Appointment reminders | Provider-dependent supporting behavior, not enough unique standalone guidance | FR-10 | Consolidated into booking/client content | — |
| Leave paper/spreadsheets/switch software | Requires verified import/export scope and a reviewed migration guide | FR-13, FR-18 | Deferred; no migration-service promise | — |

## Evidence and claim controls

All product statements derive from the accepted module specifications and
verified Prompt 01–13 implementation evidence. Examples are generic and
synthetic; no external statistics or customer claims are used. Deposit copy
states the Stripe certification blocker, client-history copy states OPEN-10,
and scheduling copy uses “reduce/help” rather than guaranteeing an outcome.

Each page has a direct answer, three symptoms, four practical steps, four
product-specific steps, two adjacent links, explicit requirement IDs and two
visible limitations. The pages are eligible for WebPage and BreadcrumbList in
Prompt 23; they intentionally do not claim HowTo/FAQ rich-result eligibility.
