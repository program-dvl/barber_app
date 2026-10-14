# Setup usability and booking confirmation repair — 2026-10-14

Requirements: FR-01/02/03/05/09/19; ADR-066. Implemented locally; not deployed.

## Changes

Google signup now goes directly to the saved introduction and ignores stale
intended navigation. Email signup still verifies the owner before tenant setup.
The owner-provider decision explains its actual calendar/team effect. The
existing-online-booking question is removed; imports remain available.

Address entry now chooses Google search or manual entry. Search has a consistent
outer border; a selected address has one review summary and edit action. Manual
entry uses one full address, without a required structured questionnaire. Maps
open only on request; corrections/arrival details stay optional and appear after
an address is present. Address/pin persistence, stale selection protection and
saved entrance behavior remain covered by the compiled Vue tests.

New introductions use a separately named immutable daily 09:00–21:00 preset;
older saved presets and established hours remain unchanged. The questionnaire is
replaced by a clear editable-default note. Each step shares the edit-later reminder.
Business profile puts the address/action above every profile group.

The preview is the real public `Booking/Welcome` services page, with shared
catalog and business presentation. Desktop/mobile controls resize one embedded
page; a full-size private link is available. Preview cannot restore browser
booking sessions, continue to times, hold capacity, confirm or initialize commerce.
Private branding uses separate settings-authorized tenant routes. No-store/noindex
remain; only successful private preview HTML permits same-origin framing. All
other pages continue denying framing. Viewing never records explicit review or
publishes the business.

## Booking failure

Confirmation used strict PHP array comparison for held/current location details.
The same JSON object with keys in a different order failed that comparison.
MySQL JSON normalization can expose this while SQLite fixtures preserve order.
The comparison now sorts both key sets and retains strict value comparison and
the existing exclusions for derived map links/primary flag. Real location or
policy-version changes still require fresh review. A reordered entrance/address
snapshot is reviewed through the public details endpoint, confirmed and replayed;
exactly one appointment is created. No real customer booking was submitted.

## Verification

- Complete backend suite: **746 passed / 6,956 assertions**, 30 existing intentional
  integration/disabled-feature skips.
- Complete frontend suite: **98 passed**; affected compiled address/branch/map
  tests pass again after the final disclosure changes.
- Client and SSR production builds, 17 public route asset budgets, scoped Pint and
  whitespace checks pass. Existing CSS `@property`/font-resolution warnings remain.
- Browser review uses an isolated copy of the synthetic business-setup-review
  SQLite database on localhost:8031, fake messaging and no real customer data.
  No primary database migration or real business edit/publication was performed.
- Checked first introduction, question meaning, Continue/back navigation,
  address-mode/manual entry and daily-hours note; Business profile and actual
  preview at desktop/mobile widths. Address/profile have no horizontal overflow at
  390px; the preview container fits 320px. The actual embedded catalog responds to
  the mobile toggle and Continue remains disabled after selecting a service.
- Google selection/pin behavior is covered by mocked compiled-component regressions.
  Real Google provider/referrer certification was not repeated on the new review
  origin. Production MySQL/provider/browser certification and live deployment are
  not claimed.

## Screenshots

- [Clarified owner question](business-details-desktop.png)
- [Address entry, desktop](address-desktop.png) and [mobile](address-mobile.png)
- [Address near the top, desktop](business-profile-desktop.png) and [mobile](business-profile-mobile.png)
- [Actual desktop preview](booking-preview-desktop.png) and [mobile preview](booking-preview-mobile.png)

Production rollout requires the normal code/client asset deployment (and SSR
process restart if used). There is no new database migration for this change.
OPEN-13/15/16/19/20/21/23 and existing launch controls remain unchanged.
