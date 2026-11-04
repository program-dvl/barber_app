# Article editor and image upload repair — 2026-11-04

Scope: owner-requested repair of Content → Articles and the reported featured
image failure. Requirements: PRD sections 1–4/12/15; public-website module and
ADR-065 article restoration. The owner explicitly approved the narrow Nginx
upload repair after the initial hosting hold. This task then saved the owner's
existing entered article once as a draft and verified its image. No article was
published, schema changed or source deployment triggered by this task.

## Findings

The supplied production screenshot shows a 2.3 MB PNG failing with Livewire's
generic `data.thumbnail.<id>` message. A local 2 MiB PHP size rejection initially
produced matching wording, but that hypothesis did not establish the live cause.
A later retry of the original **2,269,879-byte PNG** on the preserved live
`/admin/articles/create` form returned **HTTP 413, text/html**, with Nginx's
`413 Request Entity Too Large` body from
`POST /livewire-e88050f8/upload-file`. The preceding and following Livewire update
requests returned 200. This established a Nginx request-size rejection before
PHP. After the approved Nginx repair, upload, draft save/reopen and durable image
retrieval succeeded. Effective FPM INI values were not independently inspected.

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

The following initial changes were verified locally; Forge reports their source
revision deployed. Production featured-image upload is now verified below.

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
- Initially restored summary, topic, search fields and manual editorial lifecycle
  controls. The subsequent status-only refinement below replaces manual reviewer/
  date entry; the shared public-content and authorization gates remain.
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
| Forge deployment overview | Initial source **746682a / Deployed**, **82065079**; later **271a34e / Deployed**, **82066589**; neither triggered by this task |
| Live upload before the hosting repair | **413 text/html**, Nginx `413 Request Entity Too Large`; surrounding Livewire updates **200** |
| Approved Nginx repair | Added only `client_max_body_size 40m;`; Forge reload finished; reopened file matches the approved content apart from Forge trimming final newlines |
| Original live PNG after repair | FilePond **Upload complete**, then one successful draft save and reopen of article **1** |
| Preserved entered work | Title and full RichEditor HTML (**16,051 characters**) match exactly after reopening; summary/topic unchanged |
| Durable production image | **200 / image/png / 2,269,879 bytes / 1672×941**; SHA-256 matches the original exactly |
| Draft privacy | Saved list reports **draft**; public detail **404**; absent from blog and the **127-URL** sitemap |

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

## Production upload repair — completed

The owner answered **Yes, apply this upload fix**. Added
`client_max_body_size 40m;` immediately after `charset utf-8;` in General site
configuration's existing server context. Before editing, the complete current
editor fragment matched the reviewed version. Before saving, full readback
confirmed this was the only addition. Forge reported updating/reloading Nginx,
then completed; reopening verified the saved content, with only final blank-line
normalization by Forge. [Saved hosting limit](live-nginx-upload-limit.jpg).

Retried the already-selected original image without reloading the owner's form.
FilePond reported Upload complete. The old upload validation message remained
until Save; the reopened draft has no upload error. Saved once as Draft, reopened
the resulting article **1**, and compared the title and complete editor HTML in
memory only. [Reopened saved image](live-featured-image-saved.jpg).

The durable `/storage/articles/01M6K178HVACEF3V2FFYEY5P0H.png` returned the original
PNG bytes. SHA-256: `23d78f585869ebe0214521b5517be7d7a7f04de2da5fd5011b46d2a061c0ec7c`.
The article remains absent from the blog/sitemap and returns 404 publicly.
Machine-readable evidence: [live-upload-evidence.json](live-upload-evidence.json).
Article validation remains 5 MiB; this does not certify every other upload
workflow or the exact FPM INI values. Other hosting changes remain outside this
specific approval.

## Status-only form follow-up — verified locally

The owner requested **No need Publication tab, just status is fine, dates should
be the time when it's done**. The form now has one Draft/Published Status control
in Article, without the separate Publication section or manual reviewer/dates.
The server uses the verified administrator's name and UTC save time on publication.
It preserves a reached first-publication date on later edits, refreshes the review
for changed public fields and normalizes legacy future dates only on an explicit
Published save. Client-supplied lifecycle metadata cannot rewrite that history.
Draft/Active controls and the existing content/image/search gates remain distinct.

Marketing + admin resource checks: **250 passed / 3,990 assertions**; scoped Pint
and whitespace passed. A successful temporary-image retry clears the previous
featured-image error while retaining text. The native Status control now has its
own 44px hit area; shared phone Save/Cancel actions already meet that height.

In the isolated browser, attempted publication with missing summary/topic/search
fields produced inline errors, expanded Search appearance when needed and kept
the text/image. Completing those details and saving Published recorded
`Article Review Editor` and **2026-11-04 10:40:07 UTC** automatically. A later save
kept both dates. The local article displays its body, correct featured/social
image and one H1, and appears in the sitemap. Desktop **1440×900** and phone
**390×844** checks show no horizontal overflow and actual 44px Status/Save/Cancel
targets. Browser sizing was restored. Screens:
[desktop](status-desktop-published.jpg), [phone](status-mobile-published.jpg),
[phone validation](status-mobile-validation.jpg).
Machine-readable evidence: [status-local-evidence.json](status-local-evidence.json).

The owner subsequently deployed **69cad43** through push-to-deploy; Forge
deployment **82069727** reports **Deployed**. Read-only live checks confirm the
single Status control, removal of the Publication section and manual reviewer/
dates, saved Draft state and preserved image. [Live desktop](status-live-desktop.jpg).
At a **390×844 CSS viewport**, live DOM reports no horizontal overflow and a
44px Status control. Phone screenshot capture returned a blank background under
the existing browser zoom; that blank capture was discarded. Phone visual proof
is the isolated screenshot above. All temporary sizing was restored.

The production upload repair is complete. No production article was published
to test these controls; automatic metadata writes and preservation were tested
in the isolated browser. [Live evidence](status-live-evidence.json).

## Remaining editor checks

- The browser extension's automated file-setting API requires Allow access to
  file URLs; native picker fallback worked locally. Browser control later became
  disabled in Work settings, then was restored before the live retry above.
  Browser access is now enabled. An attempted existing SSH connection was
  rejected for lack of an accepted key; no remote command executed.
- Inline attachment selection began locally but save/reload and public rendering
  of that actual inline upload were not completed in the browser. Automated
  content/image tests pass, but they are not that browser journey.
- Desktop production failure/reopened-image/live status and isolated desktop/
  phone status/validation screenshots are retained. Production publication was
  intentionally not exercised against the owner's draft.

## Continue from here

1. Complete a real inline-image picker save/reload separately when browser upload
   access permits; do not equate automated renderer tests with that journey.
2. Preserve the owner's open form and article. The deployed status-only controls
   are verified; publication should occur only when the owner completes and
   chooses to publish their actual article. Never publish synthetic production
   content for this check.
3. Preserve the approved [Nginx change](nginx-upload-limit.patch) and use the
   [article-media runbook](../../deployment/article-media.md) for future releases.

Machine-readable local evidence: `local-evidence.json`.
