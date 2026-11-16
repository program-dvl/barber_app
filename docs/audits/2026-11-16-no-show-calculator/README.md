# No-show cost calculator — 2026-11-16

Scope: FR-01/19/21, owner-approved public free tool and accuracy refinement.
Route: `/tools/no-show-calculator`. No production deployment or outreach claimed.

Implemented: existing Business shell, two-field quick view, six currencies, optional
three-field refinement, transparent earnings-at-risk breakdown, scenario slider,
copy estimate and clean share link, manual clipboard fallback, Resources/footer
links, canonical metadata, sitemap, breadcrumb graph and readable non-SSR fallback.
No new account, database schema, lead capture, analytics input or external service.

Math example: 60 × (8 − 2 refilled) − 100 kept payments − (10 × 6 costs avoided)
= 200 monthly earnings at risk, before fixed costs. At 50% fewer empty slots,
estimated earnings protected is 100; the same averages are assumed. Covered amounts
stop at zero. Blank/invalid values never silently count as zero.

Verification totals are recorded below after the final run. Calculator math covers
integer minor-unit rounding, bounds, zero/invalid inputs, all/none scenario cases,
refilled-slot deduction, fees, avoided costs and fully covered visits. Route checks
cover public access, canonical origin/alias, sitemap, private exclusions, non-SSR
formula and actual built SSR content. Client and SSR builds and frontsite budgets
are included.

Browser QA limitation: CUA returned “Local browser access is unavailable for this
chat.” No desktop/mobile screenshots, clipboard interaction, keyboard journey or
visual certification were obtained in this task. Passing tests/build/SSR is not
substituted for those checks. The running local preview can be reviewed manually.

Final local evidence:

- Calculation suite: 9 passed. Calculation + PublicHead + analytics: 41 passed.
- Marketing backend suite: 300 passed / 4,673 assertions; 1 live-SSR case skipped.
- Separate `CALCULATOR_LIVE_SSR=1` tool suite: 5 passed / 54 assertions against the
  built localhost renderer, including rendered refinements and Resources entry.
- Client + SSR build passed; frontsite budgets passed for 26 route entries.
- Scoped PHP formatting and `git diff --check` passed.
- Local preview: `http://127.0.0.1:8039/tools/no-show-calculator`, using the existing
  ignored isolated SQLite preview database and logged mail. No customer data changed.

Manual browser checks still required: quick/refined estimates at phone/tablet/desktop
sizes; focus order and slider keys; blank/invalid/max inputs; all slots refilled or
payments/costs fully covering service value; copy success/fallback; repeated reset;
Share this tool copying only the canonical URL; guide/trial navigation. Browser
access being unavailable is a verification limit, not a passing visual result.

## Owner screenshot follow-up: currency and unit wrapping

The supplied screenshot showed `USD` splitting into `US`/`D` and `visits` splitting
at its final letter. Main/adjustment inputs used `width:100%` inside a flex row with
shrinkable affixes. The repair uses `flex:1 1 0%; width:0; min-width:0` for the input
and nonshrinking, nonwrapping affixes. Currency/calendar leading widths match at
32px; trailing units remain intrinsic width. Refinement-toggle icons, percentage
badges and breakdown monetary amounts also retain their width. Descriptions wrap.

Verified after repair: 9 calculation tests, client + SSR build, 26-route budgets,
and 5 built-renderer route tests / 76 assertions. DOM checks cover the main monetary
field and both adjustment monetary fields, correct USD affixes, labels, helper IDs,
and decorative unit accessibility. The exact owner-specified port-8000 page returns
its title and main/adjustment controls. An isolated renderer on 13715 was used to
avoid restarting the owner's existing renderer; the temporary build artifact change
was restored. No existing app server or customer records were changed.

CUA again returned “Local browser access is unavailable for this chat.” No replacement
screenshots or complete visual review could be obtained. The above checks validate
source/math/markup, not actual flex geometry, zoom, desktop/mobile pixels or clipboard
interaction. Those browser checks remain pending until access is enabled.
