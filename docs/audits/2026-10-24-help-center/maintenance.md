# Help Center maintenance, capture and deployment

Date: 2026-10-24. This is a reproducible runbook, not a claim that the latest
capture, browser matrix or deployment has passed. Final results must be recorded
in the dated Help Center audit with the actual command outputs and rendered
screens. The repository corpus currently contains **80 articles in 14 categories**.

Read the [module contract](../../modules/help-center.md),
[setup inventory](setup-inventory.md) and
[operations inventory](operations-inventory.md) before changing content.

## Author or revise a guide

1. Read the affected module requirements and accepted decisions. Follow the
   actual user journey and inspect current page/controller fields, actions,
   permission checks and subscription gates. Source existence alone does not
   establish a complete customer-facing workflow.
2. Add or edit `content/help/{slug}.json`. Keep one focused task, its prerequisites,
   exact navigation and labels, required/optional fields, result verification and
   useful recovery. Use stable section IDs, plain Markdown, customer terms in
   `keywords`, truthful audience labels and relevant `related` links.
3. Keep unverified content `draft`. Only reviewed, correctly dated `published`
   documents are publicly eligible. Do not insert requirement/task IDs, internal
   notes, placeholders, real client information or unfinished product promises.
4. Use `action.key` from `config/help.php` for application links. Add a new key
   only after checking the named route, feature/access decision and destination
   authorization. Do not embed business IDs or secure customer tokens in articles.
5. Use a media key with accurate alternative text, caption and section assignment
   for actual screenshots. Record unavailable captures as gaps and resolve them
   before retaining a missing published asset reference.
6. Update the affected inventory, related guides, contextual ordering and search
   question fixtures. Have the module maintainer review behavior and a second
   reviewer check clarity and screenshots before publication.

`updated_at` is also the feedback revision. Change it when the reviewed guidance
materially changes. Preserve existing article slugs and section anchors whenever
possible; deliberately migrate redirects and references for any rename. The
current flat category model is intentional; introduce nested categories only with
an actual navigation need and corresponding route/SEO/content migration.

## Prepare a safe screenshot environment

Run from the repository root. The capture preparer bootstraps its own SQLite
fixture under ignored `tmp/help-center`; it never seeds the primary business
database. It enforces testing/SQLite configuration and uses array mail plus fake
client-notification transport. The seeders provide representative businesses,
services, staff, clients, appointments, sales and notification records. Private
random sign-in credentials stay in a mode-0600 ignored file and must never be
copied into public assets or command output.

```sh
php scripts/help-center/prepare-demo.php --resume
```

Without `--resume`, an existing capture database is refused instead of overwritten.
For a clean independent run, intentionally use a new capture fixture path/name in
the preparer and its server configuration, preserving any evidence needed from
previous runs. Never use `migrate:fresh` against the application’s normal database.
If cached configuration defeats the isolated settings, the preparer stops; repair
the dedicated local capture configuration rather than weakening its guard.

Start a server in a separate local terminal with the same isolated database and
fake delivery settings. The current fixture path is shown below; update both
preparer and server together when naming a new fixture.

```sh
APP_ENV=testing APP_URL=http://127.0.0.1:8139 DB_CONNECTION=sqlite DB_DATABASE=/Applications/AMPPS/www/barber_app/tmp/help-center/help-capture-v2-service-catalog-review-business-setup-review-notification-workspace-review.sqlite CACHE_STORE=array SESSION_DRIVER=file SESSION_COOKIE=clipperdesk_help_capture QUEUE_CONNECTION=sync MAIL_MAILER=array COMMUNICATIONS_TRANSPORT_MODE=fake COMMUNICATIONS_IMMEDIATE_EVENT_DISPATCH=false php artisan serve --host=127.0.0.1 --port=8139
```

Check the application actually uses this fixture before navigating authenticated
screens. Do not reuse the normal database server just because it already runs.
Keep provider transport fake and do not submit live charges or customer messages.
The browser capture blocks requests outside the specified local origin; maps or
other external content may be absent and should be documented as such.

## Capture genuine interface assets

Use Node 24 (the project requires Node >=22.12); the host’s default Node may be too
old. The desktop runtime available for this review is:

```sh
/Users/dhavalprajapati/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --version
/Users/dhavalprajapati/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node scripts/help-center/capture.mjs http://127.0.0.1:8139
```

On another machine use its supported Node runtime and installed Puppeteer. The
script defaults to local Google Chrome; set `HELP_BROWSER` to an actual supported
browser executable when needed. Its origin guard rejects remote targets. It
signs in through the real application and uses current controls rather than
fabricating screenshots. Extend its task/control sequence when adding a workflow.

Successful captures write WebP assets to `public/images/help` and entries in
`content/help-media.json`. The manifest records a stable key, public path,
width/height, capture date and SHA-256 of the exact asset. The catalogue checks
actual dimensions and any supplied hash, as well as the restricted asset path.
Copying an older image under a new key is not verification of a changed screen.

The script writes `capture-results.json` with routes, headings, overflow/broken
image observations, missing controls and page errors. It writes its successful
manifest in `finally`, so **a failed or partial run can replace the manifest with
an incomplete set**. Review the report and diff, rerun the complete set after
fixes, then run the publication validator. Do not publish the partial manifest or
silently relabel another workflow’s screenshot to hide a failed capture.

For each image, visually inspect the current screen and confirm:

- It uses recognizable synthetic business/client data and exposes no credential,
  private link, provider secret or real customer information.
- The controls and labels match the article and support the described task.
- The viewport, image dimensions, caption and meaningful alternative text are
  correct. Screenshots fit phones and enlarge through the shared lightbox.
- Any highlight explains a real control without concealing a warning or changing
  the interface. Keep an unmodified capture and provenance if annotations are added.

The completed local capture contains **30 genuine images**, all referenced by
published guides. `capture-results.json` records no missing controls, page errors,
broken loaded images or captured-page overflow. `help:validate` verifies every
published media reference, dimension and checksum. See the [final audit](README.md)
for actual browser and regression results. Detailed form/merge/refund/invitation,
MFA setup, review-confirmation and some less common controls remain text-guided;
future captures should be added where they reduce customer effort. No screenshot
of a credential, authenticator QR, private token or real customer is permitted.

## Validate publication and regression behavior

```sh
php artisan help:validate
php artisan test tests/Feature/HelpCenter
node --test tests/Frontend/*.test.mjs
npm run build
npm run check:frontsite-budgets
```

Run frontend commands with the supported Node runtime on `PATH`, not an older
host default. `help:validate` checks schemas, related articles, recognized article
links, route registry and real media integrity. Corpus tests cover every category
and realistic relevance examples, draft/future/invalid exclusion, sanitized
Markdown, no-SSR HTML, SEO/indexation, tenant-safe contextual summaries,
authentication and denied/eligible/multiple-workspace links, feedback revision
idempotency and input bounds. The wider suite remains necessary for changes to
shared navigation/SEO/access middleware.

A passing build is not browser verification. Test at desktop and 390px/320px:
public index/category/article/search; loading/no-results/network failure and
rate-limited search; keyboard suggestions; article anchors/breadcrumbs/related
links; screenshot lightbox Escape/focus restoration; contextual Help; signed-out
application links, authorized and restricted/multiple-workspace handoffs; feedback
success/failure/cooldown with real button labels. Verify no horizontal overflow,
cut-off support text or touching actions. Confirm full article HTML and metadata
without optional SSR, and ensure search/feedback/open routes remain outside the
indexed sitemap. Record what actually passed, commands, dates and limitations.

## Deploy feedback storage and review aggregates

The Help Center content is file backed. Feedback is its only new database table;
apply the additive migration through the normal reviewed deployment process
before enabling the deployed frontend feedback control:

```sh
php artisan migrate --force
php artisan help:validate
php artisan help:feedback
php artisan help:feedback --article=appointments-add
```

This runbook does not authorize an unreviewed production deployment. Confirm the
normal migration set and backup/release procedure for the target environment;
never describe a local migration or isolated test as a deployed change.

The unique feedback key is article slug + review date + opaque session HMAC.
Repeated votes update that revision’s response. The table stores no customer text,
user/business IDs, IP or browser details. The read-only aggregate command reports
Helpful/Not helpful counts by article and revision without visitor hashes. Review
low-helpfulness guides against the actual workflow and support evidence. No ticket
was created by a vote, and no email/message is sent by the feedback control.

Deploy content, media manifest, actual image files, backend routes/views and built
assets together. Production Help URLs belong to the primary website under
`/help`; verify `BRAND_WEBSITE_URL` and existing canonical policy, rather than
publishing duplicate Help pages on the booking host. Confirm real status, titles,
canonical URLs, structured data, crawlable article text and sitemap after rollout.
No local test closes existing payment/provider, legal, regional, accessibility or
other launch gates.

## When product screens change

1. Identify affected guides from these inventories and search article keywords,
   action keys and screenshot references. Review adjacent guidance, FAQs and
   troubleshooting so the same setting is not described inconsistently.
2. Update customer instructions and capture tasks alongside the UI change.
   Re-capture actual affected screens from the isolated demo; regenerate manifest
   metadata/hash and visually compare images with both desktop and phone rendering.
3. Re-run validation and relevant customer-question, permission and workflow tests.
   Keep the old revision’s feedback history; bump the article review date for the
   changed guide.
4. Update the module/status/audit evidence only with verified facts. Keep gaps or
   externally blocked behavior visible internally. If a real capability is
   removed, revise the article and related links rather than leaving a stale
   promise searchable.
5. Publish the cohesive change through the normal release process and check the
   deployed public/help-to-application journey. Only that evidence supports a
   claim about the deployed experience.
