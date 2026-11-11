# Automatic store discovery and MySQL migration recovery

2026-11-11, Asia/Kolkata. MP-01/04 and revised MPG-03/05/07.

## Report and verified cause

The owner reported error1059 in `2026_11_08_000005_create_marketplace_local_growth`.
Its automatically generated composite foreign-key name exceeded MySQL's 64-character
identifier limit. MySQL had already committed the earlier tables and attribution
columns. The attribution tenant FK, unique keys and subsequent lookup indexes were
missing, and the migration remained pending. The existing message-email migration
had already been applied. These facts came from local migration/schema inspection.

Earlier SQLite evidence did not catch this MySQL restriction. The fix assigns the
explicit name `mp_attribution_appointment_tenant_fk` and makes the unreleased migration
resume table creation. Existing rows are retained. Missing constraints and indexes
are restored from the declared schema, including fluent column indexes. Table
creation, recovery and a repeated up run are covered. Missing expected columns stop
with an explicit error.

The local migration then completed the growth, attribution-lineage and customer-photo
migrations. A subsequent `php artisan migrate` reported **Nothing to migrate**.
Local reconciliation rebuilt23 businesses with0 failures using actual eligible data.
Google indexing remains false locally.

## Owner workflow refinement

The previous deployment instructions required a growth flag, duplicate approved
origin, per-business pilot allowlist and manual individual-profile preparation.
The owner explicitly replaced this workflow with automatic discovery after setup.

- Publishing completed online booking refreshes the store's supply after commit,
  before returning when available. A failed refresh retains the durable retry work.
- Store names, qualified public services, prices and public booking addresses become
  searchable through the ordinary Marketplace routes. The normal application
  scheduler automatically refreshes existing stores and missed changes.
- Individual profile readiness/status/hash follows the saved public facts. Policy
  provenance is `automatic-profile-policy-v1`. A reviewed geography binding or
  individual operator approval is no longer required for a profile. Real public name, address and qualified services govern search-engine eligibility.
  The description remains optional. All13 audience mappings remain.
- Owners manage the existing Business setup, Services and Locations workflows.
  Marketplace listing has one optional visibility choice. The SEO request form,
  extra consent checkbox and approval/pilot notices were removed from the normal UI.
- Paused/withdrawn, unfinished/private/demo and unqualified supply guards continue
  to apply. Curated area hubs retain their existing geography/editorial workflow.
- `MARKETPLACE_LISTED_SLUGS`, `MARKETPLACE_GROWTH_ENABLED`,
  `MARKETPLACE_GROWTH_ORIGIN` and `MARKETPLACE_GROWTH_PILOT_IDS` are retired and ignored.
  The normal configuration sets discovery enabled. Canonical-origin checks reuse
  BRAND_WEBSITE_URL. One platform-wide MARKETPLACE_GROWTH_INDEXATION setting defaults
  false. The message-email flag keeps its existing behavior and default true.

This policy supersedes the per-store profile/pilot requirements in the earlier
release notes. Production indexing remains a platform deployment choice. Google
crawl/index timing has not been verified.

## Source changes

- `database/migrations/2026_11_08_000005_create_marketplace_local_growth.php`.
- `config/marketplace-growth.php`, `config/marketplace.php`, `.env.example`.
- `ProjectionWork`, `PublicSupplyProjection`, `SeoPageRegistry`, reconciliation command.
- Marketplace/owner-booking controllers and Marketplace/Business.vue.
- Handler provides route configuration when a Marketplace exception bypasses shared
  middleware. A renderer log exposed that recovery issue during verification.
- AutomaticPublicationTest, MigrationRecoveryTest, updated policy/compatibility tests
  and mounted owner-visibility test. Existing staged/user changes were preserved.

## MySQL verification and repeatable recovery

Run `php scripts/verify-marketplace-mysql-migration.php` in the local development
environment with permission to create a temporary MySQL schema. The script verifies
the resolved connection name and actual selected database before running migrations.
It creates and removes only its randomly named `codex_mpg_migration_*` schema.

The test reproduces1059 with the old identifier, retains a synthetic existing row,
finishes all migrations, verifies tenant FK/uniqueness/lookup indexes, repeats up,
and verifies fresh growth installation and all Marketplace identifier lengths.
The final [MySQL log](mysql.txt) records all these successes.

During development of this test helper, a copied connection name caused a CREATE
users statement to target the existing local schema. MySQL rejected it because that
table existed, before any application-table modification. The helper was corrected
by removing inherited connection names/read-write targets and asserting the actual
fixture database before execution. The successful final run used the checked
isolated connection and removed its synthetic database.

For the reported local failure, ordinary `php artisan migrate` now resumes the
existing tables and retains their rows. The verified local repair used that ordinary
command. Production deployment remains pending.

## Validation

| Check | Result |
| --- | --- |
| Targeted backend suite |221 passed,2 skipped,2,066 assertions |
| Built live SSR suite, including both skipped cases |35 passed,510 assertions |
| Frontend suite |244 passed |
| Native MySQL fresh/recovery/replay |Passed with retained synthetic row |
| Actual local migration |Completed, then Nothing to migrate |
| Actual local reconciliation |23 processed,0 failed |
|100-business/1,000-offer query fixture |4 SELECTs,0 live availability calls |
| Client/SSR build and frontsite budgets |Passed,25 route entries |
| Scoped PHP formatting and whitespace |Passed |

[Backend](backend.txt), [SSR](ssr.txt), [frontend](frontend.txt),
[MySQL](mysql.txt), [build](build.txt), [budgets](budgets.txt), [load](load.json). The
frontend test checks a single optional visibility form and the absence of an SEO
approval step. The normal HTTP publication test verifies immediate search and
suggestions while its background queue is faked. Tests also cover publication
rollback, unqualified supply, owner pause/resume, withdrawal, automatic content
refresh, default-off indexing, canonical host checks, sitemap and private headers.
The live renderer run covers actual initial HTML with automatic profiles, owner
settings, private appointment actions and recovery without shared middleware.

The100-business/1,000-offer synthetic retrieval fixture retains its four-query
budget and performs no live availability matching. Client/SSR build, frontsite
budgets, scoped formatting and whitespace checks are included.

CUA again returned **Local browser use is disabled in Work settings**. Actual
browser desktop/mobile visual verification remains pending. The raw-HTML and
mounted-component tests are recorded separately. No screenshot or deployed result
is claimed. No external email, Search Console operation or production deployment
was performed.

## Deployment configuration

The normal Marketplace additions in `.env.example` are now only these two values.

```dotenv
MARKETPLACE_GROWTH_INDEXATION=false
MARKETPLACE_MESSAGE_EMAIL_ENABLED=true
```

Use the existing deployment steps for migrations, client/SSR build, config cache,
managed workers and the application scheduler. Discovery has no environment rollout
step. Existing eligible stores are refreshed automatically by the scheduled sweep.
A deployment can run `php artisan marketplace:growth-reconcile --all` to refresh
immediately. Store owners perform no commands or environment edits.

The existing canonical HTTPS origin is BRAND_WEBSITE_URL. After authorized production
crawl checks, the one global indexing switch can be enabled. Eligible individual
profiles then follow ordinary publication automatically. Rollback sets indexing
false and retains private noindex/no-store plus registry, appointment and financial
history. The former pilot/duplicate-origin settings are ignored.
