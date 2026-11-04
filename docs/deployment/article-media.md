# Article images and editorial workflow

Scope: Content → Articles, PRD sections 1–4/12/15 and ADR-065's article restoration.
The public publication/privacy gates and administrator authorization remain.

## Deployment requirements

1. Deploy the reviewed article code and `config/articles.php`, `config/livewire.php`
   and `public/.user.ini` together. Refresh the normal Laravel configuration cache.
   No schema migration is required: editorial lifecycle columns already exist.
   The status-only follow-up removes manual lifecycle entry. The verified admin
   actor and current server UTC time supply reviewer/publication metadata;
   preserve the reached original publication date on normal later edits.
2. Preserve existing media. `ARTICLE_MEDIA_DISK` explicitly selects public article
   storage. Without it, existing `FILESYSTEM_DISK=s3` deployments retain S3;
   other deployments use the named `public` disk. Do not point this to client
   document storage. If moving storage deliberately, inventory and copy existing
   article images first, verify URLs, and only then change configuration.
3. For local public storage, verify the existing `public/storage` link targets
   the persistent release-independent `storage/app/public`, with app write and
   web read access. Run the normal `php artisan storage:link` only if missing;
   do not replace another mounted storage location without inspection.
4. Livewire temporary files use `storage/app/private/livewire-tmp` and signed
   previews. The PHP process needs write access. Multi-server deployments need
   shared private storage or a separately reviewed temporary-upload disk/route;
   this single-host setup must not be silently copied into an unshared cluster.
   Existing temporary files on an old disk are not automatically migrated/deleted.
5. PHP-FPM reads `.user.ini` only if enabled. The source specifies upload 32 MiB
   and POST 40 MiB, preserving existing 20 MiB salon-media journeys. Article
   validation still accepts only JPEG/PNG/WebP up to 5 MiB. FPM may cache per-dir
   settings for several minutes; verify the effective request SAPI settings.
   A CLI `php -i` check does **not** establish FPM limits. Nginx/proxy body limits
   must also permit the upload. Do not reduce another module's approved limits.

These runtime details follow the [PHP per-directory configuration documentation](https://www.php.net/manual/en/configuration.file.per-user.php)
and the installed [Livewire upload workflow](https://livewire.laravel.com/docs/4.x/uploads).

## Required end-to-end check

Use an isolated test environment or an explicitly authorized draft. Preserve all
real article text and do not publish synthetic production content.

- Select the reported original 2.3 MB PNG, wait for completed upload, save a draft,
  reopen it and verify its image URL returns image bytes. Verify the image editor
  and a body-image attachment persist after save/reload.
- Try an oversized image and an unsupported file, retain the text and recover
  with a permitted replacement or save without an image as Draft. A persistent
  transport/configuration error is an operator issue, not a customer settings task.
- Publish only an actually reviewed article with complete summary/topic/search
  fields and image by choosing Published and saving. Verify the reviewer and
  current UTC time were recorded automatically and a later ordinary save does
  not replace the first publication time. Confirm blog listing, article body/
  featured image, canonical/social metadata and sitemap agree. Existing future
  schedules remain gated until reached unless an administrator explicitly saves
  Published, which now means publish immediately. No new scheduling UI is offered.
- Check desktop and 390px: readable feedback, separated controls, preserved text,
  no overflow and successful save/reload. Ordinary/support/auditor identities must
  not gain article administration access.

## Diagnosis and rollback

The original generic Livewire message and the replacement friendly message do
not identify the cause. On 2026-11-04, retrying the original 2,269,879-byte PNG
on the preserved live article form returned **HTTP 413, text/html**, with Nginx's
`413 Request Entity Too Large` body. The preceding and following Livewire update
requests returned 200. This establishes Nginx request-size rejection before the
upload reaches PHP; it does not establish effective PHP-FPM limits or successful
temporary/final storage. The earlier local 2 MiB PHP limit was a hypothesis,
not the verified cause of this live response.

Read-only Forge inspection shows source `746682a` deployed (push-to-deploy
`82065079`). The General site configuration has no explicit
`client_max_body_size`; its `forge-conf/3340514/server/*` includes were not read.
The apex server includes `forge-conf/3340514/site.conf`, with no other body-size
override visible. The exact effective Nginx limit is therefore unconfirmed.

After the owner explicitly approved this upload fix, the following directive
was added immediately after `charset utf-8;` in the General site configuration's
existing `server` context:

```nginx
client_max_body_size 40m;
```

The complete before/after editor fragment was compared to preserve every other
setting. Forge completed its save/reload flow; reopening confirmed the saved
content, apart from normalization of final blank lines. The 40 MiB ceiling aligns with the source
POST limit and preserves existing salon-media workflows; article validation
remains 5 MiB. Nginx's [request-size directive](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size)
is separate from PHP's `.user.ini`. The applied [context-only patch](../audits/2026-11-04-article-editor/nginx-upload-limit.patch)
records the narrow change; other hosting changes were not authorized by this approval.

The original PNG then reported Upload complete. The owner's article was saved
once as Draft and reopened with the exact same title and complete editor HTML.
Its durable public image returns **200 / image/png / 2,269,879 bytes**, with
SHA-256 identical to the original. The saved draft remains excluded from the
blog/sitemap and returns 404 publicly. This verifies the reported featured-image
journey; effective FPM INI values and other upload journeys are not certified.
See [live evidence](../audits/2026-11-04-article-editor/live-upload-evidence.json).

Retry the original PNG without refreshing away the entered article text. If a
new PHP/validation/storage failure appears after the Nginx repair, inspect that
response and effective request-SAPI settings before another change. Capture
only status and sanitized error details: never retain cookies, signed upload
URLs, provider credentials or full page state in an audit.

If local temporary upload works but final media fails, inspect the configured
article disk, write permission and resulting public URL. For S3, verify existing
public-object access and credentials server-side; never expose private objects
or weaken client-file access to repair an article image.

Keep the last known source release/configuration for rollback. Preserve saved
article and media records across any rollback. Do not delete uploads, rewrite
stored articles or turn off publication validation to force a success message.
For this Nginx change, preserve the previous General site configuration
and restore it through Forge's validation/reload flow if the change fails or
causes unrelated regressions; restoring the previous setting may restore the
known upload 413.
An older renderer may not display newly authored rich HTML correctly, so verify
the public article body after any rollback as well as the admin save.

Current evidence and explicit limits: [2026-11-04 article audit](../audits/2026-11-04-article-editor/README.md).
