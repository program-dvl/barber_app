# Business setup and operational readiness

Requirements: [FR-01–FR-05, FR-09, FR-13–FR-15, FR-19](../product-requirements.md).
Existing foundation: ADR-030/031/032/035/050/053. This specification restores the
affected onboarding boundary under OPEN-14; Phase 2 remains deferred.

## Lifecycle and starter records

Registration records an owner intent; verified email provisions one Business,
Owner membership, default Location, trial, starter roles and client-form templates
transactionally. Four resumable decisions personalize the workspace; public phone/address may be added before online publication. The existing
business-first transaction prepares type-specific categories, selected services,
regular location hours, one reused owner StaffProfile, working hours and service
assignments. Generated public IDs retain provenance; they are ordinary editable
tenant records. No fake clients, appointments, sales or payments are created.
Completed sessions and existing catalogues are never reseeded. Retry must preserve
customization and cannot duplicate initialization. Defaults are internally versioned.

## Readiness center

One backend projection powers the page and shared setup drawer. Operational
essentials are profile/region, active location/hours, valid active services, active
team, a qualified staff/service/location combination with overlapping usable hours,
and booking interval. Offline operation does not require online visibility,
publication, a logo, an additional employee or a notification/payment provider.
Readiness recomputes from current configuration, including dated assignments and
actual branch membership, rather than retained completion flags. Configured
readiness does not promise an unoccupied appointment slot.

Online publication retains public contacts, policies, slug, valid online delivery
paths and desktop/mobile review. Preparing starter records does not count as that
review and does not publish. Owners explicitly review and publish. Existing live
businesses stay live; new setup never publishes implicitly. Optional branding,
imports, notification channels and online payments never reduce essential progress.

## Editing and access

The overview recommends one incomplete essential and exposes direct navigation.
Starter provenance, prices, duration and owner identity are explicit. Compact
service review reuses the reviewed catalogue command, revisions, hold protection,
reasoned future impact and exact replay. Deactivation retains history. Weekly hours
allow different days and split periods, explicit save and copy actions. Established
schedule changes retain impact review. Staff assignment/availability uses the Team
domain; onboarding must not introduce a competing permission or schedule engine.

Settings authority is enforced server-side. Business-wide settings require an
Owner or settings-capable member assigned to every business location. Branch-only
managers retain their scoped Services/Team workspaces. Tenant binding and explicit
business IDs apply to every read and command. Meaningful applied configuration
changes produce existing Activity events; navigation and review clicks do not.

## UX and evidence

Setup remains accessible after completion. Required and optional work are separated;
online states distinguish not published, ready to publish, paused and live. Profile
groups use progressive disclosure, explicit save and dirty-navigation protection.
Local preview is labeled; it never fabricates public availability or payment
connection status. Cash/manual checkout can work without connecting Stripe;
subscription billing is a separate context. SMS/email transport state comes from
existing transport configuration and simulation state. “Sending configured” is not a live delivery certificate. Unsupported mobile
channels and Phase 2 memberships/packages are absent.

Verification and six-perspective review live in the [dated Business Setup audit](../audits/2026-10-04-business-setup/README.md).
Browser evidence, backend isolation/rollback/replay tests and production builds
must be recorded before the project-status document claims verification.
