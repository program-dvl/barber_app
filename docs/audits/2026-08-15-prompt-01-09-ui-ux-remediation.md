# Prompt 01–09 UI/UX remediation audit

Date: 2026-08-15

Standard: [`docs/design-system.md`](../design-system.md)

## Scope and result

Every existing user-facing Vue page associated with Prompts 01–09 was reviewed
against the Good Hours UI/UX standard. Existing behavior, tenant isolation,
permissions, scheduling truth, append-only history, and accepted requirements
were preserved. The review changed presentation and interaction hierarchy; it
did not promote later-prompt product scope.

| Prompt | Existing surface reviewed | Result |
| --- | --- | --- |
| 01 | Shop/public/platform shells, sign-in, registration, shared cards and states | Updated |
| 02 | Invitation and access-facing shell states | Reviewed; shared-shell updates apply |
| 03 | Subscription and billing overview | Updated |
| 04 | Business onboarding and configuration | Updated to focused sections and structured choices |
| 05 | No standalone user page; booking engine is consumed by later surfaces | No page to redesign |
| 06 | Operational calendar and walk-in queue | Updated |
| 07 | Public booking, secure management, waitlist offer, and public client forms | Updated |
| 08 | Client directory, profile, visits, forms, files, consent, and privacy | Updated |
| 09 | No dedicated business-facing Vue page exists | Backend capability retained; UI gap recorded |

## Principal changes

- Replaced nested-card and developer-facing presentation with clear page,
  section, and action hierarchy.
- Limited page-level primary actions and moved rare calendar actions into an
  overflow menu.
- Added three selectable dashboard working views over live tenant-scoped data.
- Converted configuration from one long wall of fields into resumable focused
  sections with centralized reference choices and human-scale money/duration
  inputs.
- Split the Client workspace into Overview, Visits, Forms & consent, Files, and
  Privacy sections and converted internal enum-style labels to plain language.
- Kept public booking as one mobile-first step flow, reduced repeated date text,
  and clarified progress, privacy, time-zone, deposit, and waitlist language.
- Introduced the branded two-column Good Hours authentication shell.
- Simplified billing, platform-readiness, empty, unavailable, and permission
  states so they describe user impact rather than implementation status.

## Verification evidence

Browser captures are stored in
`docs/evidence/ui-ux-audit-2026-08-15/`. They include all dashboard variants,
configuration, calendar, Client directory/profile/forms, billing, platform
operations, public booking at 390px, authentication, and the mobile dashboard.
The blocking visual comparison and result are recorded in the repository-root
`design-qa.md`.

Automated verification covers the frontend production build, the product-shell
route and token checks, tenant-scoped live dashboard data, business
configuration, and public booking behavior.
