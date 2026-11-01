# Batch salon photos and clear booking preferences

Date: 2026-11-01 (Asia/Kolkata). Requirements FR-02/03/09/19, ADR-077.

## Scope and diagnosis

The gallery picker previously accepted exactly one image and forced another
selection/save cycle for every photo. It now accepts multiple files or a drop,
keeps thumbnail previews and optional descriptions, and saves the selection with
one action. The server still uses its existing single-photo command: serial
requests avoid revision races and retain its authorization, normalization,
private storage, audit and idempotency checks. Eight photos remain the limit.

Each selected file owns a stable UUID and freezes its caption once attempted.
Confirmed files are removed from the queue. Unknown outcomes stop the queue and
retain the exact request for explicit retry. A stale gallery requires reload;
selection changes cannot bypass that gate. A known-invalid image is blocked until
removed, rather than offering the identical failing upload. Remaining previews
are released when removed/unmounted; unmounting never starts a later upload.

The owner's screenshot also showed uploads disabled by the existing branding
entitlement. This work preserves that control; it does not grant an account a
subscription feature. Reads of saved images remain available.

## Booking control trace

| Reported wording | Revised wording | Existing effect |
| --- | --- | --- |
| Price display | Prices on your booking page | Uses service labels, fixed labels, or starting labels; amounts are unchanged. Staff-dependent differences can still show a starting price. |
| New clients | Who can book online | New/existing clients, new clients limited to services flagged Consultation service, or contact match to an existing client record. Service-level restrictions still apply. |
| Clients notified per opening | Waitlist offers per opening | Creates up to the saved number of matching waitlist offers for a released slot; first valid confirmation wins. Delivery is governed separately by consent, channel and sender readiness. |
| Appointment link expires after | Private appointment links expire after | Lifetime of newly issued default private management/view links. Public booking URL and separately bounded action/review links retain their behavior. |
| Allow staff gender requests | Hidden | Saved/projected field has no current public booking/scheduling consumer. Value and request contract preserved; gap documented. |

The form keeps its existing save/revision/audit and business authority, displays
custom saved durations and associates hints/errors with their fields. Advanced
waitlist/link controls use one disclosure, automatically opened for their errors.

## Local browser evidence

Actual application on isolated `127.0.0.1:8027`, disposable SQLite copied from a
prior synthetic fixture. No production browser/session or real customer writes.
Demo images are generated test illustrations, not claimed salon photography.

- Native file chooser advertised multiple selection; three files produced three
  previews and no automatic save. One Add 3 photos action saved all three.
- Reload retained all three photos. A second three-file batch saved its first
  image, received actual server 422 for its 20×20 middle image, retained that image
  plus the later valid selection and stopped. Add was disabled; Remove this photo
  then Add photo saved the later image, reaching five total without replaying
  completed files.
- Desktop and 390px previews and actual failure notice inspected. Phone document
  width remained 390px; removal buttons 44×44px and main action 324×44px.
- On the final build, selected all three remaining spaces and used keyboard
  navigation to reach the Add action. Leaving opened the unsaved-work dialog;
  Cancel retained all three previews and explicit Discard changes left without
  uploading them. The earlier five saved photos remained unchanged.
- Actual preference dropdown choices and dynamic price/consultation explanations
  inspected; waitlist/link disclosure opened. Saved preferences through the real
  controller on the fixture and observed confirmed save. Phone layout has no
  horizontal overflow and a 324×44px Save booking preferences action.

Evidence: [selection desktop](gallery-selection-desktop.png),
[selection mobile](gallery-selection-mobile.png),
[phone upload action](gallery-selection-mobile-viewport.png),
[unsaved selection dialog](gallery-unsaved-mobile.png),
[saved three photos](gallery-saved-desktop.png),
[recovered saved gallery](gallery-recovered-desktop.png),
[partial failure desktop](gallery-partial-failure-desktop.png),
[partial failure mobile](gallery-partial-failure-mobile.png),
[preferences desktop](booking-preferences-desktop.png),
[preferences mobile](booking-preferences-mobile.png),
[preferences save](booking-preferences-saved-mobile.png).

## Automated verification

- Full frontend: 187 passed. Mounted gallery covers explicit selection/save,
  sequential revision use, repeated-click prevention, partial failure, exact
  per-file retry, limits/invalid/duplicate choices, stale reload, entitlement,
  cooldown, known-invalid recovery, preview cleanup and leaving during upload.
- Focused gallery/setup/public-booking backend: 122 passed, 946 assertions. New
  server compatibility tests prove lost-response replay after a full gallery,
  failed-middle-file isolation and entitlement checks between uploads.
- Actual booking form mounted with shared FormField/AppNotice: option values,
  dynamic hints, preserved custom durations/hidden value, save submission,
  processing lock and error association/advanced disclosure.
- Client and SSR production builds pass. Existing business schema and provider
  configuration are unchanged. Scoped Pint and whitespace checks pass.

Not deployed. The actual screenshot account's entitlement is unchanged and was
not independently certified. Network loss, stale revision and cooldown are
covered by mounted tests; no provider outage or production concurrency rehearsal
is claimed by the local browser walkthrough.
