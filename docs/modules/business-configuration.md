# Module: Business configuration

Status: Implemented and verified (2026-08-11)

Requirements: FR-02, FR-03, FR-04, and staff/schedule portions of FR-05.

## Purpose

Let a business describe where, when, by whom, and with which physical capacity a
service can be sold, then publish a valid booking page quickly. Booking search,
capacity claiming, confirmation, and the operational calendar remain Prompt
05/06 responsibilities; Prompt 07 now consumes them for public booking.

## Responsibilities

- guided, resumable onboarding with explicit launch blockers;
- business profile, booking slug/aliases, branding, locale, tax posture, and
  default rules;
- locations, normal hours, special hours, holidays, and closures;
- physical resources, active hours, maintenance blocks, and quantity needs;
- service categories, services, add-ons, segments, price/duration/tax/deposit
  configuration, visibility, and eligibility;
- staff profiles, qualifications, service variants, schedules, breaks, leave,
  temporary changes, locations, and invitations; and
- idempotent CSV import with templates, mapping, preview, errors, progress, and
  duplicate review; and
- versioned public controls for online enablement, Staff choice, exact/from
  price display, new-client rules, cancellation cutoff, waitlist batch size,
  and secure-link lifetime.

## Core invariants

1. All records have explicit Business lineage; Location/staff/service/resource
   relationships repeat and constrain that lineage.
2. A published Service has at least one valid staff, Location, schedule, and
   resource path.
3. Staff-specific price and duration resolve at a requested instant before
   availability display and must be resolved again before confirmation.
4. Add-ons stay explicit service lines and carry their own price, tax, time,
   skill, and resource rules.
5. Published hours, closure, or staff schedule changes require a fresh impact
   preview; affected appointments require resolution evidence.
6. Booking slugs are globally unique and every old slug remains a redirect.
7. Imports are tenant-scoped, idempotent, observable, and never auto-merge
   destructive duplicates.
8. Historical appointments consume immutable commercial/duration snapshots;
   future edits do not rewrite them.

## Implemented readiness rules

`ReadinessEvaluator` returns only `publishable`, ordered `blockers`, optional
`improvements`, and `next_step`; it never manufactures a percentage. Publishing
is blocked until all of these are true:

1. business type, country, locale, ISO currency, IANA time zone, week start,
   tax posture, public contact/address, cancellation text, terms/privacy links,
   appointment interval, and unique booking slug are present;
2. an active Location has normal opening hours;
3. active Staff is assigned to an eligible Location and has working hours;
4. an active online Service has an eligible Location, qualified visible Staff,
   and satisfiable physical-resource quantities; and
5. the owner reviewed the configuration-only mobile/desktop preview.

Logo, cover image, website, and import are improvements, not blockers. The
idempotent `GoodHoursDemoSeeder` publishes Pine & Palm Studio after a recorded
24-minute onboarding session and is the production-like Gate B fixture.

## Schema and relationship summary

- Business holds profile/rule values and current `booking_slug`;
  `booking_slug_aliases` keeps prior links.
- `onboarding_sessions` stores current/completed steps and actual start, save,
  preview, and publish timestamps.
- Location owns effective-dated `location_hours`, date-range exceptions, and
  `physical_resources`. Resources own weekly hours and UTC maintenance blocks
  with original local intent and IANA time zone.
- `services` includes services and add-ons. Categories, eligible Locations,
  active/processing/cleanup segments, add-on links, resource quantities, and
  effective-dated staff variants are relational records.
- `staff_availability_rules` represents split work, breaks, leave, holidays,
  sick leave, temporary changes, and personal blocks. Overlapping work,
  especially across Locations, is rejected.
- `configuration_snapshots` stores the complete resolved commercial/duration
  decision for a later Appointment Service Line and is never updated in place.
- `configuration_change_previews` stores proposed changes, exact future
  Appointment public IDs, count, expiry, status, and resolution evidence.
- Logo, cover, staff photo, and service image are tenant-private object keys;
  raw media is never written to the public disk.

## Import contract and evidence

CSV templates are available for clients, staff, services, and products.
`ConfigurationImportService::preview()` validates UTF-8, size, unique headers,
column shape, explicit mapping, row values, stable row keys, and SHA-256
fingerprints before any projection. Source and error CSVs use private tenant
storage.

`ProcessConfigurationImport` is a tenant-aware queued job. State progresses
through previewed, queued, processing, and completed; summaries retain total,
created, updated, skipped, failed, and duplicate counts. A Business-scoped
idempotency key plus source hash makes exact replay return the original job and
rejects key reuse for different content. Duplicate candidates remain in review
until create, update, or skip is selected. Staff imports re-check `staff.max`
in the worker. Staff and service rows project into configuration aggregates;
client and product rows remain durable tenant-owned import records until the
later CRM/inventory modules own merge and projection rules.

## Exact interfaces offered to the booking engine

Prompt 05 receives the read-only
`App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration`
contract:

- `locationWindows(Location, CarbonImmutable localDate): array` returns local
  open/close windows and source after closure/special-hours precedence;
- `staffWindows(StaffProfile, Location, CarbonImmutable localDate): array`
  resolves weekly/temporary work and subtracts breaks, leave, sickness, and
  personal blocks;
- `resourceWindows(PhysicalResource, CarbonImmutable localDate): array`
  returns resource-specific windows or explicitly inherits Location windows;
- `resourceMaintenance(PhysicalResource, CarbonImmutable fromUtc,
  CarbonImmutable untilUtc): array` returns overlapping UTC maintenance blocks;
- `resolveService(Service, StaffProfile, Location, ?CarbonImmutable at):
  EffectiveService` returns the effective Location/staff price, currency,
  active/processing/cleanup time, total bookable time, tax, deposit, and
  segment-occupancy snapshot; and
- `requiredCapacity(Service, list<Service> addons):
  list<CapacityRequirement>` returns resource identity, segment, required
  quantity, configured quantity, and configuration satisfiability.

Prompt 05 must implement
`AppointmentImpactSource::affectedAppointmentIds(Business, Model subject,
string changeType, array proposedChange): list<string>`. The default adapter
returns no appointments because this prompt intentionally creates no
Appointment store. Prompt 05 must use the same configuration contract for
search and confirmation and must own holds, atomic capacity claims, overlap
detection, stale-result revalidation, and commit idempotency.

## Verification evidence

`tests/Feature/BusinessConfiguration/ConfigurationFoundationTest.php` covers
15 scenarios and 82 assertions for readiness, the 24-minute demo, the guided
first-bookable-path interface, permissions, tenant isolation, slug aliases, DST/local exceptions, resource
maintenance, split/overlapping schedules, staff variants/snapshots, add-on
capacity, impacted-record previews and published-change gating, import
replay/malformed files/error export/duplicates, staff entitlements, and the
booking-facing contract. Full-suite and build evidence is in
`docs/project-status.md`.

## Open decisions

OPEN-02 still controls approved launch defaults and legal/tax copy, so
configuration requires explicit values instead of inventing them. Production
object-storage topology and expiring public renditions remain infrastructure
work; private tenant paths are enforced. Trademark clearance/domain acquisition
remain OPEN-11.
