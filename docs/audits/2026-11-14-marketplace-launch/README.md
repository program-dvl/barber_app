# Marketplace launch experience — local evidence

Date: 2026-11-14, Asia/Kolkata. No production deployment or production delivery claim.
Reference: [owner-approved design](approved-design.png).

Implemented in the existing Laravel/Inertia app with owned industry photos and the
shared CD Marketplace logo. Compared at 1440×1000 and 390×844. Category/guide pages,
all 13 audience destinations, city context, signup success, token confirmation and
mail-failure recovery were exercised in the in-app browser. The mobile headline gap
and a category query-parameter rendering error found during QA were fixed and
rechecked. Shared action icon sizing also fixes the compressed-label/large-arrow
issue found on the category page.

Local backend verification:

- Final launch suite: 10 tests / 313 assertions passed.
- Combined launch, legacy discovery, qualified supply and automatic-publication
  suites: 28 tests / 601 assertions passed after the enrollment-lock refactor.
- Vite client + SSR builds pass. Existing DaisyUI @property and missing local
  Manrope TTF warnings remain; no change to those unrelated assets.
- New migration applied only to the isolated SQLite preview, not the application’s
  normal or production database.

Preview fixtures: `/Applications/AMPPS/www/barber_app/tmp/marketplace-qa/launch/` (ignored). Main local preview uses logged email on port 8039. Port 8040 intentionally
uses an unavailable transport to exercise failure. No actual message was sent to a
customer, and no real business records were created or changed.

UI evidence files in this directory show the desktop/mobile launch page and the
real browser error state, with preserved inputs, 60-second retry and support action.
The release still needs its migration, normal deployed build, mail delivery check,
privacy coverage review and an operator following the area-update runbook.
