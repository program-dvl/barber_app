# Booking photos and public presentation

Requirements: [FR-02, FR-05, FR-09, FR-19](../product-requirements.md). The
2026-10-29 refinement is recorded in ADR-074. Scheduling and booking remain in
the [canonical journey](../public-booking-journey.md).

## Optional salon gallery

### Saved accent colour

The Business profile accent is supplied to the actual public booking layout and
private preview. Scope the theme to that booking root so it cannot change another
business or the owner workspace. Use the exact saved colour for progress, with
readable shades of the same hue for buttons, links, selected states and focus.
Keep ClipperDesk identity and semantic status colours. Missing/invalid colours
inherit the platform theme. This is presentation only; no booking/permission or
notification-branding rule changes. [Implementation and verification limits](../audits/2026-10-31-booking-accent/README.md).

### Gallery

Business setup → Photos & branding offers up to eight business-wide photos, separate
from the existing logo and cover. Photos are optional at every setup/publication
stage. Owners can add a description, move photos earlier/later and remove them;
the first photo is featured on the public page. Owners must have existing
business-wide Settings authority and the custom-branding entitlement. A manager
limited to a subset of branches cannot edit or preview business-wide media.
This slice does not add service-specific or branch-specific media editing.

Uploads accept actual PNG, JPEG and WebP rasters up to 5 MB, with sides between
160 and 6,000 pixels and no more than 12 megapixels. The platform decodes and
re-encodes the image as JPEG, preserves supported phone orientation, strips
embedded metadata and limits the stored longest side to 2,560 pixels. SVG and
unreadable files are rejected. GD is required to save images; its absence offers
a safe retry-later message and leaves the rest of setup available.

`BusinessPhoto` belongs to an explicit business. Files are stored under that
business's private prefix and storage keys never appear in the public/owner
gallery projection. Public delivery resolves the requested business and photo
together and requires an active, published business accepting online bookings.
Private delivery reuses business-wide setup authorization. Responses use
`no-store, private` and `nosniff`, so a removed photo or paused page does not
remain available through an immutable browser cache.

Every mutation locks the business. The current ordered photo IDs and descriptions
produce the gallery revision; stale changes must reload before saving. Uploads
also bind a UUID retry key to the original file/description hash. An exact retry
never adds another photo, even if the original response was lost. A failed
database/audit write removes its newly stored file. Removal retains an internal
tombstone, hides both public/private delivery immediately and deletes its file
after commit. Repeated removal and exact no-op edits create no extra audit event.
Applied uploads, description/order changes and removals produce Activity records.

The editor saves only after an explicit action, preserves a selected file and
unsaved descriptions after failures, preserves other draft descriptions during
reordering, stops automatic retries and respects a returned retry cooldown.
Persistent feedback uses `AppNotice`; related actions wrap with 10px gaps and
44px touch targets. Removal uses the shared keyboard-operable dialog.

ADR-075 makes this section directly discoverable without changing its optional
status. A confirmed empty response offers photo selection; loading does not show
an empty state. An unavailable response never claims that no photos exist. A
database failure returns a safe unavailable result with a support reference,
while the diagnostic remains in platform logs. Platform failures stop automatic
and repeat-navigation reloads; connection recovery offers one deliberate load
action. Existing thumbnails, selected previews and descriptions survive failures.
The local missing-table recovery applied the new photo and review migrations
only; [deployment/evidence limits](../audits/2026-10-29-setup-recovery/README.md)
remain separate from the interface contract.

## Professional profiles and no-photo pages

Team reuses existing public title and biography fields and offers an optional
portrait in the operational profile drawer. Portrait uploads validate PNG/JPEG/
WebP, 5 MB, 100–6,000 px and 24 megapixels, normalize orientation, strip metadata
and store at most 1,200 px. Tenant/branch authority and the profile revision apply
to replacement/removal. Public URLs require the active published business and
an active online-visible professional, match the current photo version and use
no-store. Private Team and setup preview delivery retain their respective access
rules. Superseded portrait objects remain private/inaccessible through old URLs;
storage cleanup is a later operational task, not a public delivery capability.

The public page uses a photo mosaic/gallery when actual photos exist; otherwise
it starts with business identity, description and navigation, with no stock salon
fallback. Initials fill absent/broken portraits. Team cards expose real profile
information; choosing a professional uses only the existing branch/service
qualification and staff-preference policy. About dialogs show the full biography.
Usual hours are explicitly separate from real bookable appointment times.

## Owner image editing

Logo and cover show the saved image, selected local preview and explicit Save,
Discard, Replace and Remove actions. Removal requires confirmation. Replacement
and removal bind the currently saved image under the business lock; stale image
recovery preserves the selected preview. Gallery controls expand only for the
photo being edited, with a local preview before upload. File choices and drafts
participate in existing unsaved-work navigation guards. All image work remains
optional, and unavailable capability text uses account/subscription language.
New HTTP logo/cover uploads also validate decoded size (6,000 px/12 megapixels;
minimum 32 px logo/160 px cover), correct phone orientation and remove embedded
metadata. Logos retain alpha as PNG at up to 1,200 px; covers are JPEG at up to
2,560 px. Failed audited writes clean newly stored unreferenced images. Existing
saved files are not rewritten; replacement/removal prevents their old public
endpoint from delivering them, but older branding objects remain private at rest.

## Local verification

`BusinessPhotoGalleryTest` covers optional empty state, normalized private files,
upload retries, invalid type/size/dimensions/description, phone orientation and
metadata removal, ordering/stale revisions, the eight-photo limit, tenant/role/
branch authorization, unpublished/paused/removed delivery and rollback cleanup.
Desktop/mobile browser evidence and deployment verification are recorded by the
parent booking audit; these tests alone do not establish deployment or complete
accessibility certification.
