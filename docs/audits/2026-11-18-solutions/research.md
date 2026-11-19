# Research and decisions · 2026-11-18

Review date: 18 November 2026. Official vendor pages describe vendor claims, not independently verified customer outcomes. No competitor text, screenshots, rating or ROI claim is reused. Browser/mobile competitor usability was not certified; the comparisons below concern the accessible page content.

## Search guidance

- [Helpful content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content): prioritise first-hand product evidence and answers that support a buying decision. Add meaningful industry scenarios, not noun-swapped pages.
- [Spam policies](https://developers.google.com/search/docs/essentials/spam-policies): do not mass-produce keyword permutations. Keep the established URLs.
- [SEO starter guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide): descriptive titles, useful link text, crawlable content and consistent canonical identity. Retain curated sitemap policy and add readable solution HTML if SSR is unavailable.
- [AI features](https://developers.google.com/search/docs/appearance/ai-features): ordinary search fundamentals apply; no special AI file or inclusion promise.
- [Structured data](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data): markup must describe actual content. Existing shared organization/site/software entity graph is retained, without new offers, reviews or industry-specific SoftwareApplication entities.
- [Google documentation updates](https://developers.google.com/search/updates): current guidance says FAQ rich results stopped appearing on 7 May 2026; the old FAQ documentation redirects to the update log. FAQs here serve readers, with no FAQ-rich-result promise or added FAQ schema.

## Buyer benchmarks and actions

| Audience | Official pages reviewed | Buyer expectation / action for ClipperDesk |
|---|---|---|
| Salons | [Boulevard salon](https://www.joinblvd.com/salon-software), [Fresha scheduling](https://www.fresha.com/for-business/features/scheduling) | Connect schedule, client context and checkout; show actual screens and inspectable timing rather than an all-in-one slogan. Explain payment boundary. |
| Barbers | [Fresha barber](https://www.fresha.com/for-business/barber), Fresha scheduling | Explain preferred barber and appointment/arrival handling. Show actual queue; keep wait estimates honest. |
| Independents | Fresha scheduling’s independent-business path, [Vagaro](https://www.vagaro.com/pro) | Setup effort and price matter. Link current pricing, show working hours and explain bookable staff versus admin users. |
| Spas | [Boulevard spa](https://www.joinblvd.com/spa-software), [Fresha spa](https://www.fresha.com/en-GB/for-business/spa-and-sauna) | Buyers expect treatment and room detail. Explain cleanup and the incomplete allocation editor; test two practitioners sharing one room. |
| Nails | [Boulevard nails](https://www.joinblvd.com/nail-salon-software) | Show service eligibility and appointment composition. Removal/add-on timing and shared-station limits matter more than generic scheduling copy. |
| Medspas | [Boulevard medspa](https://www.joinblvd.com/medical-spa-software) | Vendor presents a clinical/product scope beyond scheduling. ClipperDesk must clearly say that clinical suitability and integration are unverified, even with a separate EHR. |
| Massage | [Boulevard](https://www.joinblvd.com/) massage category, Fresha scheduling | Explain session lengths, returning clients and room turnover. No invented recurring series or clinical notes. |
| Fitness/recovery | [Vagaro personal training](https://www.vagaro.com/pro/personal-training-software) | Packages/classes are material buying expectations. Narrow to one-to-one appointments and surface their absence. |
| Physical therapy | [Jane physical therapy](https://jane.app/physicaltherapy), [Jane scheduling](https://jane.app/features/scheduling?locale=en-US) | Clinical documentation, insurance and course workflows matter. General appointment administration is insufficient proof of product fit. |
| Health practices | Jane scheduling / physical therapy | No blanket healthcare fit. Lead with data suitability, contractual review and missing specialist systems. |
| Tattoo/piercing | [Vagaro tattoo](https://www.vagaro.com/pro/tattoo-shop-software) | Consultation/session lengths, deposits and forms are buying issues. Explain recorded payments, unavailable deposits and no age/consent certification. |
| Pet grooming | [MoeGo pet profile FAQ](https://help.moego.pet/en/articles/14133919-pet-profile-faq), [MoeGo Help](https://help.moego.pet/en) | A distinct pet record is fundamental in specialist products. Show the owner-based record limitation and a two-pets/one-owner fit exercise. |
| Tanning | [Vagaro tanning](https://www.vagaro.com/en-au/pro/tanning-salon-software) | Memberships and packages are prominent expectations. Narrow to staff-led appointments; explicitly exclude bed controls and exposure rules. |

These observations informed content, not a public competitor scorecard. No superiority, traffic transfer or conversion lift is inferred.

## Query signals and URL ownership

Queries.csv was not found in the workspace or available temporary attachments. The supplied snapshot totals (117 rows, 394 summed impressions, zero clicks) are user-provided, not independently recomputed. The 17 representative rows are preserved verbatim in `supplied-queries.csv`; normalisation would trim surrounding quotation artifacts and normalise punctuation without replacing originals. No invented full export, date range, device/country split or ranking URL is implied.

| Intent cluster | Primary existing URL | Treatment |
|---|---|---|
| Spa scheduling | /solutions/spas | Treatment timing, actual screen, room caveat |
| Physical therapy / physiotherapy scheduling | /solutions/physical-therapy | Suitability review; no clinical promise |
| “PT” scheduling | Unassigned pending intent evidence | Could be personal training or physical therapy; do not silently allocate |
| Pre-visit planning | /features/client-management for business-form intent only | Clinical interpretation remains unsupported; existing forms guidance linked through client records |
| Salon client management / records | /features/client-management | Strengthened returning-client handoff and permission questions; no synonym pages |
| Dog / pet / groomer scheduling | /solutions/pet-grooming | One owner-based workflow with explicit pet-profile gap |
| Massage scheduling / management | /solutions/massage | One page; session/turnover and repeat-visit boundaries |
| All-in-one salon software | /solutions/salons | Show service sequence and payment limits; four impressions are not evidence of stable rank |
| Salon waitlist | /features/calendar-and-walk-ins | Six supplied impressions; strengthen existing page, distinguish future openings from arrivals |
| Spa payroll / salon memberships / bootcamp | No new target page | Product fit fails; answer relevant objections on existing pages |
| Unrelated account-deletion strings | None | Exclude from acquisition planning |

Positions near 90 and zero clicks do not diagnose a title-only problem. Check indexing, relevance, competition and authority. Query-only data cannot identify which URL currently ranks; request query-by-page/device/country data before considering overlap or consolidation.

## Expansion gates

No new pages created. Waitlist is real but the existing calendar page plus maintained Help guide can cover the intent; it now explains the distinction and lack of owner dashboard. Client-management content is strengthened in place. Existing no-show article, planner and calculator already cover attendance; solution pages connect to the calculator. Memberships fail implemented billing/redemption gates. Forms exist in client management and Help; do not relabel them clinical planning. Grooming, massage and nail synonyms remain consolidated on established pages.
