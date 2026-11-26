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


## Final production acceptance

Source commit `ab3f2c03d9aa85a44c8eb84a2458fd0187c52d56` was pushed to master. Forge deployment `84113013` was observed **Deployed** through the existing push-to-deploy workflow. No duplicate manual deployment was triggered. The three new FAQ/definition markers were observed on the actual production salon, client-management and calendar/walk-in pages. Expanded client-record and waitlist answers and the salon answer were reviewed at actual 390×844 viewports; document width was 390px on all three. Temporary viewport overrides were reset. Desktop client-record content was also inspected.

Independent live initial-HTML checks found each new content marker, exactly one H1, the expected primary-host self-canonical and `index, follow, max-image-preview:large` on all three pages. The public sitemap had 145 URLs including salon, barber, both feature pages and the demo scorecard. Robots advertised the primary sitemap. The sitemap was not resubmitted unnecessarily.

Google accepted all four recrawl requests: salon solution, barber solution, client-management feature and calendar/walk-in feature. Each ended with **Indexing requested** and the priority-queue confirmation. No CAPTCHA was solved or access control bypassed. Acceptance does not prove a subsequent crawl or improved rank.

GA Admin confirmed the dedicated ClipperDesk public-website property already has `marketing_cta_clicked` marked as a key event with an active stream in the last 28 days. The default purchase event showed no stream data. No fake purchase, trial-completion event, identity join or tracking-consent change was added. CTA interest must not be counted as completed trials.

Release evidence was recorded after production verification locally; it is not a claim that later Google ranking or acquisition results were measured. No recurring report or reminder was scheduled. Use the recorded country/query baseline for the next matched 28-day comparison, retaining low-sample caveats.

## Second content release acceptance

Commit `006410390e0df7fc75b80bd387871bdcc2ea288e` was pushed through the existing deployment workflow. Forge deployment `84118278` was observed **Deployed**. Production browser checks confirmed the returning-client steps on the salon/client-management pages and the future-opening/offer steps on calendar-and-walk-ins. The new `/guides/salon-client-record-template` resource rendered its fictional example, six worksheet prompts, limits and related links. Independent final initial-HTML inspection found exactly one H1, the correct self-canonical, `index, follow, max-image-preview:large`, and readable worksheet content. A pre-deployment fetch returned the old 404; the final post-deployment fetch supersedes it.

318 Marketing tests passed, two optional checks skipped, with 5,533 assertions on in-memory SQLite. After the final copy adjustment, both targeted resource tests passed again (64 assertions). Production client/SSR build and all 27 bundle budgets passed. Local resource layout was inspected desktop and at 390×844; resource/waitlist document width matched 390px. Viewport overrides were reset. No new Search Console requests for the same four previously requested pages, listing submissions, purchases, reviews or outreach were made. No new ranking outcome was established.

See [competitor evidence and distribution shortlist](competitors-and-distribution.md) for reviewed sources, the practical next actions and reusable truthful listing copy. This acceptance note was recorded locally after deployment.
