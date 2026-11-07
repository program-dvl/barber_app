# Receipt PDFs and completed-visit emails

Implementation: 2026-11-05–06. Requirements FR-09/13/15/19; ADR-082.

## Delivered behavior

- Business-specific automatic receipt and completed-visit review email preferences.
  Receipt default on preserves prior behavior; review default off applies to every
  business. Enable dates prevent review backfill and receipt backfill after
  re-enabling. No bulk job or historical sweep is introduced.
- Direct completed-visit Send review email and separate Copy review link. Existing
  feedback replaces outreach actions. Private feedback and public-consent behavior
  remain unchanged. Review email requires subscribed marketing status, explicit
  email preference and no suppression; business switches never grant consent.
- Issued receipts share one escaped online/A4 layout with the ClipperDesk mark,
  restrained indigo/navy accents, aligned metadata, paid/outstanding label,
  itemization, local time, minor-unit precision and financial breakdown. PDF uses
  embedded local branding, repeated headings and page numbers; no remote asset
  request is needed. Existing issued financial snapshots and receipt links remain.
- Staff Download PDF and Send receipt use existing financial/branch permissions;
  email additionally requires client-contact access. The pipeline retains attempts,
  callbacks and audit events. Stable visit intents plus appointment locking prevent
  repeated-click/event duplicates. Receipt issuance and completion can arrive in
  either order. Delivery rechecks current preferences and visit eligibility.
- SES attachment delivery uses MIME with plain text, HTML and the PDF plus existing
  tracking tags/configuration set. Resend also supports the attachment payload.
  PDF failures happen before submission and can safely retry; uncertain SES
  acceptance is not automatically resent.

## Local verification

- Scoped backend: 114 tests passed; final assertion count is recorded in project status.
  Includes the receipt re-enable cutoff and final branded layout.
- Review/notification frontend: 10 tests passed with Node 22.23.2.
- Client and SSR builds passed. Scoped Pint and whitespace checks passed.
- Synthetic four/five-page A4 receipt rendered with installed Chrome and inspected
  as PNGs: long descriptions/reference/client name, KWD three-digit precision,
  repeated headings, grouped totals, business identity and branding. The new
  branding replaces the first plain sample. No real client data was used.
- Actual built Client notifications page captured with isolated test-generated
  props, all browser requests intercepted: 1440px and 390px, no horizontal overflow
  or page errors. Receipt on/review off defaults confirmed in browser.json.
  Screenshots precede the small checkbox sizing refinement. These captures do not
  establish live delivery, actual Calendar/Checkout phone interaction, screen-reader
  coverage or a production deployment. Those checks remain release verification.
- Tests exercise manual/automatic reuse, duplicate completion, disabled settings,
  old visits, missing email, unfinished/cancelled/no-show visits, client permission
  withdrawal while queued, existing feedback, foreign-business authorization,
  immutable receipt attachment content, MIME tracking and out-of-order events.
  Existing SES tests cover explicit throttling, uncertain acceptance and callbacks.

## Deployment

1. Deploy source and run `php artisan migrate --force` before new workers start.
   Migration `2026_11_05_000002_add_visit_email_preferences.php` is additive.
   The unrelated pending short-link migration remains untouched.
2. Install locked Composer and npm dependencies. PDF generation reuses installed
   Browsershot/Puppeteer, with a supported Node runtime (22.12+) and a managed
   Chromium executable plus its operating-system libraries and fonts. Provision
   Chromium during deployment, never lazily inside a worker. If binaries are not
   on the worker PATH, set `RECEIPT_NODE_BINARY` and `RECEIPT_CHROME_PATH`.
3. Build client/SSR assets, refresh configuration cache and restart communication
   workers. PDF generation has a 15-second timeout within the existing 30-second
   job budget; verify target-machine generation time before enabling production.
4. With controlled addresses/fake provider first, verify PDF download, attachment,
   automatic/manual delivery history and the secure links. No credentials, consent,
   suppression, marketing campaign or SMS-review settings are silently changed.
5. Run a controlled deployed smoke test before describing customer delivery as
   resolved. This change did not deploy or send any real customer email.

## Deliberate limits

One request per visit is shared across automatic/manual actions. Repeated clicks
return the tracked outcome, including failures; they do not create an untracked
resend. Operators use existing authorized delivery recovery for eligible failures.
An uncertain provider outcome requires support review. Staff final receipts still
require completed checkout; this does not invent provisional receipts. Existing
snapshots without a captured zone/locale use the prior route fallback. Regional
legal/tax release approval and wider campaigns remain outside this change.
