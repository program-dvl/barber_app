# Search follow-through — November 26, 2026

## First release observed

The initial SEO batch is contained in owner-published commit `12fe68d`, already shared by local master and origin/master. Chrome observed the new salon evaluation FAQs and scorecard link on production. No duplicate deployment of that batch was triggered. Production salon, barber and scorecard pages were viewed at 390×844; document width equaled viewport width (390px), with readable hero text and separated actions. Salon and scorecard canonicals match their primary HTTPS URLs. Desktop salon content was also read. No public customer data was entered.

Search Console URL inspection reports salon and barber pages indexed. Both request-indexing actions completed with **Indexing requested** and priority-queue confirmation. This is request acceptance, not proof of a subsequent crawl, ranking improvement or new customer.

## Country baseline

Selected three-month Web report, data shown October 11–November 24: total 717 impressions, two clicks, average position 68. Country rows: US 432 impressions/one click; Canada 35/zero; India 47/one; UK 76/zero; Australia 58/zero. Click identities are unknown.

US queries (zero reported clicks for each):

| Query | Impressions | Average position |
|---|---:|---:|
| salon client management | 15 | 69.4 |
| hair salon waitlist management software | 4 | 25.0 |
| salon record keeping software | 2 | 13.0 |
| all in one salon software | 6 | 4.8 |
| barber shop management software | 3 | 2.0 |

These samples are too small to claim stable top-five rankings. They show the overall mean does not describe every query.

Query/page inspection: US hair-salon waitlist query maps to the published salon buying-guide blog article (4 impressions, position 25). US record-keeping query maps to `/solutions` (2, 13). US salon-client-management results are distributed over salon solution (8, 98.5), solutions hub (5, 41.4), buying-guide article (2, 27) and client-management feature (2, 35). Grouped page rows can differ from query-level totals. Preserve the guide's evaluation intent; do not create a duplicate keyword-variant article or assume the wrong landing page ranks.

Canadian visible query rows: spa scheduling software 6 impressions; spa payroll software 5; physiotherapy scheduling software 4; spa appointment software 3; several spa/nail variants 2; salon client management 2; hair salon computer systems 1. Payroll/clinical impressions do not justify unsupported claims. Canadian salon data is too sparse for a separate country landing page.

## Three implemented page improvements

- `/solutions/salons`: explain actual client-history use between appointments and its limits.
- `/features/client-management`: evaluate record keeping through fictional profiles, role access and non-clinical boundaries.
- `/features/calendar-and-walk-ins`: distinguish present arrivals from future-opening requests; add a real customer waitlist Help link and a test scenario with expiring offers and availability rechecks.

These support existing acquisition pages without adding doorway URLs. No clinical compliance, memberships, paid acquisition, invented testimonials, outreach or guaranteed refill claims were introduced. Existing GA consent/withdrawal/private-route rules and verified-trial reporting are unchanged; full Marketing regression includes those tests.

## Verification and release

316 Marketing tests passed, two skipped (5,453 assertions), on the configured in-memory SQLite test database. The skips are optional renderer checks; automated route tests alone are not production SSR evidence. Client and SSR production builds pass; all 27 route bundle budgets pass. The build retains existing CSS/font-resolution warnings; no new runtime font certification is claimed. No production migration or customer-data change is needed.

For follow-up, compare matched 28-day periods by country, query, page and device, with brand queries separated. Keep CTA intent separate from verified trials; consent-based GA is not a full visitor census. Use the baseline above as historical context, not a conversion attribution claim. No recurring automation is created. Update this record only with observed deployment and Google request outcomes.
