# Four published article revisions — 2026-11-05

## Authority and scope

The owner explicitly authorised reviewing and editing all four existing published
articles through the signed-in ClipperDesk administration module. All four were
backed up before editing. Changes were saved through the normal article editor,
retaining Published/Active, the original slug, author, featured image and first
publication date. No source deployment, server configuration, financial settings,
new customer records, outreach or Google indexing request was performed.

## Editorial changes

| Article | Revised purpose and substance |
| --- | --- |
| 1 — Salon/barbershop operations | Daily review and closing routine, walk-in timing example, client handoffs, five consistently defined measures and contextual product/article links. |
| 2 — Software buying guide | Accurate buying-guide title in place of an untested best-product promise; publisher disclosure, realistic trial tasks, fictional total-cost comparison, privacy/export questions and explicit current payment/deposit limits. |
| 3 — No-show reduction | Ten practical steps, an explicit denominator, fictional calculation, confirmation/reminder examples, measured 30-day plan and useful policy/calendar links. No claimed percentage reduction. |
| 4 — Salon opening | Preserved hypothetical USD69,000 budget, separated opening costs/reserve, local-quote workflow, break-even and attendance assumptions, decision-focused business plan and opening checks. |

All four have revised summaries, concise distinct search titles/descriptions,
short keyword fields, proper H2/H3 body structure and descriptive links. The
software and operating guides share the Salon Operations topic, enabling the
existing related-article section. The startup title retains 2027 planning intent;
other titles are evergreen. Original URLs intentionally retain their historical
year wording. Keywords are not treated as a Google ranking signal.

Claims were checked against `config/frontsite.php`, the public-website module
specification and the current booking/checkout feature boundaries. No expert
credentials, customer testimonials, software comparisons, results or product
screenshots were invented. Existing featured images remain. Actual customer
case studies and first-hand salon review remain opportunities requiring genuine
evidence; this edit does not establish them.

Sources checked on 2026-11-05:

- [Google people-first content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [SBA business planning, startup costs and break-even guidance](https://www.sba.gov/counseling/plan-your-business/)
- [SBA location and licensing guidance](https://www.sba.gov/counseling/launch-your-business/)
- [Google Business Profile eligibility](https://support.google.com/business/answer/13763036?hl=en)

## Verified live

- All four original public article URLs return HTTP200 and pass the existing
  original-HTML SEO inspector: one title, description, self-referencing canonical,
  index/follow, rendered H1/main content and valid JSON-LD.
- All four appear in the HTTP200 sitemap. All17 distinct linked destinations
  return HTTP200, including the three official external source pages.
- Public body text matches each proposed revision completely after normalising
  markup/whitespace. Titles, descriptions and Article schema headlines match.
- First publication dates remain 2026-11-04. Visible update dates and Article
  `dateModified` are 2026-11-05; stored UTC values match the public markup.
- Desktop1512px and phone390×844: all four render within the viewport, all
  observed article/related images load, and phone content width is358px with
  document width390px. Desktop and phone screenshots visually inspected.
- Admin listing confirms four Published/Active articles with unchanged slugs and
  original publication dates. Public archive shows revised titles and summaries.
- The installed production article renderer preserves all30 authored links and
  the intended headings in a local content check. No application source changed;
  no broad application test suite was necessary for these editorial saves.
- Temporary phone viewport was reset and the public blog was left open.

This is website and content verification, not evidence of a Google recrawl,
indexing, improved positions or increased visits. No fresh Search Console
performance measurement was taken during this edit.

## Evidence and rollback

`before-1.json` through `before-4.json` contain the original public editorial
fields and rich HTML, captured from the admin form (no session credentials).
`revisions.json` contains the intended revised content. To restore an article,
use its original fields/HTML through the normal editor while retaining its slug,
image and current publication controls; a restore is another material edit and
must not falsify the first publication or update timestamps.

- [HTTP checks](http-verification.json)
- [Desktop content, metadata and schema](browser-desktop.json)
- [Phone content and dimensions](browser-phone.json)
- [Revised public archive](blog-after.png)
- [Desktop article](operations-desktop.png)
- [Phone article body](startup-phone.png)
