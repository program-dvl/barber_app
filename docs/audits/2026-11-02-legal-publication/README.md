# Public legal publication — 2026-11-02

## Owner instruction and customer outcome

The owner stated the service is live and requested removal of the Privacy
Policy and Terms review/non-effective notices, including suitable visible
metadata changes. Readers should be able to identify the current policy,
version and effective date without an obsolete draft warning. There is no new
acceptance control or notification. Terms retains print/PDF and related links.

ADR-080 records that authority and its limits. Terms is version 1.0, updated and
effective 2 November 2026. Privacy remains version 1.2 with the same public date
and the Analytics disclosure. Draft-only body wording was cleaned up without
inventing governing jurisdiction, liability caps, indemnities or an SLA. No
existing financial records, environmental values or third-party approvals were
changed. The separate Refund Policy remains in review.

## Independent privacy gates

`privacy.public_effective_at` records the owner-authorized public date. Existing
`privacy.effective_at`, approval, approval reference, operational/provider facts,
`legal:check-privacy`, indexation and sitemap conditions remain unchanged. An
invalid or future configured date is not displayed. Public presentation does
not establish counsel review, completed retention operations or verified
provider/transfer facts.

## Local verification

- Marketing suite: 153 tests / 3,503 assertions passed. Final focused
  TrustPages/PrivacyPolicy rerun, including null/empty operational dates and
  invalid/future public dates: 35 tests / 339 assertions passed. The release
  checker and noindex gate remain blocked even when the public date is shown.
- Scoped Pint and `git diff --check` passed; 21 public route budgets passed.
- Client and SSR production builds passed. Existing daisyUI `@property` and
  runtime-font resolution warnings were emitted; no compilation failure.
- Root verified the actual built app at `http://127.0.0.1:8032`, with SSR
  disabled to exercise fallback HTML and client integration. Explicit browser
  viewports were confirmed as 1440×900 and 390×844. Both pages had no horizontal
  overflow at either width. Privacy showed last updated/effective November 2,
  2026 and v1.2; Terms showed v1.0 and last updated/effective 2 November 2026.
  Neither displayed its obsolete review banner; Terms had no review-status field.
  Root inspected the refreshed screenshots after the completed build. This is
  local preview evidence, not a deployed or live-SSR finding.

| Page | Desktop | Phone |
| --- | --- | --- |
| Privacy Policy | [Screenshot](privacy-desktop.jpg) | [Screenshot](privacy-mobile.jpg) |
| Terms | [Screenshot](terms-desktop.jpg) | [Screenshot](terms-mobile.jpg) |

## Deployment

Forge independently confirmed **981dd17** deployed. Both actual production pages
were checked at **1440×900 and 390×844** after deployment. Privacy showed version
1.2 and last updated/effective November 2, 2026; Terms showed version 1.0 and
last updated/effective 2 November 2026. Neither displayed an obsolete review/draft
banner; Terms displayed no review-status field. Neither page overflowed
horizontally at either width.

| Live page | Desktop | Phone |
| --- | --- | --- |
| Privacy Policy | [Screenshot](live-privacy-desktop.jpg) | [Screenshot](live-privacy-mobile.jpg) |
| Terms | [Screenshot](live-terms-desktop.jpg) | [Screenshot](live-terms-mobile.jpg) |

This verifies the deployed public presentation. It does not establish counsel
review, provider/retention/transfer operations, privacy release-check approval or
changes to the existing noindex and sitemap gates. The separate Refund Policy's
review presentation remains unchanged.
