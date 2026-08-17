# Prompt 05: Atomic availability and booking engine

Work in `/Applications/AMPPS/www/barber_app`.

Implement the authoritative availability, capacity hold, and booking commit
engine underlying FR-06 and FR-07. This prompt is domain-first: do not build the
full calendar or public booking experience yet.

Read root `AGENTS.md`, `docs/README.md`, `docs/project-status.md`,
`docs/architecture.md`, `docs/domain-model.md`,
`docs/modules/scheduling-and-operations.md`,
`docs/modules/business-configuration.md`, `docs/quality-and-testing.md`,
`docs/decisions.md`, FR-03 through FR-07, and PRD Section 7 booking scenarios.
Use the accepted production database strategy and current framework
documentation.

Implement one shared rule engine for:

- location hours, special hours, closure, appointment interval, notice window,
  and advance window;
- staff qualification, schedules, breaks, leave, temporary changes, location,
  and travel-impossible overlaps;
- service/add-on price, duration, active/processing/cleanup segments, and
  eligibility;
- resource types, quantities, hours, maintenance, and segment occupancy;
- existing appointments, personal blocks, and unexpired holds;
- preferred staff versus any qualified staff; and
- contiguous or valid segmented multi-provider visits.

Search results are advisory. Booking commit must revalidate every rule, claim
staff and resources atomically, and create all appointment segments or none.
Implement expiring capacity holds with stable idempotency keys and safe replay.
Return explainable domain errors without leaking private schedule information.

Use production-engine concurrency tests with genuinely parallel requests for
online-online, online-reception, staff-free/resource-busy, resource quantity,
multi-segment, expiry, duplicate request, and stale search cases. Cover daylight
saving changes, local midnight, closures, breaks, and processing segments.
Measure representative query performance and document required indexes.

Do not let controllers, Vue pages, Filament resources, or later drag-and-drop
operations write appointment timestamps directly. Expose tested use-case
commands and query contracts for future surfaces.

Update the scheduling module, domain model, architecture decisions, and project
status. End with invariants, command/query contracts, locking strategy, test and
performance evidence, and any known capacity limitations.

