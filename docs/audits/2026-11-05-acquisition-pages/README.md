# Public acquisition pages — 2026-11-05

## Scope

Owner-authorised acquisition work, preserving broad appointment-led positioning
and all thirteen existing industry destinations. Salons and barbershops remain
leading examples. No invented testimonials, case studies, customer logos, results,
competitive rankings, specialist features or financial capabilities.

- Home: explicit broad fit, real calendar screenshot and walkthrough link.
- Salons/barbershops: distinct working examples, four buying FAQs each, real demo
  screenshots, retained booking/payment/specialist limits and normal trial links.
- Four shared feature pages: real screenshot, practical example, concise FAQs,
  early trial/walkthrough links and existing features/limits intact.
- Pricing: business-type coverage, possible extra costs and accurate feature copy;
  current amounts, offers, calculation and subscription behaviour unchanged.
- Existing Resources hub: distinct daily checklist and walkthrough discovery.
- New `/features/product-walkthrough`: five read-only stages with genuine public
  demo images, feature and Help links, sample-data captions and trial checks.
- New `/guides/daily-operations-checklist`: five sections, ten operating checks.
- All three maintained guides: printable title/body/checklist, shared AppNotice.

The walkthrough uses the existing reserved Product prefix, avoiding any new root
slug reservation that could displace an existing tenant booking address. New pages
join the curated sitemap/indexation/alias policies. Prior-opt-in public page-view
measurement extends to the new curated path; CTA allowlists and private exclusions
are retained. No provider, environment, schema or real business data changes.

## Local checks

- Marketing: **282 passed / 4,412 assertions**.
- Frontend: **219 passed**.
- Production client/SSR builds and **22** public route asset budgets pass.
- Scoped PHP formatting and whitespace checks pass.
- Nine reviewed paths at desktop1512px, tablet768px and phone390×844 have one H1,
  expected canonical and no horizontal overflow; no loaded image failures.
- Real demo media reuse the existing file, size and SHA256 validation. Missing
  media fail closed without failing the text page (automated regression).
- Screenshot enlargement works at desktop and phone: Escape/Close restore focus;
  phone dialog width374.4px, close target44px and document width390px.
- Opened the salon deposit/loyalty FAQ and verified the real limiting copy.
- Inertia navigation from walkthrough to checklist updates title/canonical with
  one description. Guide print-media emulation retains header/title in black and
  hides action buttons. This checks print CSS, not an exported PDF file.
- Isolated local preview uses disposable SQLite and storage; SSR is disabled in
  that preview. Production SSR checks are a separate post-deployment requirement.

[Desktop results](local-desktop.json), [tablet/phone results](local-responsive.json),
[phone notice](checklist-phone.png), [phone product proof](salons-phone.png).
Existing demo media provenance is recorded in the
[Help capture audit](../2026-10-24-help-center/README.md).

## Deployment

Pending. Local validation does not establish live deployment, Google crawling,
indexing or increased traffic. Post-deployment evidence will be appended here.
