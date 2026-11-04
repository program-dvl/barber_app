# Article editor and image upload repair — 2026-11-04

Scope: owner-requested repair of Content → Articles and the reported featured
image failure. Requirements: PRD sections 1–4/12/15; public-website module and
ADR-065 article restoration. No production article was saved or published, no
schema changed and no deployment/hosting setting was changed by this task.

## Findings

The supplied production screenshot shows a 2.3 MB PNG failing with Livewire's
generic `data.thumbnail.<id>` message. A local 2 MiB PHP size rejection initially
produced matching wording, but that hypothesis did not establish the live cause.
A later retry of the original **2,269,879-byte PNG** on the preserved live
`/admin/articles/create` form returned **HTTP 413, text/html**, with Nginx's
`413 Request Entity Too Large` body from
`POST /livewire-e88050f8/upload-file`. The preceding and following Livewire update
requests returned 200. The current live failure is a verified Nginx request-size
rejection before PHP; effective FPM limits and downstream storage remain unknown.

[Live failure screenshot](live-nginx-upload-error.jpg) shows the original image
and the preserved form. No cookies, signed upload URL or full form payload is
retained. A read-only Forge command to inspect inherited Nginx limits was marked
Finished, but its output returned a missing provision-output file; it did not
establish the effective limit or inspect those included files.

Read-only Forge overview inspection shows **746682a / Deployed**, push-to-deploy
**82065079**. The task did not trigger that deployment. The General site
configuration contains no explicit `client_max_body_size`, but its
`forge-conf/3340514/server/*` includes were not read. The apex server includes
`forge-conf/3340514/site.conf`, with no other body-size override visible. This
does not establish an exact effective Nginx limit.

Independent code review found these actual defects:

- Article uploads inherited the global disk. With the default local disk,
  files were stored under `storage/app/articles`, but `/storage` serves
  `storage/app/public`: a successful upload could still produce a missing image.
- The admin form omitted the existing publication fields required by the public
  query. It could save articles but could not complete publication normally.
- RichEditor saves HTML, whereas the public Markdown renderer stripped HTML;
  a representative rich-editor article rendered an empty body.
- Public Blog/Article templates ignored the supplied featured-image URL and
  displayed the same editorial stock image for every article.
- Every article save generated an unused social screenshot synchronously.
- Publication could accept a raw HTML attribute/link that the public SQL marker
  filter would exclude. Known private/temporary links and exceptionally large
  bodies needed explicit publication failures rather than leakage/truncation.

## Implemented source

The following changes were verified locally; Forge now reports their source
revision deployed. Successful production image upload is still unverified.

- Explicit public article disk; existing S3 deployments retain S3 by default,
  local storage uses `public`, and `ARTICLE_MEDIA_DISK` permits an explicit
  reviewed configuration. Temporary Livewire files use the private disk.
- JPEG/PNG/WebP and 5 MiB image validation for featured/inline images; generated
  paths, featured-path tampering protection, clear image guidance and upload
  recovery that names the image and preserves typed text. Draft saving does not
  require an image.
- `public/.user.ini` supplies PHP-FPM upload/request ceilings of 32/40 MiB,
  preserving existing 20 MiB salon-media journeys. This source file alone does
  not establish actual production limits or repair Nginx/S3 configuration.
- Summary, topic, search fields, publication status, reviewer and UTC review/
  publish controls restore the existing editorial lifecycle. Scheduled, draft,
  incomplete, blocked and inactive articles remain excluded by the shared gate.
- HTML and legacy Markdown share Symfony sanitization; headings, links, lists,
  images and tables survive without executable markup or arbitrary CSS. Public
  article/card/social images now use the saved featured image.
- Removed the synchronous unused social-image generator. Image deletion stays
  on the article disk and preserves shared/unrelated paths.
- Publication guards match raw and decoded marker rules, reject known private/
  task/temporary/signed links, require visible article text, cap source content
  at 100k characters and reject expanded HTML beyond the sanitizer safety limit.

## Verified evidence

| Check | Result |
| --- | --- |
| Marketing + administrator-resource PHP suite | **236 passed / 3,874 assertions** |
| Full existing frontend suite plus actual-component article image tests | **217 passed** |
| Production client/SSR builds using installed Node 24.19.0 | Passed |
| Public route budgets | All 21 entries passed |
| Scoped Pint and diff whitespace | Passed |
| Isolated browser: draft without image, save and reopen text | Passed before browser interruption |
| Isolated browser: original reported PNG upload and Save changes confirmation | Passed before browser interruption |
| Saved original PNG bytes | **2,269,879**, hash matches the reported original file exactly |
| Local public image URL | **HTTP 200, image/png, Content-Length 2,269,879** |
| Forge deployment overview | **746682a / Deployed**, push-to-deploy **82065079**; not triggered by this task |
| Live retry of the original PNG on the preserved create form | **413 text/html**, Nginx `413 Request Entity Too Large`; surrounding Livewire updates **200** |

The isolated fixture uses disposable SQLite/storage and a synthetic administrator
at localhost:8051, with fake/disabled providers. Normal local MySQL and real
customer records are untouched. The fixture was initially configured as testing,
which invokes Livewire's test-only temporary disk; corrected only that fixture
to local before the successful real upload. That initial fixture failure is not
attributed to production or the repaired application.

Tests exercise real decodable images larger than 2 MiB, draft/edit preservation,
publication to listing/detail/sitemap, scheduling, size/type/path rejection,
unauthorized users, HTML/Markdown/XSS behavior, private links and oversized
content. Actual Vue components are rendered to verify featured/related/card
images rather than checking source strings. Livewire tests do not run through
PHP-FPM or Nginx and cannot certify those transport limits.

## Still unfinished

- The browser extension's automated file-setting API requires Allow access to
  file URLs; native picker fallback worked locally. Browser control later became
  disabled in Work settings, then was restored before the live retry above.
  Browser access is now enabled. An attempted existing SSH connection was
  rejected for lack of an accepted key; no remote command executed.
- Inline attachment selection began locally but save/reload and public rendering
  of that actual inline upload were not completed in the browser. Automated
  content/image tests pass, but they are not that browser journey.
- A desktop production upload-error screenshot is now retained. Desktop/mobile
  publication, mobile error feedback and upload-success screenshots remain
  outstanding. No phone-layout certification is claimed.
- **No production runtime repair or successful production upload is claimed.**
  Forge reports the source deployed, but Nginx rejects the original upload.
  No real article was saved/published and no hosting setting was changed by this
  task. The owner's earlier hosting/deployment hold remains in effect.

## Prepared hosting repair — not applied

Add `client_max_body_size 40m;` immediately after `charset utf-8;` in the General
site configuration's existing `server` context, preserving every other setting.
The [context-only patch](nginx-upload-limit.patch) is prepared for review. Check
included/location overrides, then use Forge's validation/save/reload flow after
the owner explicitly clears the hosting hold. This aligns Nginx with the source
POST ceiling while article validation stays 5 MiB. It does not prove effective
FPM settings or storage success. See the [deployment and rollback runbook](../../deployment/article-media.md).

## Continue from here

1. Finish the isolated editor's inline-image,
   publication, error recovery and desktop/390px checks, capturing actual screens.
2. After the hosting hold is explicitly cleared, validate/apply the prepared
   Nginx repair and verify media configuration/storage link plus FPM limits using the
   [article-media runbook](../../deployment/article-media.md). Never expose a
   client-private disk or publish a synthetic production article for testing.
3. Upload the original 2.3 MB image to an authorized draft, wait for completion,
   save/reopen and confirm its actual public image URL. Only then report the
   deployed upload problem resolved. Preserve the owner's entered article text.

Machine-readable local evidence: `local-evidence.json`.
