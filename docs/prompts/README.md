# Implementation prompt library

Use one prompt per separate Codex thread, in numeric order unless
`docs/decisions.md` records a safe reordering. Each prompt is deliberately
bounded and includes its own documentation handoff.

## How to use

1. Start a new thread with the repository set to
   `/Applications/AMPPS/www/barber_app`.
2. Paste the full contents of one prompt file.
3. Answer only the genuinely blocking product/provider questions the agent
   cannot resolve from the repository and decision log.
4. Require the agent to finish verification and documentation before starting
   the next prompt.
5. Review `docs/project-status.md`, test results, and decision changes at the
   handoff.

## Prompt order

| Prompt | Outcome |
| --- | --- |
| 00 | Audit the Larafast foundation and accept an adoption plan |
| 01 | Establish brand-ready, accessible product shell and design rules |
| 02 | Establish tenant isolation, identity, staff access, and audit |
| 03 | Establish subscription lifecycle and server-side entitlements |
| 04 | Build onboarding, locations, catalogue, staff, schedules, resources, import |
| 05 | Build the atomic availability and booking engine |
| 06 | Build operational calendar, lifecycle, and walk-in queue |
| 07 | Build mobile public booking, secure self-service, and waitlist |
| 08 | Build client CRM, forms, consent, attachments, and privacy workflows |
| 09 | Build reliable, consent-aware communications |
| 10 | Build deposits, checkout, payments, refunds, receipts, and cash close |
| 11 | Build inventory, commissions, dashboard, reports, and instrumentation |
| 12 | Build platform admin and safe support operations |
| 13 | Complete launch hardening and chargeability validation |
| 14 | Audit the public front-site, search surface, claims, and route boundaries |
| 15 | Build the accessible public design system and navigation |
| 16 | Build the evidence-backed homepage and conversion narrative |
| 17 | Build the product and feature information architecture |
| 18 | Build differentiated industry solution pages |
| 19 | Build useful problem and use-case pages |
| 20 | Build server-owned pricing and trial-selection conversion |
| 21 | Build truthful company, security, and legal surfaces |
| 22 | Build the resources, guides, and safe editorial system |
| 23 | Establish technical SEO, indexation, schema, sitemap, and errors |
| 24 | Align public answers for GEO, AEO, and AI-assisted search |
| 25 | Harden performance, accessibility, and international readiness |
| 26 | Establish conversion telemetry and internal-link contracts |
| 27 | Complete the final front-site and SEO launch audit |

Do not combine prompts merely to move faster. The product has capacity,
financial, privacy, and tenant-isolation risks that require clear stage gates.
