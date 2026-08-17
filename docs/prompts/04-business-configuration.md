# Prompt 04: Onboarding and business configuration

Work in `/Applications/AMPPS/www/barber_app`.

Implement FR-02 through FR-05 configuration capabilities so a new tenant can
publish valid availability in one guided session. Do not build the booking
search/commit engine or operational calendar in this prompt.

Read root `AGENTS.md`, `docs/README.md`, `docs/project-status.md`,
`docs/domain-model.md`, `docs/architecture.md`,
`docs/modules/business-configuration.md`, `docs/quality-and-testing.md`,
`docs/decisions.md`, and FR-02 through FR-05 in the PRD. Respect the tenant,
access, entitlement, and audit foundations already verified.

Implement:

- resumable onboarding for business details, hours, services, staff, staff
  availability, booking rules, import, preview, and publish;
- readiness blockers and optional improvements rather than a vague percentage;
- business profile, locale, currency, time zone, week start, tax posture,
  cancellation links, branding assets, and booking slug with alias/redirect;
- location normal hours, special hours, holidays, temporary closures, and
  affected-appointment preview contracts;
- reusable physical resources, quantities, hours, and maintenance blocks;
- services, categories, add-ons, active/processing/cleanup segments, price,
  tax, deposit, notice, visibility, eligibility, qualification, and resource
  rules;
- staff profiles, invitations, weekly/split schedules, breaks, leave, temporary
  changes, locations, service assignments, and staff-specific variants; and
- idempotent CSV import for clients, staff, services, and products with mapping,
  validation preview, duplicate candidates, progress, summary, and error export.

Create domain resolvers for effective service price/duration and required
capacity. Store effective dates and historical snapshots where future edits
must not rewrite past appointments. Use private, tenant-scoped media storage.

Test tenant isolation, entitlements, role permissions, time-zone boundaries,
slug uniqueness/change, schedule exceptions, impacted-record previews, service
variants, resource quantity, import replay, malformed files, and duplicate
review. Provide a production-like demo tenant that meets the under-30-minute
readiness definition.

Update the configuration module, domain model, glossary, decisions, and project
status. End with the readiness rules, schema/relationship summary, import
evidence, tests, and the exact configuration interfaces offered to the booking
engine.

