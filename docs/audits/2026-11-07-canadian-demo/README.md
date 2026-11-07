# Canadian pitch-demo setup verification

Scope: FR-01–06/15/18/19, owner-requested Canadian demo account and completed
Business setup. The change is contained in PitchDemoSeeder, its fixtures/tests
and documentation. Normal registration, production onboarding and commercial
rate cards are unchanged.

## Cause and repair

Version one populated business/catalogue/history and publication fields but did
not set `personalized_at` or `guided_completed_at`. The setup screen uses the
latter to select the four-decision introduction, so the existing demo incorrectly
looked new. Version two saves both timestamps, all four introduction steps and
real demo provenance. `demo_version` is separate from starter `version`: the UI
now shows **Your current setup**, rather than a claim that no client activity was
created. Canonical readiness must still pass.

## Fixture evidence

A separate local SQLite preview seeded on 2026-11-07 contains two Toronto
locations, ten bookable staff, fourteen logins, CAD, en-CA and America/Toronto.
It has 1,640 appointments from 2026-09-07 through 2027-01-07; both -04:00 and
-05:00 offsets are represented. The inspected run has 734 sales; exact completed
sales depend on the time of day. CAD catalogue examples include CA$25 beard care,
CA$40 barber cut and CA$65 signature cut. All people/addresses are fictional.

The browser signs into the isolated owner account using the supplied demo
password. Setup shows the readiness overview, all six essentials ready, two
locations/eight services/ten staff, **CA CAD America/Toronto** and no initial
introduction or false starter disclaimer. A private booking preview displays
Toronto addresses, +1 416 example phones and CAD service prices. It creates no
booking and does not follow the live public booking-host link.

Desktop 1440px and phone 390×844 screenshots were visually inspected. Document
scroll widths are 1440 and 390 respectively, with no horizontal overflow. The
viewport override was reset and the temporary browser tab closed afterward.

- [Desktop readiness](setup-desktop.jpg)
- [Phone readiness](setup-mobile.jpg)

## Backend checks

Local full scoped run: **4 passed / 955 assertions**. After the final manual-record
regression additions, the focused existing-demo upgrade run passes **1 test /
35 assertions**. Scoped Pint, PHP syntax and whitespace checks pass.

PitchDemoSeederTest covers authentication, branch-linked staff, images, actual
setup/readiness props, CAD and timezone, month-end-safe window coverage, both DST
offsets, staff overlap checks, financial arithmetic, canonical stock/commission/
receipt data, password restoration, isolation, collision rejection and rollback.
The legacy-upgrade test reproduces missing introduction completion and INR
financial records, checks that active generated legacy visits are cancelled with
history, adds a missing CAD visit, and proves issued sale/payment/receipt records
remain byte-for-byte unchanged. Replay does not duplicate the upgrade; an injected
late upgrade failure rolls region, lifecycle, metadata and new bookings back.
The same upgrade also leaves an independently added client phone and a manually
created appointment byte-for-byte unchanged. Provider calls and seed-generated
message jobs are excluded.

## Deployment and limits

The owner deploys and runs the same command for fresh creation or a one-time
version-one upgrade:

```bash
php artisan db:seed --class=PitchDemoSeeder --force
```

Existing live financial records keep their original currency. This is not a
foreign-exchange conversion or approval of paid CAD subscription pricing; the
manual demo subscription is complimentary. New activity uses CAD. The seeder
does not refresh its dates after the completed version-two upgrade. Passwords
remain Password@123 for the fourteen original, exclusively demo-owned accounts.

Local SQLite, browser and formatting evidence does not establish production
execution, MySQL race/load guarantees, payment delivery or regional tax compliance.
No live database or external provider was changed. The temporary preview used
its own database, storage and sessions under /tmp; application environment files
and the normal local database were not edited.
