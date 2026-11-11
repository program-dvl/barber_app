# Marketplace local growth release candidate

Updated locally on 2026-11-11 (Asia/Kolkata). Requirements MPG-01–10 and MP-05.
**Stage E authorization is pending. No deployment, public enrollment, indexation,
Search Console change, campaign, Google account operation or spending occurred.**

The working tree includes an earlier uncommitted Marketplace/customer foundation.
Preserve and review that dependency with this candidate; the source manifest is a
review aid, not permission to overwrite unrelated work. The review archive contains
documentation/evidence, not a database or a deployable build.

## Current automatic-publication policy

The 2026-11-11 owner refinement replaces manual profile review, per-store pilot IDs
and the separate growth origin. Publishing a completed bookable store refreshes its
search listing and prepares its individual profile automatically. Discovery runs
without an environment enable flag. Existing stores are refreshed by the scheduler.
Area hubs retain reviewed geography/editorial content. The existing canonical host
comes from BRAND_WEBSITE_URL. One default-off Google indexing switch remains.

```dotenv
MARKETPLACE_GROWTH_INDEXATION=false
MARKETPLACE_MESSAGE_EMAIL_ENABLED=true
```

Retired slug, growth-enabled, growth-origin and pilot-ID variables have no effect.
Owners complete ordinary Business setup and may pause their listing. They perform
no deployment configuration or separate profile approval. See the
[automatic-publication and MySQL audit](../../audits/2026-11-11-marketplace-automatic-publication/README.md).

Current verification passed221 backend tests with2 live-renderer cases skipped in
that run,2,066 assertions. The separate built SSR run passed35/510 including those
cases.244 frontend tests, build/budgets and native MySQL fresh/partial-recovery/replay
checks passed. Local migration recovery is complete. Browser visuals remain blocked
by Work settings.

## What is included

- All 13 Solutions audiences through the versioned audience/family/concept graph;
  tenant service mappings, reviewed aliases and owner-reviewed stable geography.
- Public service/branch projection with durable generation work, after-commit jobs,
  retry, expiry and scheduled reconciliation. Live eligibility/tenant guards fail
  closed; ambiguous services retain exact-name discovery.
- Bounded directory retrieval and suggestions, local-v1 ordering, a limited new
  business exploration slot and labelled farther-away alternatives. Date checks
  cover at most 12 candidates. No new search provider.
- Registry-only country/city/audience/service pages; existing provider booking
  canonical; real slug aliases, HTML/SSR-outage content, schema and sitemap shards.
- Separate consented discovery observation, owner link label and operational entry;
  owner-only appointment/ledger outcomes and aggregate Search Console import.
- Private customer raster photos with avatar selection and compact account actions;
  standard optional-cookie wording. No public customer image directory.

Relevant migrations (in addition to the uncommitted Marketplace foundation):
`2026_11_08_000005_create_marketplace_local_growth`,
`2026_11_08_000006_add_marketplace_attribution_lineage`, and
`2026_11_09_000001_add_marketplace_customer_photo`.
The first creates the geography, service mapping, projection/work/terms, SEO registry,
attribution and aggregate search tables. The second adds tenant-constrained logical
booking lineage. The third adds a private customer photo path. These are unreleased
candidate migrations; do not edit a migration already applied in a released system.
No migration seeds public providers, approves pages or enables indexing.

## Local evidence and honest limits

See the [audit](../../audits/2026-11-08-marketplace-local-growth/README.md),
[validation results](../../audits/2026-11-08-marketplace-local-growth/validation.json),
[raw crawl](../../audits/2026-11-08-marketplace-local-growth/crawl-local.json), and
[load fixture](../../audits/2026-11-08-marketplace-local-growth/load-local.json).
They establish local behavior, not Google indexing/ranking, acquisition or revenue.
SQLite query measurements do not establish production-engine locking or throughput.

Earlier real Chrome desktop/mobile captures cover local pages and cookie choices.
The final corner-removal dialog/compact account revision, owner publication/report screens and full
visual booking journey could not be rechecked: CUA returned exactly
“Local browser use is disabled in Work settings”. Automated component/backend tests
are recorded separately. Automated native upload was also blocked earlier by the
extension’s file-URL permission; the user selected a preview manually. No automated
browser upload-save success is claimed. Restore browser access and close these
visual gates before release; do not replace them with synthetic screenshots.

## Operator inputs required before production

- Accountable release owner and the canonical HTTPS primary origin already configured
  in BRAND_WEBSITE_URL. `pilot-cohort.csv` is an optional measurement template.
- Reviewed geography/editorial content for any curated area hubs being released.
  Individual stores use their existing public booking address automatically.
- Updated privacy disclosure approval/version/effective date and operations review
  for the acquisition purpose/retention and customer photos. Existing privacy
  approval flags are not renewed or satisfied by this local candidate.
- Production-engine migration/rollback rehearsal and concurrent booking regression,
  queue/scheduler health, backups and recovery, final browser/accessibility review.
- Exact-origin HTTP/noindex/canonical/sitemap checks in the authorized release
  environment and the existing deployment/search-indexing launch gate.
- Optional Search Console property read/export authorization. Its absence does not
  block directory/booking functionality. Never invent aggregate performance.

Only after these inputs and review should an authorized operator request explicit
production release/indexation approval. This package does not grant it.

## Safe rollout sequence (commands for later review, not run in production)

1. Preserve a backup and source revision, review pending migrations with their
   foundation dependencies, and rehearse against an isolated copy with fake mail.
   Run `php artisan migrate --pretend`, then normal reviewed deployment migrations.
   Never point `prepare-marketplace-growth-qa.php` at any existing/live database.
2. Keep MARKETPLACE_GROWTH_INDEXATION=false during deployment verification. Message
   alerts default true. Preserve the existing production mailer and canonical origin.
3. Build client/SSR with supported Node, refresh config and restart managed workers
   and SSR through the existing deployment. Keep the application scheduler running.
4. The scheduler discovers and refreshes existing published businesses. To refresh
   them immediately during deployment, run `php artisan marketplace:growth-reconcile --all`.
   New store publication refreshes after commit before returning when available.
   Failed refreshes retain durable retry work for the scheduler/worker.
5. Individual profile records and hashes follow public facts automatically. Curated
   country/city/audience/service hubs can use the existing geography/page commands.
   Those editorial commands are separate from a store's normal setup.
6. Verify correct/wrong hosts, raw HTML, private headers, aliases, sitemap and rollback.
   Set MARKETPLACE_GROWTH_INDEXATION=true once the authorized platform launch checks
   pass. The canonical host is checked against BRAND_WEBSITE_URL. No per-store list
   is required. Search Console operations remain separately authorized.

## Publication and request policy

| Surface/state | Delivery | Index/meta/header | Sitemap | Cache |
| --- | --- | --- | --- | --- |
| Eligible profile, automatically evaluated | Existing direct canonical booking | noindex | absent | private no-store HTTP; public facts only in projection |
| Automatically ready profile + global indexing | Same booking page | index/follow | profile shard | same privacy policy |
| Approved useful hub + all gates | Real listed inventory | index/follow | hub shard | same privacy policy |
| Previously approved hub below 3 but nonempty | Useful truthful list; 7-day editorial review window | follows approval within window, otherwise noindex | only while indexable | same |
| Hub empty | Honest empty recovery | noindex | absent | same |
| Search/filter/date/map/query | Bounded results | noindex | absent | same |
| Clean valid pagination | Distinct current page | conditional self canonical | base registry page only | same |
| Owner pause / SEO pause / withdrawal / closure | Removed; profile 404 | noindex | absent | dirty/eligible guards invalidate facts |
| Real old slug | 301 to actual equivalent | target policy | alias absent | no private link sharing |
| Unknown city/service/profile/out-of-range page | 404 | noindex | absent | private no-store |
| Account/token/hold/login/preview/API | Existing authorized behavior | always noindex | absent | private no-store even on enable/rollback |

Editorial thresholds and the seven-day review window are local policy, not Google
requirements. Slot scarcity is not closure. A withdrawn Marketplace listing does
not cancel appointments or disable the owner’s working booking link.

## Repair and rollback while preserving history

- Failed/missed projection: inspect `marketplace_projection_work` attempts/retry/
  failure reference; repair the canonical source, then reconcile that business.
  Jobs re-read current truth; replaying an old job cannot restore an old snapshot.
  The minute sweep repairs missed pivot/temporal work. Keep failure logging private.
- Wrong mapping/address: fix the existing Services/Locations workflow and reconcile.
  Address changes invalidate the previous approved binding. Review revised profile
  content again for curated hubs. Individual profile hashes refresh automatically.
- Immediate indexing rollback: set indexation false, retain migrations and history,
  refresh config and verify private-page noindex/no-store plus empty growth sitemap.
- Functionality rollback uses the previous reviewed application revision. Preserve
  registry/work/attribution/appointment/ledger/photo records. Keep indexing false
  during recovery and verify private headers. Schema downs are reserved for isolated
  development rehearsals.
- Individual withdrawal: apply explicit paused/withdrawn status through the reviewed
  page command and/or existing owner listing pause, reconcile and fetch directory,
  profile, aliases and sitemap. Keep review/audit history and direct owner bookings.
- Optional evidence retention: `php artisan marketplace:growth-prune` removes expired
  source evidence to unknown after 60 days while preserving necessary entry and
  operational/financial history. Consent withdrawal clears session-linked optional
  evidence immediately. No fingerprint or raw-query recovery is permitted.
- Customer photo repair: use the authenticated profile selection/removal endpoint;
  images are private normalized 256px JPEGs. Do not expose the storage directory or
  copy a customer image to a provider page. Financial records are unrelated.

## Aggregate search import interface

`search-performance.json` is an empty array until an authorized real export exists.
Rows contain only `day`, canonical registry `path`, `impressions`, `clicks`, optional
`position`. No query dimensions, identifiers, tokens or contacts. Use:

```sh
php artisan marketplace:growth-search-import search-performance.json --property=https://APPROVED_ORIGIN --source=AUTHORIZED_EXPORT_REFERENCE --reviewer=REVIEWER
```

Dry-run validation is default; `--apply` saves idempotently by property/day/path.
Source/reviewer is retained, dates cannot be future and counts must be valid.
The owner report suppresses imported counts below three and labels profile-wide
coverage separately from branch booking outcomes. No Google request is sent.

## Pilot operation and evaluation

Use [weekly-operations.md](weekly-operations.md) and [pilot-report.md](pilot-report.md).
Record unknowns and denominators. Compare relevant completed visits and owner
outcomes only after a real authorized cohort exists; no competitor superiority,
guaranteed ranking, incremental revenue or causal lift claim is part of this release.


### Candidate update: Marketplace booking shell — 2026-11-09

The shared Marketplace header/logo/navigation/canvas/footer now continues onto
the canonical business booking page, with the configured business accent confined
to its content. Latest frontend239 tests and real SSR9/144 pass; see the
[continuity evidence](../../audits/2026-11-09-marketplace-booking-shell/README.md).
Final actual desktop/mobile verification remains blocked by Work browser settings.
The production indexing choice follows the platform deployment checks. The review manifest
and documentation archive were refreshed for this candidate.

### Candidate update: focused Messages and customer continuity — 2026-11-09

Business Messages is separated from listing/publication/outcome controls, which are
now linked from Business setup. New booking messages produce durable transactional
email alerts in both directions, with one verified main owner recipient for customer
messages. Email branding embeds the Marketplace raster logo; private customer
management/actions/forms and receipt HTML share Marketplace identity. See the
[audit, synthetic HTML/MIME samples and recovery runbook](../../audits/2026-11-09-marketplace-messages-email/README.md).

Latest local checks:174 targeted backend passed/1 skipped/1,622 assertions;
separate built SSR28/383,243 frontend passed, build/budgets/whitespace pass.
The skipped SSR case passes in the separate renderer run. Local additive migration
`2026_11_09_000002_add_marketplace_message_email` is applied; authorized deployment
must apply it and deploy the packaged PNG/templates together. No historical alert
backfill. Worker/scheduler/first-party mail origin and sender require operator checks.
`MARKETPLACE_MESSAGE_EMAIL_ENABLED=false` stops new/queued alerts while preserving
conversations and delivery evidence; never automatically resend unknown/accepted
mail or roll back history. Private noindex/no-store survives enable/rollback.

Actual desktop/mobile and delivered-email visual verification is still blocked by
Work browser settings. No external email, deployment, Search Console/indexation
change or production authorization was performed. Canonical-origin, disclosure and production-engine checks remain. The 2026-11-11
policy retires the per-store cohort gate. Source hashes and the documentation
review archive are refreshed; they include shared preexisting working-tree changes
and are not a clean patch or production database export.
