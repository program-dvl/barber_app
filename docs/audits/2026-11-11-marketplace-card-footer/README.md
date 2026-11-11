# Marketplace rating and cookie footer — 2026-11-11

Requirements: MP-01, MPG-06/08. Local implementation only.

## Reported defects and changes

The discovery card used `.rating`, which collides with the installed DaisyUI rating
widget and masks/squeezes its nested review text. `Discover.vue` now uses
`.salon-rating`, displays the actual score/count (for example ★ 4.0 (1)), and gives
assistive technology the full score and singular/plural review count. The repeated
“verified visits” suffix is removed from cards; consented review provenance is unchanged.

`AcquisitionConsent.vue` previously rendered a padded block outside the Marketplace
canvas. Its Cookie settings opener now lives in the existing Marketplace footer,
right aligned, transparent and matched to the footer typography. Direct business
booking retains an equivalent opener in its own footer. There is no separate footer
band. Both open the existing global consent panel through a browser event, with the
listener removed on unmount. Choices, 30-day expiry, browser privacy signals, source
classification and observation/deletion endpoints retain their existing behavior.
The compact opener retains a 44px target and visible keyboard focus.

## Evidence

- `node --test tests/Frontend/*.test.mjs`: 247 passed. New mounted tests cover actual
  rating/count/address rendering and saved rejection → reopen → reject → reopen →
  accept, including deletion/observation requests. Actual layout SSR tests cover one
  settings control inside each Marketplace/direct-booking footer.
- `MPG_TEST_LIVE_SSR=1 php artisan test --filter='Tests\\Feature\\MarketplaceGrowth\\SeoTest::'`:
  9 passed, 152 assertions. Built SSR HTML contains one settings button within the
  footer on directory and canonical booking pages, with no former opener strip;
  fallback, private headers, paused supply and canonical/indexation regressions pass.
- `npm run build`: client and SSR passed. `npm run check:frontsite-budgets`: all
  25 entries passed. Scoped Pint and `git diff --check` passed.
- Desktop/mobile browser verification remains unavailable: CUA returned
  “Local browser use is disabled in Work settings”. No screenshot or visual-success
  claim is made. Responsive styling/component output was checked in code/tests.

No migration or environment change is required. Deploy the rebuilt client and SSR
assets together and restart the SSR process through the normal deployment workflow.
Production appearance and browser interactions still need a visual check there.
