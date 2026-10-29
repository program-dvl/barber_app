# Optional salon presentation and completed-visit reviews

Date: 2026-10-29. Requirements: FR-02/05/09/13/19; ADR-074.

## Experience brief

The salon owner wants a credible booking page without a photography/setup task.
The client wants to understand the salon, choose services and a professional,
reserve a real local time, and leave feedback after the visit. The primary action
remains choosing services and completing booking. Photos, biographies and real
reviews support that decision; no-photo layouts, initials and honest empty states
must work equally well. Optional uploads stay in Business setup and Team.
Completed-visit feedback uses the same secure appointment journey; public display
requires an explicit client choice. Existing payment/deposit policies stay intact.

## Reference study and adaptation

Inspected the supplied [Lashes Girl page](https://www.fresha.com/a/lashes-girl-west-covina-216-south-glendora-avenue-fsxo0fya)
and its live booking flow in Chrome. The public page offers an image mosaic/full
gallery, service categories/prices/durations, team, reviews, portfolio, opening
hours, directions and a persistent booking action. Booking uses service selection,
professional/time selection and confirmation with a persistent visit summary.
No real appointment, account, review, payment or outbound message was submitted.

ClipperDesk already has service categories/search, multi-service selection,
branch-aware professional qualification, local dates/times, real availability,
holds, details/policy review, secure management, calendar links, waitlist and
location directions. Team titles/bios exist but were hidden behind a select;
photo storage fields lacked an owner workflow. Logo/cover were already optional.

This change adds optional gallery/portraits, visible professional profiles and
completed-visit reviews. The clean fallback uses actual business information,
never stock salon photos, fake ratings or a setup requirement. Fresha marketplace,
competitor recommendations, group booking, AI summaries and payment collection
are not part of this adaptation. Service photos/advanced portfolios remain under
OPEN-20; salon gallery images can showcase premises or work with owner captions.

## Verification

- Full backend: **984 passed / 10,389 assertions**, 30 existing intentional
  integration/disabled-feature skips. Full frontend: **151 passed**. Client/SSR
  production builds, scoped Pint and whitespace checks pass. Existing CSS
  `@property` and font-resolution build warnings remain.
- New tests exercise optional empty media, normalized/rotated/transparent images,
  rejected dimensions/types/sizes, stale edits, repeated uploads/removal,
  rollback/file cleanup, business/branch/role visibility, publication, review
  completion/expiry/replay/composite tenant constraints, public consent/withdrawal,
  private feedback, all-rating totals/pagination and CRM merge/privacy export.
- Mounted actual Vue components cover local previews, explicit saves, duplicate
  requests, preserved image/text drafts, gallery disclosures, stale refresh,
  staff-policy changes, qualified local professionals, preview side effects,
  Retry-After and stopped retries. Booking notice tests mount the real AppNotice.
- A no-photo projection test covers local midnight with fractional clock
  precision, current/expired/future usual hours and Sunday mapping. One old
  onboarding expectation was updated for the intentional removal of the stock
  cover. An unrelated randomized CRM search fixture was made deterministic after
  it randomly matched the search being tested. OpenSSL signing tests needed a
  writable temporary RANDFILE in this environment; no product workaround was made.
- Browser evidence uses an isolated copied synthetic SQLite fixture on
  localhost:8044, fake communications and no real client data. Existing local
  marketing assets were deliberately added as **demo photos** to test the actual
  upload domain/image storage and rendering, never as production fallbacks or
  images of an actual salon. No primary application database was migrated.
- Checked the no-photo desktop/390px page, public photo mosaic/full gallery,
  professional biography dialog with Escape/focus return, qualified staff cards,
  no-times/next-day actual availability and remembered service selection. Owner
  branding/compact gallery and one expanded editor reflow at 390px without page
  overflow. Team photo controls open on demand. Desktop Calendar reads private
  feedback and creates/copies an expiring review link with a visible privacy note.
  The final owner review-link notice/drawer also reflows at 360px with no page
  overflow; its actual privacy guidance and Copy review link label were checked.
- Submitted synthetic private feedback in desktop and 390px browser journeys;
  saved confirmation appears and public review count remains unchanged. Public
  consent/pagination, withdrawal, expiry, upload failures and cooldowns have
  automated evidence; they were not all re-created with provider outages in a
  browser. The browser extension blocked file-chooser upload access, so selected
  local-image previews/upload interaction have mounted-component and HTTP/domain
  evidence, not a completed native file-picker browser run. No extension settings
  were changed.

## Screenshots

- [No photos, desktop](no-photos-desktop.png) / [phone](no-photos-mobile.png)
- [Public gallery, desktop](gallery-desktop.png) / [phone](gallery-mobile.png)
- [Professional selection, phone](professionals-mobile.png)
- [Owner gallery, desktop](owner-gallery-desktop.png) / [phone](owner-photos-mobile.png)
- [Team portrait controls](owner-team-desktop.png)
- [Owner review-link confirmation, 360px](owner-review-link-mobile.png)
- [Review form, desktop](review-desktop.png) / [phone](review-mobile.png)
- [Private feedback saved, phone](review-saved-mobile.png)

## Rollout and remaining evidence

Apply the additive business_photos/client_reviews migrations before serving this
code, deploy the built assets and restart SSR if used. GD is needed for new
uploads; EXIF support preserves phone orientation. Existing financial data,
bookings, policies and communication controls are preserved. No real appointment,
message or payment was submitted on Fresha or production ClipperDesk.

This is local evidence only. Production-engine migration/concurrency rehearsal,
deployment/real-account checks, the upload-picker browser run and independent
accessibility/browser certification remain outstanding. OPEN-25 records review
reporting/moderation and safe recovery policy before production review enablement.
Existing deposit payment, regional/legal and communications launch gates remain.
No public payment bypass, automatic review outreach or marketplace promotion is
included.
