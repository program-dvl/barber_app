# Larafast adoption audit

Audit date: 2026-08-10

Scope: Evidence-backed review of the Larafast boilerplate as a foundation for
the Phase 1 barber shop and salon management SaaS. This audit changes
documentation only. It does not approve or implement salon behavior and does
not remove existing boilerplate.

Related requirements: FR-01, FR-05, FR-19, FR-20, and the cross-cutting
requirements in PRD Sections 11 and 12.

## Executive recommendation

Adopt the Laravel, Inertia/Vue, Filament, Fortify, queue, notification, and test
tooling foundations, but do not adopt Larafast's product model as the salon
product model.

Introduce an explicit `Business` tenant aggregate. Keep `User`, `Membership`,
and `StaffProfile` separate. Make `Business` the SaaS billing owner. Replace the
currently inactive Jetstream Team storage and global Spatie role assignment
with business-scoped membership and authorization. Reuse Jetstream/Fortify UI
and action patterns only where their behavior is retained deliberately.

The recommendation has a lower migration cost now than adapting Team because
no salon module or production tenant data is established. It also avoids
encoding personal-team semantics, user-owned subscriptions, global roles, and a
single hidden `current_team_id` into every later route, job, file, export, and
financial record.

Before implementation, the repository needs a small baseline-safety slice:
isolate the test database, update vulnerable locked dependencies, and add
regression tests for the currently exposed authorization and authentication
risks. Removal of Team, billing-provider, marketing, AI, or content code remains
subject to explicit approval.

## Evidence reviewed

- Root `AGENTS.md` and all foundation documents named by Prompt 00, including
  the full 883-line PRD and all module specifications.
- `composer.json`, `composer.lock`, `package.json`, and `package-lock.json`.
- 28 repository migration files plus package-provided Lemon Squeezy migrations,
  16 application models, policies, providers, controllers, middleware,
  listeners, services, commands, factories, and seeders.
- All route source files and the booted route collection: 128 routes total, of
  which `route:list --except-vendor` identifies 74 application-owned or
  application-resource routes. The admin prefix contributes 47 routes.
- 14 Filament resources (69 PHP files including pages, widgets, schemas, and
  relation managers), 31 Vue pages, and 73 Vue components.
- 23 feature-test files and 5 other test support/unit files.
- `.env.example`, `.env.testing.example`, ignore rules, and a tracked-secret
  pattern scan. The real `.env` is untracked and ignored. No recognized live
  token pattern was found in tracked source; example provider credentials are
  placeholders. This is not a substitute for repository-history or deployed
  secret scanning.
- Current local application boot evidence: PHP 8.4.8, MySQL configured, sync
  queue, database sessions, file cache, public storage link absent, Sentry DSN
  absent, UTC application time zone.
- Current security-advisory services through `composer audit --locked --no-dev`
  and `npm audit --json`.

## Classification key

| Classification | Audit meaning |
| --- | --- |
| Keep | Retain as a foundation with routine maintenance and verification. |
| Adapt | Retain the capability, but change ownership, scope, terminology, behavior, or tests before production use. |
| Replace | The current implementation or schema should not become the production domain implementation. |
| Remove | Recommended for removal after approval because it is unrelated, broken, duplicated, or avoidable surface area. |
| Defer | Keep quarantined until a named product/provider decision is approved; do not build new behavior on it. |

No `Remove` or `Replace` entry authorizes a code or schema change by itself.

## Feature adoption inventory

| Existing capability | Evidence | Classification | Adoption condition |
| --- | --- | --- | --- |
| Laravel application, Eloquent, migrations, events, queues, scheduler, notifications | Framework 13.8.0 is locked and application boots | Keep | Patch locked vulnerabilities before implementation; add production-engine integration evidence. |
| Inertia/Vue staff application shell | Inertia Laravel 2.0.24, Vue adapter 2.3.23, Vue 3.5.34 | Adapt | Replace generic navigation and pages with tenant-explicit shop surfaces; preserve accessible responsive primitives where useful. |
| Tailwind/DaisyUI styling | Tailwind 4.1.14 and DaisyUI 5.1.27 | Adapt | Establish the product design system and compile only required DaisyUI themes; current Filament theme bundle is 1.76 MB uncompressed. |
| Fortify password login, password reset, password confirmation, profile update, and 2FA | Enabled in `config/fortify.php`; related tests mostly pass | Keep/Adapt | Require verified owner/staff identity as approved; enforce stronger platform-admin authentication and session revocation. |
| Email verification | Feature and routes are enabled, but `User` does not implement `MustVerifyEmail` | Adapt | Restore an enforceable verification contract and add middleware regression tests. Current `verified` middleware does not prove verification for this model. |
| Magic-link login | Public unthrottled POST creates users with unvalidated email; signed link is replayable during its lifetime | Replace | Use a validated, throttled, hashed, single-use, purpose-bound login intent. Do not create staff/owner accounts merely by requesting a link. |
| Social login | Dynamic `{driver}` routes and email-based account linking without an allow-list or verified-email check | Defer | OPEN-08 must choose launch methods; retained providers need an allow-list and explicit safe account-linking rules. |
| Jetstream profile photos and browser-session management | Enabled and represented in profile pages | Adapt | Profile photos may remain public for staff avatars; client and operational attachments require a separate private tenant-scoped service. |
| Jetstream API tokens | Package/models/pages/tests exist; feature is disabled | Defer | Do not expose until a Phase 1 API use case, tenant-scoped abilities, expiry, rotation, and audit rules are approved. |
| Jetstream Team membership and invitations | Models, migrations, actions, pages, and tests exist; Team and invitations are disabled in configuration | Replace | Use explicit Business, Membership, StaffProfile, and Invitation records. Do not turn Team on as a shortcut. |
| Personal team creation | Registration action always creates a personal Team while Team routes/UI are disabled | Replace | Business creation must be an idempotent onboarding use case, not an implicit personal-team side effect. |
| Account deletion | Enabled Jetstream action deletes owned teams and user data | Replace | Use a retention-aware closure/anonymisation workflow that preserves business, financial, consent, and audit history. |
| Spatie roles and permissions | Package 6.25.0, global role tables, `permission.teams=false` | Adapt | Separate platform roles from business membership roles; scope business permissions by Business and optionally Location. |
| Filament platform-admin shell | Filament 5.6.2, `/admin`, 14 resources | Adapt | Keep Filament for platform operations only; require platform role, strong authentication, granular policies, support-access workflow, and audit. |
| Subscription entitlement middleware | `Subscribed` exists but its route group is empty and returns a URL string instead of a redirect response | Replace | Implement provider-neutral subscription state and entitlement enforcement on Business after OPEN-04. |
| Stripe/Cashier subscription and one-off checkout | Active `Billable` trait on User, routes, commands, webhooks, products/prices/orders | Defer | Candidate only. If selected, move SaaS billing ownership to Business and replace generic one-off commerce semantics. |
| Lemon Squeezy subscription and order flow | Package is `dev-main` at locked commit; routes, webhook, tables, resources, and factories exist | Defer | Candidate only. Pin a stable supported release and make Business billable if approved. Remove otherwise. |
| Paddle flow | Public and authenticated routes/controller/pages exist, but `Laravel\\Paddle\\Checkout` is not installed | Remove | Disable/remove after approval. The current endpoints will fail when invoked. |
| Generic Invoice and InvoiceItem models, admin CRUD, PDF/email support | User-owned mutable invoice tables, global number sequence, unauthorised download binding | Replace/Adapt | Replace schema and lifecycle with tenant-owned immutable/provider-normalized invoices; adapt the PDF rendering/template only if it meets receipt/invoice requirements. |
| Sentry error reporting | Package and logging configuration present; local DSN missing | Keep/Adapt | Configure per environment, redact tenant/client data, add correlation IDs, and define alert ownership. |
| Email/Resend support | SMTP active locally; Resend SDK and mail transport available | Defer | Select launch transactional-email provider as part of communications design; retain application-owned notification records. |
| Browsershot/Puppeteer PDF and OG rendering | Used by invoices and public OG endpoint | Adapt | Keep only for controlled server-side documents if operationally justified; sandbox rendering, bound resource use, and update vulnerable Puppeteer dependencies. |
| Blog, changelog, roadmap voting, and coming-soon collection | Public routes/pages, global tables, admin CRUD | Remove | Not Phase 1 operations. Retain only through an explicit go-to-market decision; use an external content system if that reduces product attack surface. |
| Generic marketing homepage, plans, partners, integrations, testimonials, FAQ | Public home renders Larafast copy and generic integration claims | Replace | Prompt 01 should create approved salon product identity and claims. |
| Dynamic OG image and live sitemap generation | Public routes invoke browser rendering/crawling without rate limits | Remove/Adapt | Prefer prebuilt/cached assets. If retained, validate inputs, rate-limit, cache, and prevent request-time crawling. |
| OpenAI and xAI services | Service classes/config exist; xAI provider registered; Phase 2 defers AI receptionist/features | Remove | Remove after approval; no Phase 1 requirement justifies provider secrets or long-timeout clients. |
| Setup, Stripe catalogue, OG, and sitemap console commands | Boilerplate operational commands | Remove/Adapt | Remove provider/content-specific commands with their features; retain only deliberate, idempotent operational commands. |
| Boilerplate database seeding | Creates about 300 Lemon orders and 100 additional users, with swallowed exceptions | Replace | Build small deterministic, tenant-safe factories and scenario seeders; never use this seeder as a production-like fixture. |

## PHP dependency inventory

All direct manifest entries are listed below; Fortify is also called out because
it owns enabled authentication behavior through Jetstream. Lock files are
version authority. `Keep` and `Adapt` packages still require security updates
before implementation.

| Dependency | Locked version | Classification | Reason |
| --- | ---: | --- | --- |
| PHP runtime | Local 8.4.8; manifest `^8.3` | Keep | Supported application runtime; deployment version still needs an explicit baseline. |
| `laravel/framework` | 13.8.0 | Keep | Core platform; current lock has advisories and must be updated. |
| `inertiajs/inertia-laravel` | 2.0.24 | Keep | Staff/public web delivery foundation. |
| `filament/filament` | 5.6.2 | Adapt | Platform admin foundation; current lock has high/medium advisories. |
| `livewire/livewire` | 4.3.0 | Keep | Required by Filament. |
| `laravel/fortify` | Transitive through Jetstream | Keep/Adapt | Authentication backend foundation. |
| `laravel/jetstream` | 5.5.2 | Adapt | Reuse auth/profile scaffolding; replace Team domain semantics. |
| `laravel/sanctum` | 4.3.2 | Adapt | Current session guard support; API tokens remain deferred. |
| `spatie/laravel-permission` | 6.25.0 | Adapt | Retain permission engine with explicit platform/business scoping. |
| `laravel/cashier` | 16.5.3 | Defer | Stripe candidate pending OPEN-04 and Business billing ownership. |
| `lemonsqueezy/laravel` | `dev-main` at `7e8721d` | Defer | Lemon Squeezy candidate; unstable branch constraint and duplicate schema. |
| `laravel/socialite` | 5.27.0 | Defer | Pending launch authentication decision. |
| `resend/resend-php` | 0.13.0 | Defer | Pending launch email-provider decision. |
| `sentry/sentry-laravel` | 4.25.1 | Keep/Adapt | Error monitoring foundation with privacy controls. |
| `spatie/browsershot` | 5.3.0 | Adapt | Possible document renderer; current public OG use is inappropriate. |
| `spatie/image` | 3.9.4 | Defer | Retain only if approved image processing needs it directly. |
| `cviebrock/eloquent-sluggable` | 13.0.0 | Adapt | Could serve booking-page slug creation; aliases and tenant rules are still missing. |
| `spatie/laravel-sitemap` | 7.4.0 | Remove | Current request-time sitemap is marketing-only surface. |
| `spatie/schema-org` | 3.23.1 | Remove | Blog/marketing-only; locked version has an advisory. |
| `guzzlehttp/guzzle` | 7.10.0 | Remove direct requirement | No direct application import; Laravel HTTP clients cover current needs. It may remain transitively. Locked version has advisories. |
| `tightenco/ziggy` | 1.8.2 | Keep/Adapt | Used by Vue route generation; verify compatibility during upgrades. |
| `laravel/tinker` | 3.0.2 | Adapt | Development/support tool; do not treat direct database editing as an operational workflow. Consider moving to development-only. |

| Direct development dependency | Locked version | Classification | Reason |
| --- | ---: | --- | --- |
| `fakerphp/faker` | 1.24.1 | Keep | Factories and test data. |
| `laravel/boost` | 2.4.6 | Keep dev-only | Development tooling; never expose its route in production. |
| `laravel/envoy` | 2.12.2 | Defer | Retain only if deployment topology selects it. |
| `laravel/pint` | 1.29.1 | Keep | Formatter baseline. |
| `laravel/sail` | 1.58.0 | Defer | Retain only if Docker becomes the supported local workflow. |
| `mockery/mockery` | 1.6.12 | Keep | Test double support. |
| `nunomaduro/collision` | 8.9.4 | Keep dev-only | Test/console diagnostics. |
| `pestphp/pest` | 4.7.0 | Keep | Primary test runner. |
| `phpunit/phpunit` | 12.5.24 | Keep | Underlying test framework. |
| `spatie/laravel-ignition` | 2.12.0 | Keep dev-only | Local diagnostics; production execution routes must remain disabled. |

## Direct frontend dependency inventory

| Direct dependency | Locked version | Classification | Reason |
| --- | ---: | --- | --- |
| `vue` | 3.5.34 | Keep | Primary frontend framework. |
| `@inertiajs/vue3` | 2.3.23 | Keep | Inertia Vue adapter. |
| `@vue/server-renderer` | 3.5.34 | Keep/Adapt | SSR build passes; deploy SSR only with an owned operating model. |
| `tailwindcss` | 4.1.14 | Keep | Styling foundation. |
| `@tailwindcss/postcss` | 4.1.14 | Keep | Active PostCSS integration. |
| `@tailwindcss/forms` | 0.5.11 | Keep | Imported in application CSS. |
| `@tailwindcss/typography` | 0.5.19 | Defer | Current use is blog/content focused; retain only if operator/help content needs it. |
| `daisyui` | 5.1.27 | Adapt | Useful primitives, but all themes are currently compiled. |
| `@vitejs/plugin-vue` | 5.2.4 | Keep | Vue build integration. |
| `laravel-vite-plugin` | 1.3.0 | Keep | Laravel asset build integration. |
| `vite` | 5.1.6 | Keep/Update | Build passes, but the locked version has high-risk advisories. |
| `postcss` | 8.5.14 | Keep/Update | Active build dependency with advisories. |
| `axios` | 1.16.0 | Keep/Update | Used by security/profile flows; audit reports high risk. |
| `@heroicons/vue` | 2.1.3 | Keep | Used by components. |
| `laravel-vue-i18n` | 2.7.7 | Keep/Adapt | Useful localization foundation; locale ownership remains unimplemented. |
| `moment` | 2.30.1 | Replace | Used only for display in content pages; salon time logic must use explicit location-zone services and modern browser formatting. |
| `puppeteer` | 22.7.1 | Adapt/Update | Browsershot runtime; audit reports high-risk transitive dependencies. |
| `autoprefixer` | 10.4.21 | Remove | Not configured in the current PostCSS pipeline. |
| `theme-change` | 2.5.0 | Remove | No source import found. |

## Database and migration inventory

`migrate:status` shows all repository migrations as run in the configured local
database and also shows the Lemon Squeezy package's license-key migrations. The
audit did not mutate or inspect row data.

| Tables/columns | Current ownership/semantics | Classification | Target disposition |
| --- | --- | --- | --- |
| `migrations` | Framework migration history | Keep | Keep. |
| `users` core identity columns | Global authenticating person | Adapt | Keep User identity; normalize verified contact behavior and remove business billing/tenant state. |
| `users.current_team_id`, `trial_is_used`, Stripe customer/payment/trial columns | Hidden Team context and user-owned billing | Replace | Move active Business choice to explicit context and SaaS billing to Business/provider records. |
| `password_reset_tokens` | Fortify password recovery | Keep | Keep with rate limiting and notification tests. |
| `sessions` | Database sessions linked to User | Adapt | Keep; add prompt access revocation evidence and platform-admin session controls. |
| `personal_access_tokens` | Sanctum API tokens | Defer | Keep dormant until API decision; scope and audit before enabling. |
| `failed_jobs` | Queue failure payloads | Adapt | Keep; payloads need tenant/correlation context and sensitive-data controls. |
| `teams`, `team_user`, `team_invitations` | Jetstream Team owner/pivot/invitation | Replace | Supersede with Business, Membership, StaffProfile, Invitation, and location assignments after ADR approval. |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Global Spatie authorization (`teams=false`) | Adapt | Separate platform roles from business-scoped role assignments. |
| `subscriptions`, `subscription_items` | Cashier Stripe subscription owned by User | Replace/Defer | Replace ownership with Business; final physical schema follows OPEN-04. |
| `lemon_squeezy_customers`, `lemon_squeezy_subscriptions`, `lemon_squeezy_orders` | Parallel provider-owned User commerce | Defer | Keep only if Lemon Squeezy is selected, then make Business billable and normalize operational views. |
| `lemon_squeezy_license_keys`, `lemon_squeezy_license_key_instances` | Package-provided software licensing | Remove | No Phase 1 requirement. |
| `products`, `prices`, `stripe_orders` | Stripe SaaS/one-off provider catalogue | Replace/Defer | Never reuse as salon services/products/sales. Retain provider projections only if Stripe is selected. |
| `invoices`, `invoice_items` | Mutable global invoice, nullable User owner, global number | Replace | Tenant-owned immutable SaaS invoice/read model or provider projection; appointment receipts belong to Money and Commerce. |
| `social_accounts` | User/provider identity and encrypted access token | Defer/Adapt | Add provider/account uniqueness, verified linking, token minimization, revocation, and provider decision. |
| `articles` | Global blog posts with public thumbnail | Remove | Marketing-only. |
| `changelogs` | Global rendered Markdown | Remove | Marketing-only; do not confuse with immutable audit history. |
| `coming_soon_emails` | Global lead emails | Remove | Marketing-only and lacks consent/source/retention evidence. |
| `feature_requests` | Unused older feature request table | Remove | No active route/model behavior beyond an empty controller. |
| `roadmaps`, `roadmap_votes` | Global public product-roadmap voting | Remove | Not Phase 1 salon operations. |

Schema-specific concerns:

- No current application table provides a canonical Business, Location,
  Membership, StaffProfile, SupportAccess, AuditEvent, tenant-scoped file, job,
  export, or entitlement record.
- Several old foreign IDs are not constrained (`subscriptions.user_id`, Team
  pivots, `current_team_id`) and therefore do not prove referential ownership.
- `is_admin` remains in the User model's fillable/casts and access fallback but
  no repository migration creates that column on a fresh database.
- Generic invoices allow create/edit operations and cascade-delete invoice
  lines. This does not satisfy append-only financial-history rules.
- Provider tables overlap while representing different ownership models. They
  are not a safe abstraction merely because all are present.

## Route and page inventory

### Public and shop routes

| Route family | Current surface | Classification | Finding |
| --- | --- | --- | --- |
| `/`, `/dashboard` | Generic marketing home and authenticated Larafast dashboard | Replace | Neither represents approved salon IA. Dashboard is authenticated/verified but not tenant scoped. |
| Fortify login/register/reset/verify/2FA routes | Authentication | Keep/Adapt | Core flows are useful; verification enforcement and role-specific policies need correction. |
| `/auth/magic-link*` | Passwordless login | Replace | Unvalidated, unthrottled user creation and replayable link. |
| `/auth/redirect/{driver}`, `/auth/callback/{driver}` | Socialite | Defer | No driver allow-list or safe verified-email linking. |
| `/user/profile`, password/2FA/session routes | Account security | Keep/Adapt | Retain appropriate self-service; account deletion must become retention-aware. |
| `/api/user` | Authenticated identity endpoint | Defer | API product surface is not approved. |
| Team and API-token routes/pages | Inactive Jetstream code | Replace/Defer | Routes are absent because both features are off; related Team tests still run and fail. |
| `/stripe/*`, Cashier webhook | Stripe checkout/billing | Defer | User-owned and provider-specific pending OPEN-04. |
| `/lemonsqueezy/*`, package webhook | Lemon checkout/billing | Defer | Parallel provider path pending OPEN-04. |
| `/paddle/*` | Paddle checkout/swap/cancel | Remove | Package class is absent; one checkout endpoint is public. |
| `/invoices/{invoice}/download` | Invoice PDF | Replace immediately after approval | Authenticated route performs unscoped implicit binding with no policy/ownership check: any authenticated verified user can request another invoice ID. |
| `/blog*`, `/changelog`, `/roadmap*`, `/coming-soon` | Public content/leads/voting | Remove | Unrelated global surfaces and tables. |
| `/og-image*`, `/sitemap` | Dynamic browser rendering and request-time crawler | Remove/Adapt | Public expensive work has no specific throttling/caching. |
| `/terms-of-service`, `/privacy-policy` | Generic Jetstream legal pages | Adapt | Replace only after launch-market legal/privacy decisions. |
| Empty `Subscribed` route group | Subscription gate | Replace | No protected routes; middleware response is incorrect. |

The booted collection also includes expected framework/package routes for
Livewire, Filament imports/exports, Sanctum CSRF, Ignition, Boost, Cashier, and
Lemon Squeezy. Keep package routes only when the owning package is approved and
environment controls prevent development routes from being exposed.

### Vue page groups

| Pages/components | Classification | Disposition |
| --- | --- | --- |
| Auth pages (`Login`, `Register`, password reset/confirm, verification, 2FA) | Adapt | Reuse accessibility/layout pieces; align methods and verification with OPEN-08. |
| Profile page and security partials | Adapt | Retain profile/password/2FA/session controls; replace destructive account deletion. |
| `AppLayout.vue` and generic primitives | Adapt | Build tenant-explicit navigation and permission-aware staff shell. |
| API token page/manager | Defer | Dormant while API support is off. |
| Team create/show/member-manager pages | Replace | Supersede with Business staff/membership/invitation UX. |
| `Home`, `Dashboard`, `Blog`, `Article`, `Changelog`, `ComingSoon`, `Roadmap` | Replace/Remove | Generic Larafast product/content surfaces. |
| `TermsOfService`, `PrivacyPolicy` | Adapt | Replace copy through launch-market review. |
| Paddle components | Remove | Broken/uninstalled provider surface with hard-coded price IDs. |
| Generic marketing components and images | Remove/Replace | Preserve only primitives selected in Prompt 01; remove provider logos and claims after approval. |

### Filament resources

| Resource group | Classification | Disposition |
| --- | --- | --- |
| Users, Roles, Permissions | Adapt | Platform identity/role administration only; add policies, stronger auth, audit, and separate business-role workflows. |
| Invoices | Replace/Adapt renderer | Current editable global invoice CRUD is unsafe for historical commercial records. |
| Stripe Orders/Subscriptions, Products, Prices | Defer | Keep only if Stripe is selected and then expose normalized, least-privileged support operations. |
| Lemon Squeezy Orders/Subscriptions | Defer | Keep only if Lemon Squeezy is selected. |
| Articles, Changelogs, Roadmaps, Coming Soon Emails | Remove | Marketing/content surfaces unrelated to Phase 1 operations. |
| Revenue/User/Subscription/activity widgets | Replace | Current global metrics are not tenant-safe operational or platform metrics. |

No non-Team Eloquent policies were found. Filament therefore relies mainly on
the broad `User::canAccessPanel()` check and resource defaults. That is not
evidence of least privilege.

## Provider integration inventory

| Provider/integration | Classification | Evidence and condition |
| --- | --- | --- |
| Stripe/Cashier | Defer | Active User billing trait, routes, webhook listener, commands, and tables. Candidate for SaaS billing or appointment payments only after separate decisions; the same integration should not silently own both domains. |
| Lemon Squeezy | Defer | Auto-discovered package, parallel customer/subscription/order/license schema and admin resources. Candidate for SaaS billing only. |
| Paddle | Remove | Environment keys/routes/UI remain but Composer package is absent. |
| SMTP/Resend | Defer | SMTP configured locally; Resend SDK available. Select and contract-test launch email delivery later. |
| Sentry | Keep/Adapt | Installed and configured, but local DSN absent. Add tenant-safe context and PII redaction. |
| GitHub/Twitter/Google Socialite | Defer | Provider keys and dynamic driver routes exist. Retain only approved drivers with verified account linking. |
| OpenAI/xAI | Remove | Phase 2 AI scope; xAI provider is unnecessarily registered. |
| AWS/S3-compatible storage | Adapt/Defer provider | Configuration exists. Private object storage is required eventually, but provider/topology remains a decision. |
| Pusher/broadcasting | Defer | Example keys exist, Broadcast provider is disabled, and local driver is log. Decide only with a real-time requirement. |
| Browsershot/Puppeteer | Adapt | Restrict to controlled document generation; remove public on-demand rendering or add hard resource controls. |

## Tenant model comparison

| Concern | Adapt Jetstream Team as tenant | Explicit Business/Tenant | Recommendation |
| --- | --- | --- | --- |
| Domain language | Requires translating Team into Business throughout UI and code while retaining package assumptions such as personal teams | Matches PRD and domain model directly | Business |
| Tenant lifecycle | Team creation/deletion actions assume collaboration workspace semantics and destructive purge | Purpose-built registration, trial, suspension, closure, retention, and export states | Business |
| Billing ownership | Current Cashier ownership is User; adapting Team also requires making Team billable and migrating provider links | Business is naturally the SaaS customer/billable; owners are authorized actors, not commercial owners | Business |
| User membership | `team_user` pivot has nullable role and little lifecycle/audit data | First-class Membership can hold status, invitation lineage, joined/left times, role, and policy context | Business |
| Staff profile | Team membership encourages equating login with worker | StaffProfile belongs to Business and optionally User, supporting non-login staff and historical attribution | Business |
| Invitations | Jetstream invitation has email/role and signed URL but no explicit expiry/revocation/staff-profile binding fields | Purpose-built invitation can hash token, expire/revoke, bind Business and StaffProfile, and record acceptance evidence | Business |
| Roles and permissions | Jetstream role strings and global Spatie roles overlap; package Team scoping is currently disabled | One explicit business membership-role model plus separate platform roles | Business |
| Multi-location | Team has no location assignment or location-aware role semantics | Business owns Locations; Membership can receive location assignments without redefining tenant | Business |
| Route binding | Hidden `current_team_id` is convenient but makes ambiguous URLs and unsafe global model binding likely | Explicit `{business}` context plus scoped bindings makes ownership visible and testable | Business |
| Jobs/webhooks | Team context must be reconstructed from package state; current user-centric billing events are ambiguous | Jobs/events carry immutable `business_id`, correlation/idempotency keys, and provider account reference | Business |
| Files | Jetstream profile disk concerns User avatars, not tenant files | Keys and metadata can be rooted under immutable Business ID with authorized expiring access | Business |
| Exports/imports | No Team-owned job/export record exists | Business-owned ImportJob/ExportJob can enforce scope, expiry, cancellation, and audit | Business |
| Support access | Team owner/admin semantics do not model platform support | Separate SupportAccess grant enters an explicit Business context with reason, scope, expiry, banner, and audit | Business |
| Package upgrades | Less custom code initially, but core package semantics become domain coupling | More application-owned code, but upgrades do not redefine the tenant aggregate | Business |
| Migration cost now | Superficially low, but still requires billing, staff, roles, location, audit, and deletion redesign | Moderate initial schema/action work | Business because no salon production data exists |
| Migration cost later | High: every Team foreign key, job, file, export, invoice, and provider record must move | Low if established before salon migrations | Business |

### Recommended ownership model

- `User` is an authenticating person or service identity.
- `Business` is the canonical tenant, SaaS customer, entitlement owner, and
  root for tenant data.
- `Membership` is a first-class grant from User to Business. It owns business
  role/status and may have location assignments.
- `StaffProfile` is a schedulable worker belonging to Business and may have a
  nullable unique link to a User within that Business.
- `Invitation` belongs to Business and may target a StaffProfile. It stores a
  normalized email, token digest, intended role/locations, expiry, revocation,
  inviter, and acceptance evidence.
- `Location` belongs to Business from the first business migration.
- SaaS `Subscription`, provider customer references, invoices, and entitlements
  belong to Business. Appointment payments remain a separate Money and Commerce
  provider boundary.
- Platform roles are global and rare. Business roles are tenant scoped. A
  platform role never silently grants tenant access.

### Context propagation rules

- Authenticated shop routes should carry an explicit Business identifier or
  resolve a single-business convenience URL into an explicit `TenantContext`.
  Scoped bindings must query child records through Business relationships.
- Queue jobs, scheduled commands, notifications, webhooks, imports, and exports
  must include immutable Business ID and correlation/idempotency identity; they
  may not infer tenant solely from the current user or a serialized child ID.
- File object keys and file metadata start with immutable Business ID. Downloads
  use policy checks and expiring URLs/responses.
- Cache keys, locks, search documents, logs, metrics, and export filenames
  include tenant context without leaking sensitive names.

## Architecture and security risks

### Critical

| Risk | Evidence | Required response |
| --- | --- | --- |
| Locked dependency graph has known exploitable advisories | Composer: 45 advisories affecting 19 production packages, including high findings in Filament, Guzzle, Laravel, CommonMark, and Symfony. npm: 19 vulnerable packages (2 critical, 14 high, 3 moderate), including direct Axios, PostCSS, Puppeteer, and Vite findings. | Update locks in an isolated maintenance slice, review major-version effects, rerun tests/build/audits, and block implementation/deployment until high/critical findings are cleared or explicitly risk-accepted. |

### High

| Risk | Evidence | Required response |
| --- | --- | --- |
| Plain test command can refresh the configured development database | `phpunit.xml` sets only `APP_ENV`; no `.env.testing` exists; most feature tests use `RefreshDatabase`; local default is MySQL | Create an enforced isolated test database configuration before any routine test run or CI. |
| No canonical tenant boundary exists | All current business-like records are global or User-owned; no Business/Location/TenantContext model/policy exists | Approve ADR-006 and implement the tenancy foundation before salon tables. |
| Authenticated invoice IDOR | Download controller accepts any bound Invoice and has no policy/owner check | Disable or policy-scope route in the first approved security slice; add cross-user/tenant denial tests. |
| Global mass-assignment protection is disabled | `AppServiceProvider::boot()` calls `Model::unguard()` | Remove after verifying explicit fillable/guarded behavior; add mass-assignment tests for sensitive models. |
| Authentication methods do not meet verified identity expectations | User omits `MustVerifyEmail`; magic link auto-creates; Socialite has arbitrary driver and unsafe email linking | Resolve OPEN-08 and harden retained methods before onboarding implementation. |
| Parallel billing paths have incompatible ownership and lifecycle | Stripe User billing, Lemon polymorphic billing, Paddle dead routes, two subscription schemas, provider-specific admin resources | Approve Business billing ownership and one launch provider; quarantine all other routes/jobs/webhooks before subscription work. |
| Platform admin is broad and lacks support-access controls | `canAccessPanel()` accepts global `admin` role or stale `is_admin`; no platform MFA policy, support grant, banner, reason, expiry, or audit | Approve proposed platform-access ADR; build support access as a separate audited workflow. |
| Route binding and authorization are mostly unscoped | Only Team policy exists; shop records and admin resources lack policies; implicit binding is global | Establish TenantContext, scoped binding, policies, and cross-tenant tests before domain CRUD. |

### Medium

| Risk | Evidence | Required response |
| --- | --- | --- |
| Public expensive endpoints can amplify resource use | OG rendering launches a browser; sitemap route crawls at request time; neither has specific throttling/caching | Remove or precompute; otherwise enforce input bounds, rate limits, cache, timeouts, and operational monitoring. |
| Provider webhook/listener behavior is not application-idempotent | Stripe listener can send duplicate notifications; no application ProviderEvent/dedup record; Lemon listener is placeholder | Add normalized provider-event ingestion, deduplication, ordering, reconciliation, and replay evidence after provider selection. |
| Financial records are mutable and user/global owned | Filament exposes invoice create/edit; invoice deletion cascades items; manual totals and global numbering | Replace with Business-owned immutable snapshots/adjustments consistent with ADR-004. |
| Stored HTML/Markdown surfaces need sanitization review | Blog and changelog render database content with Vue `v-html`; locked Markdown/sanitizer packages have advisories | Remove content surfaces or sanitize with patched dependencies and tests. |
| Sync queue and missing storage link are not a launch operating model | `artisan about` reports sync queue and missing `public/storage`; failed-job visibility alone is insufficient | Select queue/storage topology, define private file disks, workers, retry/dead-letter visibility, and health checks. |
| Large unused product surface obscures ownership | 31 pages, 73 components, 14 admin resources, three billing routes, content/AI/provider code | Approve ordered removal only after tenant/provider/auth/marketing decisions. |
| Stale documentation and comments can cause incorrect adoption | `CLAUDE.md` version summary is stale; comments claim Team/AI/provider behavior that is disabled or unregistered | Keep lock-derived architecture/status evidence current; remove stale summaries during approved cleanup. |

### Lower or currently contained

- `.env` is ignored and untracked; tracked examples use empty, null, variable,
  or obvious provider placeholders. No recognized live token pattern was found
  in tracked files. Deployed secret storage, rotation, repository history, and
  CI log exposure remain unverified.
- Local Sentry has no DSN and PII sending is disabled. Production redaction and
  tenant-safe context still need design.
- Broadcast provider is disabled and the driver is `log`, so real-time behavior
  is not currently implemented or verified.

## Test gaps

The current suite primarily covers boilerplate authentication, profile, Team,
roadmap, and invoice arithmetic. It does not provide evidence for:

- tenant isolation through direct records, scoped bindings, search, admin,
  jobs, caches, files, imports, or exports;
- Business membership, staff-profile separation, location assignment,
  entitlement enforcement, or session revocation after access removal;
- magic-link throttling/single use, Socialite allow-list/account linking, or
  verified middleware enforcement;
- invoice ownership denial, admin policy boundaries, platform MFA, or support
  access;
- webhook signature rejection, duplicate/out-of-order events, idempotency,
  reconciliation, or provider failure recovery;
- private attachments, expiring links, audit events, or sensitive-data
  redaction;
- MySQL concurrency/locking; the safe audit run used SQLite and is not capacity
  evidence;
- accessibility, responsive journeys, browser compatibility, load, backup, or
  restore behavior.

## Proposed target structure

Keep one Laravel application and database. Make module boundaries visible
without creating package repositories or microservices.

```text
app/
  Domain/
    PlatformAccess/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
    BusinessConfiguration/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
    SchedulingOperations/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
    ClientRecords/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
    Communications/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
    MoneyCommerce/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
    ReportingInsights/
      Actions/ Contracts/ Data/ Events/ Jobs/ Models/ Policies/ Services/
  Support/
    Audit/ Files/ Idempotency/ Money/ Observability/ Tenancy/ Time/
  Http/
    Controllers/Shop/ Controllers/PublicBooking/ Controllers/Webhooks/
    Middleware/
  Filament/
    Platform/Resources/ Platform/Pages/ Platform/Widgets/
  Providers/
database/
  factories/<module>/
  migrations/
  seeders/
resources/js/
  Components/<shared-or-module>/
  Layouts/
  Pages/Shop/ Pages/Booking/ Pages/Account/
routes/
  web.php  shop.php  booking.php  webhooks.php  api.php
tests/
  Unit/Domain/<module>/
  Feature/Shop/ Feature/Booking/ Feature/Platform/
  Integration/Database/ Integration/Providers/
  Architecture/
```

Guidance:

- Use normal Laravel service providers, policies, form requests, commands,
  events, jobs, notifications, factories, and migrations. Do not introduce a
  second framework or a custom repository abstraction for ordinary Eloquent
  access.
- Controllers and Filament/Inertia actions call application/domain actions.
  High-risk rules do not live only in controllers, Vue components, or model
  observers.
- Cross-module communication uses explicit actions, contracts, events, and
  read models; cross-module writes may share database transactions under
  accepted ADR-002.
- The existing `App\\Models\\User` can remain at its conventional location if
  moving it provides no value; new domain-owned models should live with their
  modules consistently.
- Add architectural tests for forbidden dependencies and tenant lineage only
  after the first two modules establish stable examples.

## Ordered cleanup plan

Each step is deliberately small. Steps that remove or replace behavior require
explicit approval.

1. **Baseline safety, no salon features:** create an isolated testing
   configuration; prevent accidental non-test database refresh; define one
   test/format/build command set; update vulnerable locked packages; rerun
   advisory scans and all baselines. Add regression tests for verified
   middleware, invoice ownership denial, and absence of exposed Paddle routes.
2. **Approve tenant and identity records:** decide proposed ADR-006 and ADR-008.
   Only then introduce Business, Membership, StaffProfile, Invitation,
   Location skeleton, TenantContext, policies, and cross-tenant test helpers.
   Do not add scheduling/catalogue behavior.
3. **Retire Team coupling:** after Business migration evidence exists, migrate
   any meaningful local Team relationships, remove automatic personal-team
   creation, inactive Team pages/actions/tests/routes, and `current_team_id`.
4. **Choose SaaS billing provider:** resolve OPEN-04 and proposed ADR-007.
   Make Business billable through an application-owned adapter; quarantine or
   remove all non-selected provider routes, webhooks, models, migrations,
   resources, commands, stubs, environment keys, and package dependencies.
5. **Choose authentication methods:** resolve OPEN-08. Harden retained password,
   magic-link, and/or social flows; remove the rest and their keys/UI/tests.
6. **Choose product-owned public surfaces:** resolve OPEN-01 and OPEN-09.
   Replace the generic shell and remove unapproved blog, roadmap, changelog,
   coming-soon, OG, sitemap, AI/xAI, and Paddle surfaces.
7. **Establish platform operations:** retain Filament but replace provider and
   global widgets/resources with policies, platform roles, support grants,
   audit, health, and provider-neutral failure inspection as later prompts
   require.

Database removal strategy must be selected from real deployment state. If no
production data exists, a clean pre-launch schema baseline is cheaper and
safer than carrying dead provider tables forever. If any shared environment
contains meaningful data, use additive migrations, explicit backfill evidence,
and later destructive migrations with backups and rollback plans.

## Verification baseline

Commands were run on 2026-08-10 in the repository root.

| Check | Exact command/evidence | Result |
| --- | --- | --- |
| Runtime/application | `php artisan about` | Laravel 13.8.0; PHP 8.4.8; local MySQL; sync queue; database sessions; UTC; debug enabled; storage link absent; Sentry DSN missing. |
| Routes | `php artisan route:list --json` | Booted successfully; 128 routes, including 47 admin routes. |
| Migrations | `php artisan migrate:status` | 30 migrations reported as run: 28 repository migrations plus 2 Lemon package license migrations. No migration was executed. |
| Tests | `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=':memory:' CACHE_STORE=array CACHE_DRIVER=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array php artisan test` | Failed: 41 passed, 10 failed, 6 skipped; 117 assertions; 5.50 s. All failures are Team tests while Team support is disabled. API-token and invitation tests skip because features are disabled. |
| Default test safety | `phpunit.xml`, tracked environment files, `RefreshDatabase` scan | Plain `php artisan test` was intentionally not run: it would inherit the configured MySQL database because `.env.testing` is absent. |
| Formatter | `vendor/bin/pint --test` | Failed; 19 files require formatting. No files were rewritten. |
| Frontend production build | `npm run build` | Passed client and SSR builds; 1,194 client modules and 98 SSR modules transformed. CSS optimizer emitted two `@property` warnings. |
| PHP dependency audit | `composer audit --locked --no-dev --format=plain` | Failed security gate: 45 advisories affecting 19 packages. |
| Frontend dependency audit | `npm audit --json` | Failed security gate: 19 vulnerable packages: 2 critical, 14 high, 3 moderate; fixes reported available. |
| Secret hygiene | ignored/tracked env check and tracked token-pattern scan | Real `.env` is ignored/untracked; examples contain placeholders; no recognized live token pattern found in tracked source. |

The SQLite test run proves only the listed boilerplate behavior. It does not
provide MySQL concurrency, tenant isolation, provider, privacy, or salon-domain
evidence.

## Decisions requested

Recommended approval order:

1. Approve proposed ADR-006: explicit Business is the canonical tenant; do not
   adapt Jetstream Team as the domain tenant.
2. Approve proposed ADR-008: User, Membership, and StaffProfile remain distinct,
   with platform roles separate from tenant roles.
3. Approve the baseline-safety cleanup slice (test isolation, dependency
   updates, and focused security regression tests).
4. Select one SaaS billing provider under OPEN-04 and approve proposed ADR-007.
5. Resolve OPEN-08 authentication methods and OPEN-09 public/marketing surface
   retention before removal work.

The smallest next slice is item 3. It changes no salon behavior and does not
depend on brand, launch market, appointment gateway, or messaging decisions.
