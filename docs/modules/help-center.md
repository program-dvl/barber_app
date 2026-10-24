# Help Center and product knowledge base

Scope: customer-facing guidance for implemented FR-01–FR-20 workflows under FR-21, PRD sections
10, 12, 14 and 17, subject to existing module requirements and release gates.
The owner requested this module on 2026-10-24; it documents current behavior and
does not promote deferred domain features. Requirements remain in
[product-requirements](../product-requirements.md); accepted decisions remain in
[decisions](../decisions.md). Apply the [design system](../design-system.md) and
[product experience](product-experience.md) contracts.

## Purpose and current corpus

The public Help Center helps business owners and team members complete existing
tasks. A shared Help entry in the authenticated workspace offers relevant guides
without placing customer information in the knowledge base or its search index.
The source corpus contains **80 articles across 14 categories**, including
step-by-step guides, explanations, FAQs and recovery instructions. It covers
setup, business/location configuration, team/access/availability, services,
appointments/public booking, Calendar, queue, clients, checkout, reports,
notifications, billing and account security. Retail documentation explains the
current availability boundary instead of presenting unfinished setup as usable.

The audit inventories map source evidence, dependencies and requirements to
published content:

- [Setup, workforce, catalogue, billing and account inventory](../audits/2026-10-24-help-center/setup-inventory.md).
- [Operational, CRM, commerce, reporting and notification inventory](../audits/2026-10-24-help-center/operations-inventory.md).
- [Maintenance, capture and deployment runbook](../audits/2026-10-24-help-center/maintenance.md).

Counts describe the repository content, not deployed publication or external
provider certification. Final capture/browser/test evidence belongs in the dated
audit; this specification does not independently certify those results.

## Architecture and ownership

Use the existing Laravel/Vue modular monolith. `app/Domain/HelpCenter` owns
`HelpCatalog`, `HelpSearch` and `HelpContext`; `HelpCenterController` composes the
public experience and guarded application handoffs. `resources/js/Pages/Help`
and shared Help components use the existing public shell, semantic tokens,
`AppNotice` and native-dialog focus behavior. Public header/footer and the shop
header expose Help through existing navigation.

Articles are reviewed JSON documents containing Markdown sections in
`content/help/{slug}.json`. `config/help.php` owns categories, search synonyms and
an allow-list of application actions. `content/help-media.json` maps screenshot
keys to actual local assets under `public/images/help`. No new external search
service, generative-answer service, tenant-data index or content-editing panel is
required. Retained Larafast editorial resources are not trusted as a knowledge
base CMS without a separate lifecycle/authorization audit.

The product maintainer for the affected module owns factual accuracy; reviewers
must check current UI labels, controller requirements, access and relevant domain
rules. Every feature or UI change must update affected guides, screenshots,
relationships and inventory in the same reviewed change. Review evidence stays
in internal documentation rather than appearing in customer copy. If a workflow
cannot be verified, keep its proposed article in draft or clearly describe the
actual user-visible limit; never silently advertise planned behavior.

## Content contract

| Field | Contract |
| --- | --- |
| `slug` | Stable lowercase hyphenated identifier, at most 120 characters, matching the filename. |
| `title`, `summary` | Plain customer language describing one useful task or question. |
| `category` | One slug declared in `config/help.php`; categories are currently flat. |
| `status` | `draft` or `published`; draft content is excluded from public detail, search and sitemap. |
| `updated_at` | Valid `YYYY-MM-DD` review/revision date; future-dated articles are not publicly eligible. |
| `order` | Optional integer ordering, default 100; used for category and contextual ordering. |
| `roles` | Human-readable audience labels; these explain relevance and never grant access. |
| `keywords` | Customer terms, task phrases and alternative vocabulary for relevance. |
| `sections` | Nonempty ordered list of `{id,title,body}`. IDs are unique stable anchors; body is Markdown. |
| `related` | Published article slugs; keep a meaningful connected next step. |
| `action` | Optional `{key,label}` using the configured action registry, never an arbitrary URL. |
| `screenshots` | Optional `{key,alt,caption,section}` references; section must exist and the key must resolve to validated actual media. |

The catalogue validates structure and strips raw HTML and unsafe Markdown links
when rendering. Invalid articles fail closed. Missing/unverified screenshots are
omitted at runtime, while `help:validate` fails publication validation until the
asset is valid or the reference is deliberately removed with a recorded gap.
The validator also checks article relationships, recognized inline article links,
registered application routes, image dimensions and any supplied SHA-256 digest.
It cannot establish factual accuracy, consent or the visual quality of an image;
those require human/source and rendered-screen review.

Article revisions currently use `updated_at`; keep slugs and section IDs stable
when editing. A breaking slug change needs an intentional redirect/link migration
rather than leaving stale Help links. There is no invented customer-facing
version selector or automatic content translation.

## Search and discovery

Search uses the reviewed public article corpus only. Weighted matching favors
titles, keywords and summaries over body mentions, adds category relevance,
accounts for term rarity and query coverage, and supports synonyms, word prefixes
and bounded edit-distance misspellings. The endpoint caps query length, terms and
result count. Suggestions are debounced, cancel stale requests and support
keyboard selection. Search remains useful without a network response through
category browsing and the normal search form.

Examples exercised by the corpus tests include “How do I add another location?”,
“staff”, “employee”, “add worker”, “locaton”, “no time slots”, “cancel subscription”
and “refund payment”. Changes to vocabulary or ranking should extend these
customer-question fixtures, not merely assert that a search returns something.

Local matching is appropriate for the present 80-article corpus. Growing the
library requires measuring request cost and relevance against an expanded
fixture set. Add a precomputed local index/cache or existing database full-text
search only when measurements justify it; any external service requires an
explicit cost/privacy/operations decision. Do not market this deterministic
retrieval as a conversational AI that knows account-specific facts.

`HelpContext` maps actual route families to categories and shares only the first
three ordered article summaries. It contains no client names, account questions,
financial records or private resource links. The app’s **Help** entry exposes
these guides, all guides for that topic and the full search page.

## Public routing, SEO and access

- `/help` is the public Help Center on the primary application/marketing host.
- `/help/category/{category}` and `/help/articles/{slug}` are stable content URLs.
- `/help/search` supplies bounded JSON suggestions; `/help?q=...` renders results.
- `/help/open/{action}` requires sign-in, the existing authenticated session check
  and verified email. It checks active business memberships through
  `WorkspaceAccessService` before building a destination. Multiple workspaces or
  a restricted feature produce an understandable selection/access state.
- Actual destination controllers and their existing membership, location,
  feature and subscription rules remain authoritative. The Help action cannot
  upgrade entitlements, bypass authorization or alter another business.

Public article pages use central SEO policy, unique titles/descriptions,
query-free canonicals, visible article content, TechArticle and breadcrumb
structured data. Eligible Help URLs enter the curated sitemap. Search variants,
JSON suggestions, feedback and authenticated handoffs are not indexed content
pages. Production canonical identity follows `config/brand.php` through the
existing `PublicUrl` policy; Help does not belong on a tenant booking subdomain.
A complete HTML fallback renders the same article sections when optional Inertia
SSR is unavailable; public guidance must remain crawlable and useful.

Documentation can explain a restricted capability publicly, but every article
must distinguish current access from availability. Business type selects prepared
starter content rather than secretly changing permissions. Current capacity
billing and retained historical subscriptions must not be described as an active
Starter/Pro picker. The complete inventory editing workspace (Products CSV import is available), commission editing, cash-close creation,
public deposit collection, provider refunds, destructive privacy work and Phase 2
benefits retain the specific limitations recorded in the inventories.

## Feedback and operations

**Was this article helpful?** records a boolean response to the article revision.
The session receives a random opaque visitor key; storage contains its keyed
hash, article slug, revision, boolean and timestamps. It stores no account ID,
business ID, IP address, question text or browser details in feedback rows. The
same session can update one response for the same revision. CSRF and a dedicated
rate limit protect writes; frontend feedback states retain reading/navigation and
respect an explained retry cooldown. Search has a separate rate-limit budget.

The additive `2026_10_24_000001_create_help_article_feedback` migration must run
before deployed feedback use. `php artisan help:feedback` gives aggregate counts
by article/revision; `--article=slug` narrows the review without exporting visitor
hashes. Feedback is a prioritization signal, not a support ticket or a statistical
claim about all readers. No free-text support inbox was invented.

## Quality and maintenance gate

Run `php artisan help:validate`, the Help Center feature/corpus tests, appropriate
existing regression tests, frontend checks and client/SSR builds. Verify public
HTML, metadata, sitemap, search relevance, authorization handoffs, feedback,
keyboard access and small-screen layouts. Review genuine screenshot content and
its current UI match separately from file integrity. Capture only isolated
synthetic data; never real customer records, passwords, secure links or provider
secrets.

The [maintenance runbook](../audits/2026-10-24-help-center/maintenance.md) describes
the reproducible demo, capture commands, final-evidence requirements, deployment
sequence and change process. Missing screenshots and broader external release
gates stay explicit until the corresponding evidence exists.
