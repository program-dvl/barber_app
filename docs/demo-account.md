# Atelier sales-demo account

Requirements: FR-01–FR-19 and the PRD launch-readiness demo tenant. This is an
opt-in, isolated fictional business for customer pitches. It is not part of
`DatabaseSeeder`, registration or automatic deployment hooks.

## Deploy and run

Deploy `database/seeders/PitchDemoSeeder.php` and the entire
`database/seeders/assets/pitch-demo/` directory with the application. Run normal
application migrations first. The standard active default trial plan must exist;
the seeder deliberately does not reconfigure the platform's shared plan catalogue.
PHP GD and the existing configured private/public storage disks must be available.
For local public storage, use the application's normal `php artisan storage:link`
setup so account avatars can load. Private staff photos and gallery images use
existing authorized media endpoints.

Run this from the deployed application root. No password environment variable is needed:

```bash
php artisan db:seed --class=PitchDemoSeeder --force
```

If Forge reports a tunnel/command failure after “Seeding database”, do not infer
that the seed rolled back. Forge's command session has its own execution limit.
Check the existing demo login and completion record before rerunning. On
2026-11-07 the Forge run reported failure, but production had all 1,640 visits
and the live demo login/setup passed; see [the live verification](audits/2026-11-07-canadian-demo/README.md#production-verification-after-forge-command-errors--2026-11-07).

Sign in at `/login`. The owner explicitly selected **Password@123** for all fourteen
demo accounts. It is stored as a password hash in the database. Rerunning the
seeder restores that password for the original fourteen demo-only accounts while
preserving all operational and financial records. It first checks that all fourteen
accounts still belong exclusively to the marked demo business; changed ownership
stops the password update. Changed passwords clear remember tokens and produce an
audit record without the password. No other account is reset.

## All demo logins

| Email | Password | Role / location |
| --- | --- | --- |
| `pitch.owner@example.test` | `Password@123` | Owner · both locations |
| `pitch.manager@example.test` | `Password@123` | Manager · both locations |
| `pitch.reception@example.test` | `Password@123` | Receptionist · both locations |
| `pitch.accountant@example.test` | `Password@123` | Accountant · both locations |
| `pitch.staff1@example.test` | `Password@123` | Stylist · Queen Street Studio |
| `pitch.staff2@example.test` | `Password@123` | Stylist · Queen Street Studio |
| `pitch.staff3@example.test` | `Password@123` | Stylist · Queen Street Studio |
| `pitch.staff4@example.test` | `Password@123` | Stylist · Queen Street Studio |
| `pitch.staff5@example.test` | `Password@123` | Stylist · Queen Street Studio |
| `pitch.staff6@example.test` | `Password@123` | Stylist · Riverside Studio |
| `pitch.staff7@example.test` | `Password@123` | Stylist · Riverside Studio |
| `pitch.staff8@example.test` | `Password@123` | Stylist · Riverside Studio |
| `pitch.staff9@example.test` | `Password@123` | Stylist · Riverside Studio |
| `pitch.staff10@example.test` | `Password@123` | Stylist · Riverside Studio |

The ten seats mean **ten bookable professionals, five per location**. Each branch
also has a physical resource representing five chairs. Administrative accounts do
not consume bookable seats. Shared cross-branch services leave physical chair
requirements unattached because the existing engine requires every attached
resource to belong to the booking branch. Staff availability enforces the five-person
maximum at each location. No production booking rule is changed.

Clients use the existing secure-link journey; they are not employee login accounts.
No platform-administrator account or additional platform permissions are created.

## Included demonstration records

- Two active fictional Toronto locations (Queen Street and Riverside), CAD operations,
  en-CA presentation and America/Toronto times,
  daily 09:00–19:00 hours and staff lunch breaks from 13:00–14:00.
- Fourteen distinct generated account portraits; the ten professionals also have
  privately stored profile portraits, titles, biographies and linked memberships.
- Two generated professional interior photos, presented through the existing
  business gallery with location-name captions, plus a Queen Street cover.
  The product currently has no independent location-photo field. Assets are bundled,
  with [generation prompts and provenance](../database/seeders/assets/pitch-demo/README.md).
- Eight online-visible services in three categories; all ten professionals are
  qualified, and both branches are eligible. No deposit is required for a demo booking.
- Sixty named fictional clients with reserved example contacts, preferences, tags,
  preferred staff/services and encrypted notes. No marketing consent is invented.
- Bookings cover two calendar months before the run date, today, and two calendar
  months after it, using month-end-safe local dates. A 7 November 2026 seed creates
  1,640 appointments from 7 September 2026 through 7 January 2027, including the
  Toronto daylight-saving change. Every third day includes an afternoon visit
  for each professional. All staff have bookings. Slots avoid lunch and overlaps.
  Exact completed counts depend on the run time. Historical visits include
  completed, cancelled and no-show states with lifecycle history; upcoming visits
  remain confirmed. Appointment creation dates never lie in the future.
- Completed visits have canonical checkout records, cash/card/bank-transfer recorded tenders,
  retail product purchases, tips, receipts and 15% service commission entries.
  One recent partially paid sale and one service refund illustrate exceptions.
  Receipt snapshots remain unchanged after refunds. No provider is contacted.
- Eight retail products, stock at each branch, replenishment and sale movements;
  one product has three remaining at each branch as a reorder example.
- Six waiting walk-ins, six active waitlist requests, two future team-training blocks,
  fourteen cash closes, six starter form templates, twelve submitted consultation
  forms and four outstanding form requests.
- Private sample feedback for completed visits and twenty **suppressed**, simulated
  notification-history records. No sample review is published as an endorsement.
- A one-year, demo-only trial granting two locations and ten staff. Billing displays
  complimentary CAD demo access and a clearly labelled CA$0
  demonstration invoice. The sample tax setting is 13% inclusive; it is fixture
  data, not a legal/tax certification. No paid CAD rate card, exchange-rate
  conversion or new commercial market is configured. It has no real provider customer, subscription or payment
  identifiers. This exception does not change normal customer trial lengths or
  grant a paid subscription. Subscription expiry is printed after seeding.

Inventory, commissions and other workspaces retain their existing product visibility
and role rules. The seeder does not enable deferred modules, memberships/packages,
gift cards, loyalty, real card collection or platform-wide feature flags.

## Repeat runs, failures and financial safety

A completed seed is identified by its business slug and an append-only
`demo.pitch.seeded` audit event. Repeating the command prints the existing demo
URL and restores `Password@123` for all fourteen original demo logins.
Version 2 automatically upgrades a previously seeded version-one business once:
it changes current region/catalogue configuration to CAD/Toronto, marks the saved
introduction complete and adds the longer Canadian appointment window. Only
still-active generated INR bookings are cancelled with retained history and a
recorded reason; manually added appointments are not silently rewritten. Existing
sales, payments, issued receipt snapshots, commission entries, cash closes and
invoices retain their original currencies and amounts. They are not relabelled as
CAD or converted. Select CAD in currency-specific reports for new demo activity.
The upgrade is audited, tenant-contained and transactional, including password
restoration; a failed upgrade restores the previous state. Later reruns do not
extend the booking window or duplicate the Canadian upgrade. Never use `migrate:fresh`,
truncate tables or remove financial history on the live application to refresh it.
A colliding business slug without the completion marker or an existing login email
fails clearly instead of attaching to or changing another business/account.

Database creation runs in one transaction. A failure rolls back this seed's
records and removes its tracked image files; existing customer records are not
updated. Tenant scope, permission context, event-dispatch configuration and test
clocks are restored after completion or failure. Trial expiry remains a real gate.

Email/SMS and automatic receipt/review emails start disabled for this business.
Clients and appointments explicitly decline both channels. Historical operational
events are ignored before commit, with immediate dispatch disabled only inside
this CLI process. History records are terminal/suppressed, with no queued delivery
or provider evidence. Real worker and provider settings are not changed. Do not
replace demo contacts with real contacts or enable messaging during a pitch.
No payment gateway calls, card charges, refunds through a provider, or external
messages are performed by this seeder. Normal account security/sign-in alerts
remain governed by the existing platform policy; demo login addresses are
reserved fictional addresses and cannot receive them.

## Completed Business setup

Both fresh creation and version-one upgrades save `personalized_at` and
`guided_completed_at`, the four introduction steps and existing configuration
steps. The demo keeps its own provenance (`source: pitch_demo`, `demo_version`)
instead of claiming normal starter-data generation. This makes the existing UI
open the readiness overview with **6 of 6 essentials ready** and **Your current
setup**. It does not reopen “Let’s prepare your business” or claim that no client
activity exists. The actual readiness evaluator must pass before committing.
No normal registration flow or Vue layout is changed.

## Verification

The isolated SQLite feature suite verifies creation, real owner authentication,
verified logins and password hashes, branch membership links, portraits/media,
publication readiness, owner page responses, entitlement quantities, appointment
windows and absence of staff overlaps, sale arithmetic, stock/commission/receipt
history, no queued communication, repeat-run data preservation and password restoration, collision rejection,
and rollback including image cleanup after a late injected failure. It uses a
microsecond clock and prevents unmocked HTTP requests.

```bash
php artisan test --filter=PitchDemoSeederTest
```

The full scoped suite passes **4 tests / 955 assertions** locally; the final
focused upgrade regression passes **1 test / 35 assertions**, including manually
added record preservation. The feature suite covers fresh creation, collisions, password restoration,
late creation failure, and upgrading the previously seeded business while retaining
issued financial records. It verifies month boundaries, both Toronto daylight-saving
offsets, actual guided completion and demo provenance, and upgrade replay/rollback.
PHP formatting and whitespace checks also pass locally. A separate isolated local
browser verifies the ready setup at 1440px and 390px, with no horizontal overflow,
Canadian region/CAD display and no introduction/starter disclaimer. Saved desktop
and phone evidence is in [the Canadian demo audit](audits/2026-11-07-canadian-demo/README.md).
This is not deployed-system, MySQL-concurrency or provider certification evidence. After you deploy, sign in as the owner,
open both location calendars and a completed sale/receipt, check the Team portraits
and booking gallery, then try a staff account to confirm its branch/own-calendar
view. No production seeding or deployment was performed in this change.
