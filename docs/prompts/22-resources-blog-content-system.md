# Prompt 22 — Resources, Blog and Content System

Execute after Prompt 21. Turn the existing generic blog infrastructure into a governed, scalable Good Hours resource system; quality and maintainability outrank publishing volume.

## 1. Mission

Design and implement the `/resources` and `/blog` architecture, article experience and editorial data/governance needed for useful salon-operations content that supports readers, product education, internal linking and future search growth.

## 2. Why This Phase Exists

The current Larafast blog model/pages are minimal and use generic layouts plus raw rich-content rendering. Sustainable content needs ownership, authorship, publication state, metadata, relationships, security and quality gates before articles are created at scale.

## 3. Prerequisites

Prompts 14–21 and the approved IA are complete. A content owner and editorial policy must be identified. Existing articles must be inventoried; do not assume they are accurate, licensed, safe or indexable.

## 4. Read Before Changing Anything

Read mandatory docs, prior Phase 1.5 outputs, current `Article` model/migration/controller, Filament resource, `Blog.vue`, `Article.vue`, rich-text rendering, storage/thumbnail handling, SEO/schema services, localization, user/authorship model and tests. Inspect live/seed article data safely and current routes/status codes.

## 5. Scope

- Define `/resources`, `/blog`, optional `/guides`, categories/topics and their distinct purposes.
- Refine listing, article, breadcrumb, author/date, related-content and related-product experiences.
- Add only the data fields/workflow required for approved content lifecycle: draft/review/published/updated, canonical/metadata, author attribution, topic relationships and content ownership.
- Establish cluster briefs for salon operations, booking, no-shows, staff, clients/retention, metrics, walk-ins, payments/deposits and switching software.
- Migrate/quarantine existing content safely and add editorial/technical tests.

## 6. Out of Scope

Mass article generation, a full headless CMS, comments/community, gated lead magnets, newsletters without an owned system, external syndication, and Phase 2 product promises. Do not publish placeholder cluster pages.

## 7. Product Truth

Product-specific statements use the claim ledger and current implementation evidence. Editorial advice must distinguish general guidance from Good Hours behavior and professional legal/financial advice. Statistics and external facts require authoritative source, date and attribution. Never turn speculative roadmap features into recommendations.

## 8. Information Architecture

Define responsibilities: Resources as discovery hub; Blog as chronological/editorial collection; Guides only for durable in-depth content if justified; categories as useful curated taxonomies, not tag explosion. Document route, primary intent, canonical/indexability, pagination and archive behavior. Categories/topics need enough distinct content before indexation.

## 9. UX Requirements

Users can scan content by real need, understand author/update context, navigate long articles, reach related product education and discover genuinely related reading. Provide empty/no-results/pagination states. Article reading must not be interrupted by intrusive CTAs; progress/sticky TOC only if accessible and useful.

## 10. UI / Design Requirements

Use public editorial typography/spacing, consistent cards, breadcrumbs, author/date blocks, content prose styles, callouts, tables and media captions. Avoid generic template hero imagery. Ensure rich content components cannot break layout or smuggle arbitrary styles/scripts.

## 11. Content Requirements

Define editorial brief fields: audience, intent, question, unique value, product evidence, factual sources, outline, CTA, related cluster, owner, reviewer, review date. Display published and materially updated dates accurately, not touched timestamps. Authorship must identify a real approved author/editorial entity. Establish freshness, correction, citation and retirement policies. Do not target arbitrary word counts or generate thin AI content.

## 12. SEO Requirements

Unique editorial title/description/canonical, one `h1`, semantic headings, crawlable pagination, descriptive URLs, accurate dates and useful image metadata. Define canonical policy for syndicated/duplicate content, noindex for drafts/preview/thin archives/search results, and sitemap eligibility on publication. Prevent tag/category duplication and orphan articles.

## 13. GEO / AEO Requirements

Structure articles to answer the reader's question clearly, define terms, expose evidence/citations and preserve qualifications. Create durable factual blocks/tables only when natural. Consistent entity/product language matters more than “AI-friendly” filler.

## 14. Structured Data Requirements

Implement verified Article/BlogPosting fields: headline, canonical URL, representative image, real author, publisher entity, `datePublished`, true `dateModified` and breadcrumb. Do not fabricate author bios/images, use created time as publication time, or mark all content as HowTo/FAQ. Schema must reflect visible content.

## 15. Internal Linking

Maintain cluster maps: hub/category → articles; article → related articles and relevant feature/industry/use-case/pricing pages; product pages → educational resources when useful. Relationships should be editorially selected or deterministic and relevant, not random. Detect orphan and broken links before publication.

## 16. Conversion Requirements

Use restrained in-content or end-of-article CTAs relevant to reader intent. Preserve a clear route to product/pricing/signup without turning advice into an advert. Track CTA context only through approved analytics; do not gate core articles for email.

## 17. Responsive Requirements

At 360px, prose, tables, code/quotes, media, TOC, cards, pagination and long words/URLs must reflow. Provide readable measures across tablet/desktop and support 200% zoom. Do not make sidebars required for comprehension.

## 18. Accessibility

Meet WCAG 2.2 AA: semantic article/header/time/cite elements, logical headings, descriptive links, labeled breadcrumbs/pagination, accessible tables/media, focus and contrast. CMS editors need guidance for alt text and heading order; automated sanitization cannot replace review.

## 19. Performance Requirements

Server-render article content; sanitize once appropriately and cache safely. Optimize thumbnails/hero assets, reserve dimensions, lazy-load below-fold media and avoid per-article JS. Keep listing queries paginated/eager-loaded and prevent N+1. Measure large-article HTML and bundle impact.

## 20. Analytics

Measure page/category and approved CTA/outbound-citation interactions only with privacy-safe fields and consent policy. Avoid collecting article text selections, search queries containing personal data or author/admin draft activity in public analytics. Define stable content IDs separate from mutable titles.

## 21. Security / Privacy Considerations

Audit and fix unsafe `v-html`: sanitize rich HTML server-side using an approved allowlist, prevent scripts/event handlers/unsafe URLs and test stored XSS. Secure draft previews with auth/signed expiring links and noindex. Validate uploads/MIME/storage access and do not expose author personal data inadvertently.

## 22. Implementation Instructions

1. Inventory existing schema/data/content and take reversible migration/backfill approach.
2. Record content model/lifecycle/taxonomy decision before schema changes.
3. Implement secure content rendering and publication-state routing first.
4. Build hubs/listings/articles/related modules with shared public components.
5. Add metadata/schema/linking and editorial validation gates.
6. Quarantine or repair existing articles based on audit; create at most a small representative verified fixture, not a content campaign.
7. Update architecture/IA/claim/status docs and editor guidance.

## 23. Do Not

- Do not mass-produce AI articles, create empty categories/tags or publish scraped/copied content.
- Do not trust stored HTML, expose drafts or use fake authors/dates/citations.
- Do not inflate `dateModified`, add schema types for eligibility alone or index internal search/filter permutations.
- Do not promise Phase 2 capabilities in editorial examples.

## 24. Acceptance Criteria

- Content lifecycle, taxonomy, editorial ownership and quality gates are documented and enforced.
- Listing/article routes render safe, accurate, responsive content with real authorship/dates, unique metadata, eligible schema and useful links.
- Draft/preview/archive/pagination/indexation behaviors are explicit and tested.
- Stored-XSS, upload and query risks are addressed in scope.
- No thin/mass-generated content is published; tests/build/accessibility pass.

## 25. Validation / Testing

Run model/migration/controller/page tests, authorization and draft/signed-preview tests, stored-XSS/unsafe-URL fixtures, upload validation where touched, date/canonical/pagination/schema tests, internal-link/orphan crawl, query-count checks, SSR/production build, mobile/zoom/accessibility review, console check and `git diff --check`.

## 26. Completion Report

Return these exact fields:

- Files changed
- Routes created
- Routes modified
- Components created
- Components reused
- SEO changes
- Schema changes
- Analytics changes
- Tests run
- Build result
- Warnings
- Assumptions
- Deferred items

Include content model/lifecycle, migrated/quarantined content, security fixes and whether Prompt 23 is unblocked.
