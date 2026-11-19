# Homepage SEO review — 2026-11-19

Scope: local redesigned Business homepage, not a deployed ranking/conversion audit.

Owner correction: broad appointment-led business positioning supersedes the initial
salon/barbershop focus. Current title: Appointment Scheduling & Business Software |
ClipperDesk. Hero, description and definition cover beauty, wellness, fitness, health
and pet care; all 13 industry destinations are linked. Earlier captured JSON files
record the prior narrow-title review and are historical, not current-copy evidence.

## Assessment and changes

The underlying metadata, schema, public indexation and SSR structure were sound.
The content needed a clearer category focus and better fallback coverage.

- Changed the generic title to **Appointment Scheduling & Business Software | ClipperDesk**.
- Added a concise description covering online booking, walk-ins, staff schedules,
  client records, pricing and setup help. No keyword meta tag or density target.
- Preserved the approved visual H1 and placed a descriptive category eyebrow and
  broad appointment-led business audience sentence directly beside it in normal HTML.
- Made feature headings descriptive: online appointment booking; walk-ins/staff
  schedules; client records/visit history.
- Added three contextual links: scheduling conflict controls, combined walk-in and
  appointment workflow, and switching from paper/spreadsheets.
- Expanded FAQ coverage for independent professionals, teams, multiple locations,
  room/equipment requirements, imports and existing payment/messaging limits.
- All 13 relevant industry links plus the existing all-13 industry hub. Specialist
  claims remain on fit/limitations pages; no medical/POS/payroll claims invented.
- Moved lead, industry links, use cases and FAQs into `config/homepage.php`, shared
  by Vue/SSR and Blade fallback. The fallback no longer drops these content groups.
- Retained Organization/WebSite/WebPage/SoftwareApplication graph; no fabricated
  ratings/reviews, offer prices, regional pages or FAQ-rich-result promises.
- Retained canonical/indexation/sitemap policies, social tags, responsive WebP,
  reduced-motion behavior, HTML anchors and consent boundaries.

## Intent map (editorial targets, not measured search volumes)

| Intent | Homepage coverage | Deeper destination |
| --- | --- | --- |
| Appointment scheduling / service business software | Title, description, hero category, definition | Features and all industry pages |
| Salon / barbershop booking software | Industry links and illustrative demo | Salons / barbershops |
| Online appointment scheduling | Visible feature section | Calendar / online-booking feature pages |
| Walk-in queue and booked visits | Interactive sample + contextual explanation | Manage walk-ins and appointments |
| Double-booking / scheduling conflicts | Working-day use case and room/equipment FAQ | Reduce scheduling conflicts |
| Client management and visit history | Feature section, definition and import FAQ | Client-management feature |
| Staff / solo / multi-location scheduling | Hero, feature heading and team FAQ | Industry pages and pricing |
| Switching software / spreadsheets | Use case, migration FAQ and setup help | Paper-booking changeover guide |
| Checkout / sales reports | Recorded-payment section and truthful limitations | Checkout-and-reporting feature |
| Nail / spa / massage / fitness / pet appointments | Relevant industry links | Dedicated fit pages; all-13 hub |

Avoid trying to make the homepage rank for every specialist phrase. Maintain a broad appointment-scheduling acquisition category and distribute detailed answers through established
feature/industry/use-case pages. This is an editorial targeting strategy, not a
claim of keyword volume, difficulty, ranking, or conversion lift.

## Local evidence

- Homepage tests: 4 passed / 112 assertions, including fallback DOM FAQ copy,
  industry/use-case URLs returning 200, one H1/title/description/canonical,
  social title and expected graph types without invented reviews/ratings.
- Full Marketing suite: 303 passed, two skipped / 5,177 assertions.
- Six focused frontend/SEO-checker tests passed, including mounted demo lifecycle.
- Client and SSR builds passed; public budgets passed for 26 entries.
- Actual local full SSR HTML: 922 main words, 49 links; single title, description,
  canonical, H1, structured-data block; index/follow. Hero and demo rendered.
- Actual local fallback HTML: 555 main words, 19 links; same SEO checks passed.
  Counts describe the captured response, not minimum SEO targets. Definition copy
  was subsequently aligned with the established brand definition; DOM tests cover it.
- Root canonical check uses the framework's root URL without an optional trailing
  slash. The earlier exact-string slash mismatch was not a production canonical bug.
- Evidence: `seo-html-check.json`, `seo-ssr-check.json`.

Remaining: full mobile visual acceptance, Core Web Vitals/page-speed measurement,
production HTML/schema verification and Search Console URL Inspection after deploy.
No production change, Google rich-result certification or ranking improvement claimed.
Region/locale remains en-IN per existing OPEN-15; regional expansion is not inferred.

## Primary references reviewed

- [Google: descriptive titles, avoid keyword stuffing](https://developers.google.com/search/docs/appearance/title-link)
- [Google: useful people-first content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [Google: crawlable and descriptive links](https://developers.google.com/search/docs/crawling-indexing/links-crawlable)
- [Google: JavaScript SEO](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics)
- [Google: snippets and descriptions](https://developers.google.com/search/docs/appearance/snippet)

These guidelines support the approach; they do not certify ClipperDesk or guarantee
Google's choice of title/snippet, indexing or rankings.
