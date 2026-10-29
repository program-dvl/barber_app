# Setup clarity, address search and local plan recovery — 2026-10-29

Scope: owner-reported onboarding search, unavailable local Plan tab and confusing
photo setup. Implements [ADR-075](../../decisions.md#adr-075--discoverable-optional-setup-and-recoverable-platform-failures).

## Findings and applied recovery

- Initial reproduction confirmed the existing Google browser key was present
  and matched the then-current build. Google denied `http://127.0.0.1:8000/`;
  read-only Cloud inspection found Maps JavaScript/Places API (New) restrictions
  and permitted local port 8027. There was no evidence of quota exhaustion.
  That diagnostic inspection made no changes; approved recovery is recorded below.
- Google defaults to device-dependent light/dark styling. The shared editor now
  explicitly uses supported light host styling. Denial stops search/map retries;
  late library imports cannot recreate enabled controls. Manual entry preserves
  typed text, other fields and existing saved addresses/pins.
- Local MySQL retained only the old pricing revision. The existing test-only
  setup command installed two current monthly/annual mappings and verified six
  active test price objects against approved amounts/cadence/progressive tiers.
  Both old cards remain. No checkout, subscription or charge was created.
- Authenticated approved estimates remain useful without current verified
  mappings. Review/purchase stays disabled; public pricing remains verified-only.
  Existing paid-checkout/change confirmation uses independent provider readiness.
- The local `business_photos` table was missing. Applied exactly the two additive
  photo/review migrations; the unrelated Help-feedback migration was untouched.
  Read-back confirms both tables and the current catalog are available, with four
  retained/current rate cards. Existing customer/appointment/financial records were
  not rewritten.
- **Photos & branding** is a direct setup tab and uses the same label on Readiness.
  Its optional badge, booking preview and outlined **Do this later** footer have
  clear hierarchy. Returning to Readiness resets the view and focuses main content.
  A genuinely empty gallery stays neutral; infrastructure failure shows a specific
  explanation and safe support reference, with no ineffective reload loop.
- Staff status/work-branch edits no longer ask for a manual reason. Activity
  records actor, before/after and retained visit IDs automatically. Scheduling
  exceptions, qualifications, holds, tenant access and revisions retain safeguards.

## Verification

- Full backend: **993 passed / 10,560 assertions**, 30 existing skips, from the
  preceding source-change run; not rerun for the later key-only update.
- Frontend: **172 passed** from that same regression run. The gallery tests mount the actual shared `AppNotice`;
  a browser check caught and corrected a default-slot/description omission.
- Production client and SSR builds, scoped PHP formatting and whitespace pass.
- Before the new local key, real Google suggestions and selection succeeded at
  the permitted local origin
  using a public Canadian landmark. A manual switch retained `111 Toronto`.
  Actual denied-origin search showed the useful manual-entry notice on desktop.
  At 390px, an optional-map authentication denial also latched the search block;
  the real search-unavailable notice and manual action preserved `111 Toronto`.
  Search/manual modes each show one relevant notice; returning to manual entry
  retains the address and explains the unavailable optional pin.
- Isolated synthetic SQLite previews use unique session cookies and fake/array
  communication transports. Disposable copies reproduce missing-table and
  unavailable-current-catalog cases. They are separate from the repaired MySQL DB.
- Desktop and actual 390px branding checks show a neutral empty gallery, no
  horizontal overflow and a 44px-high full-width skip button. The skip returns to
  Readiness. Team editor has no reason field at desktop/390px; a sample status
  change saved without one. Plan renders current USD pricing on desktop and a
  disabled-review estimate with reachable support at 390px when mappings are absent.
- Missing-table photo failure and unavailable-current-pricing notices were checked
  at desktop and 390px with the real shared notice, support links and button labels.

## Screenshots

| Evidence | Screenshot |
| --- | --- |
| Direct branding tab, neutral gallery, proper footer | [Desktop](branding-desktop.png) |
| Responsive branding layout | [390px page](branding-mobile.png), [footer and gallery](branding-mobile-footer.png) |
| Real Google suggestions on permitted origin | [Desktop](google-suggestions-desktop.png) |
| Real Google denial with useful recovery | [Desktop](google-unavailable-desktop.png), [390px](google-unavailable-mobile.png), [manual recovery](google-manual-mobile.png) |
| New local key: exact saved website/API restrictions | [Local origins](google-local-key-restrictions.png), [Required APIs](google-local-key-apis.png) |
| New local key: actual port-8000 suggestions and selected address | [Suggestions](google-local-8000-suggestions.png), [Selected address](google-local-8000-selected.png) |
| Repaired Plan pricing | [Desktop](plan-ready-desktop.png) |
| Approved estimate with purchase disabled | [Desktop](plan-estimate-desktop.png), [390px](plan-estimate-mobile.png) |
| Photo outage with explanation and support | [Desktop](gallery-unavailable-desktop.png), [390px](gallery-unavailable-mobile.png) |
| Simplified team profile edit | [Desktop](team-edit-desktop.png), [390px](team-edit-mobile.png) |

## Approved local Google key recovery

After the owner approved the exact scoped action, created exactly one
**ClipperDesk – Local Browser** key in the existing Clipperdesk Production
project. The Console creation form allowed restrictions before creation. Read
back the saved key: Websites permits only `http://127.0.0.1:8000/*` and
`http://127.0.0.1:8027/*`; API restrictions permit only Maps JavaScript API and
Places API (New). The old production key was not edited or expanded.

The new value was saved privately in ignored local `.env`. A parsed comparison
confirmed every other environment setting was unchanged; the temporary key file
was deleted. Client and SSR builds passed; their log is
`/private/tmp/setup-google-local-key-build.log`. Safe read-back confirmed
`built_assets_have_current_maps_key=true` and `config_not_cached` without
printing the key.

A temporary same-origin Chrome probe at `http://127.0.0.1:8000` used the new key.
CN Tower predictions displayed with no provider errors. Selecting the first
result and fetching `id`, `formattedAddress`, `addressComponents` and `location`
succeeded; the visible result was “Address selected successfully: 290 Bremner
Blvd, Toronto, ON M5V3L9, Canada”. No business/client data was saved. This proves
local key/origin suggestions and selection with rebuilt assets; it does not
certify the complete reported-account journey or deployment. The earlier
permitted/denied screenshots document the original diagnosis. No key values are
included in this evidence.
[Original restrictions, with no key value displayed](google-current-restrictions.png).

## Remaining boundaries

Not deployed. Full reported-account, production and broader browser/accessibility
certification remain outstanding. Production-key permissions were not expanded. No
messages, payments or real booking writes were sent during the screen checks.
Earlier launch gates remain outstanding.
