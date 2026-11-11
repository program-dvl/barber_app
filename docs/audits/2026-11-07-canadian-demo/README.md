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


## Production verification after Forge command errors — 2026-11-07

The owner deployed source `8de93f1` (Forge deployment `82379805`) and invoked the
seeder. Forge records `14014800` and `14014850` as failed, with only the initial
“Seeding database” output and a tunnel exit of -1. That output alone does not
establish that the transaction rolled back.

A separate read-only Forge diagnostic (`14015006`) confirms PHP 8.5.12, a 512M CLI
memory limit, max_execution_time=0 and the saved `pitch-demo` business (id 4), CAD,
a `demo.pitch.seeded` completion record and **1,640 appointments**. No seeder
rerun was performed during diagnosis. The exact reason for Forge's session error
was not established; do not describe an application exception or a memory limit
as the proven cause. The Commands UI advertises a short execution limit (two
minutes in the inspected panel); current public
[Forge Commands documentation](https://laravel.com/forge/docs/sites/commands)
states five minutes. Either is separate from PHP's CLI execution limit.

The existing owner account was then tested on the deployed application in an
isolated browser session, preserving the user's Chrome sign-in. Normal login
with pitch.owner@example.test / the owner-selected demo password succeeds. The
live dashboard shows Atelier Studio · Demo, Queen Street Studio and today's
seeded appointments. Business setup shows **6 of 6 essentials ready**,
**CA CAD America/Toronto**, two locations, eight services, ten team members and
**Your current setup**, with no introduction screen.

- [Verified live setup screenshot](live-setup.jpg)
- [Live demo dashboard](https://clipperdesk.com/businesses/01M6VGGD3E12JWM53Y54N76VC3/dashboard)
- [Read-only Forge diagnostic](https://forge.laravel.com/dhaval-prajapati/salon-kga/3340514/commands/14015006)

The demo is ready to use; another seed run is unnecessary. No SSH-key changes,
server configuration changes, tenant resets, deployments or financial commands
were performed during this diagnosis. This establishes deployed owner login and
setup readiness, not every module's full production journey or provider delivery.
