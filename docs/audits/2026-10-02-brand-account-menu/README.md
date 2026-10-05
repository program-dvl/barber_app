# Brand color and account menu correction

2026-10-02. Limited owner-requested follow-up to match the public site's primary
brand color and improve the signed-in account popover.

## Implemented

- Authenticated sidebar/mobile drawer and header identity use brand-primary navy
  `#172554`, matching the public Product and Pricing active navigation. The
  action-primary indigo `#4338ca` and content palette remain unchanged.
- Account popover: 14px medium-weight icon rows, 14px semibold name, 12px email,
  restrained dividers and separate Sign out action. Long identity details wrap.
- A single permitted billing workspace gets a direct Subscription & billing
  link. Several retain their unchanged names under Business billing. Existing
  permissions, destinations, logout and native disclosure behavior are retained.
- Panel width/height are bounded to the viewport; touch rows are at least 44px.

## Fresh verification

Used a separate SQLite demo fixture and local preview. Real business data was
not modified. No external payments or messages were initiated.

| Screen/state | Size | Result |
| --- | --- | --- |
| Public home, Product and Pricing | Desktop | Brand/action tokens checked; Product/Pricing selected navigation is navy |
| Reports and account popover | 1280×800 | Sidebar navy; Manrope 14px medium-weight account action labels |
| Account popover | 360×800 | 280px panel; 44px action rows; no page-level horizontal overflow |
| Long account name/email on Dashboard | Desktop and 360×800 | Text wraps inside panel; document width remains 360px on mobile |
| Account keyboard controls | Desktop | Enter opens, arrow keys move between actions, Escape closes and restores trigger focus |

Screenshots are saved alongside this report. The long-name mobile panel's inner
scroll width is 278px within its 280px border box. The fresh long-name browser
tab reported no warnings/errors.

Client and SSR builds pass. Existing frontend tests: **9 passed**. Public asset
budgets: **17 entries passed**. Diff whitespace checks pass. Backend behavior
was unchanged; no new full PHP-suite run is claimed.

## Inspection limits

Protected platform shell, other roles and zero/multiple permitted billing
workspaces were source/build reviewed rather than freshly rendered. Increased
text size and physical-device testing were not repeated for this limited change.
Earlier platform-wide verification and its remaining gaps belong to the prior
dated audits; this report does not claim a new whole-platform review.
