# Locations and Google Maps

Requirements: [FR-02/03/05/06/09/13/19](../product-requirements.md); ADR-064.
Related contracts: [Business setup](business-setup.md),
[public booking](../public-booking-journey.md), [communications](communications.md).

## Owner journey

The existing guided `location` step and Locations page share `LocationEditor`.
Choose Google search or manual full-address entry. Search uses a bordered field;
choosing a suggestion shows one selected-address summary with an edit action.
Review the address, optionally position the customer entrance, then save. Manual address entry remains
usable without Google or a Google Business Profile. Country, time zone, hours and
contacts remain business decisions. Google does not replace operational data.

The user explicitly requested a simple interface: no latitude/longitude inputs,
no required street/city/postal questionnaire and no new onboarding flow. Optional
unit/floor/parking/arrival notes and structured address corrections use two native
disclosures. Countries show readable names. Coordinate errors are explained as
pin errors. The same keyboard-accessible Advanced Marker works with touch/mouse.

Saving location details preserves hours, including split periods. Manual address
corrections retain the owner entrance and prompt a pin review; selecting the same
Place ID retains the saved entrance, while selecting a different place prepares
a new entrance selection. Stale structured city/postal data is cleared when the
full address is manually replaced. Guided introductions now prepare the editable daily 09:00–21:00 preset
without a schedule questionnaire (ADR-066); saved presets remain intact. Initial
hours remain part of the standalone location editor; subsequent hours use the existing reviewed
hours command. The only location or an explicitly selected primary location
updates the legacy business address summary for setup/publication compatibility.
Business profile links to the canonical location editor instead of asking for a
second address or a manually assembled map URL. Existing API fields are retained.

ADR-075 fixes the search field/widget to the product's explicit light colour
scheme, including when the device prefers dark mode. Authentication denial stops
the current search/map lifecycle and preserves typed or selected address text
when switching to manual entry. The interface offers that working alternative
without asking owners to repair provider settings. A denied origin must be
diagnosed against the exact scheme/host/port; it does not establish a quota
failure. The [Google deployment guide](../deployment/google-maps.md) records the
initial local 8027/8000 mismatch and the separate development-key policy. The
owner-approved local key has now been created with only those two local origins
and the required Maps JavaScript/Places APIs; the original production key is
unchanged. The new value is saved in ignored local configuration, with matching
client/SSR builds verified. A temporary same-origin Chrome probe at port 8000
successfully showed suggestions and selected/fetched a real address without
provider errors or saving business/client data. This local key/origin result
does not certify deployment or the complete reported-account journey.

Settings authority uses `BusinessSetupAccess`: Owner or a settings-capable member
assigned every branch. Tenant binding, active entitlement checks, duplicate-name
prevention, business transaction locks, optional revision checks, and audit events
remain in the existing activation command. Primary changes clear the prior primary
inside the same transaction. Upcoming appointments block branch time-zone changes.

## Data provenance and history

Existing `address`, `time_zone`, `latitude`, `longitude`, contact and status columns
remain intact. Add nullable structured address, Place ID, entrance and arrival
fields plus `is_primary`; no provider response is accepted by the server as a
Google verification or operational truth claim. Locations can share a real-world
address/Place ID; a uniqueness constraint on those would incorrectly prohibit
co-located businesses. Existing name-based branch duplicate protection remains.

The Places widget supplies sessions and Google attribution. Selected details use
only `id`, `formattedAddress`, `addressComponents`, `location`. Returned geometry
is temporary browser state. Only the owner's deliberately positioned entrance
is submitted; suggestions, provider names, hours, reviews/photos and provider
geometry are not retained. Owners review the address as their own operational
address. Place IDs are retained under Google's exception. See the
[deployment guide](../deployment/google-maps.md) for policy sources and legal
review, including EEA differences. This is not a blanket content-storage license.

New appointments capture `location_snapshot` from their actual assigned branch
inside the existing booking path, including staff/internal/waitlist bookings.
The additive migration freezes legacy appointments using the best known current
assigned address; it cannot recover previously changed historical addresses.
Same-branch replacement/rescheduling retains the original snapshot; explicitly
changing appointment branch captures the new assigned branch. Location changes
never follow a new business primary. A branch edit during a public hold requires
fresh location review before confirmation. Older flows without this new location
policy field retain existing behavior until their short hold expires.

## Customer journey

The catalog contains active branches with an eligible online service and current
qualified, visible staff assignment. One eligible branch is selected automatically,
with no branch selector. Multi-branch customers select inline radio cards, search
by name/city/postal code, and optionally request browser geolocation. Permission
is never requested on page load. Coordinates stay only in component memory and
are excluded from flow/session storage, server requests and client records.

Known owner entrance coordinates use Haversine distance for optional nearby sort;
unknown distances sort after known ones. These are explicitly straight-line
estimates, not travel distances/times. Branch changes retain eligible services and
add-ons whose parent remains, reset unsuitable staff and time, abort stale search
responses and disclose removed choices. Authoritative availability and confirmation
still enforce existing branch hours, staff, service, resource and tenant rules.

`LocationCard` presents name/address, unit, arrival/parking details and directions
in booking, inline confirmation review, confirmed booking and secure management /
reschedule/cancel pages. An interactive Google map loads only when requested.
Maps URLs prefer the owner entrance, otherwise Place ID with an address, otherwise
manual address. Apple navigation is offered only for owner entrance coordinates.
No Routes API, geocoding, customer account or geolocation is required to book.
Both `book.clipperdesk.com/{slug}` and `/book/{slug}` remain the shared journey.

## Communications

New appointment intents capture the snapshot's location/address, arrival notes
and directions variables. Confirmation/pending/approval/change/cancellation and
reminder messages add a captured location footer unless the custom template
already includes those variables. Existing queued messages and published template
versions remain unchanged. The existing provider renderer, escaping, consent,
quiet hours, idempotency and SMS segment accounting remain authoritative.
Calendar exports contain the snapshot address, arrival notes and Google link.
No message generation calls Google. Location-rich SMS may use additional segments.
WhatsApp remains disabled under ADR-041; no messaging scope was promoted.

Verification and remaining certification limits:
[2026-10-12 audit](../audits/2026-10-12-location-intelligence/README.md).

## Direct workspace access (2026-10-29)

Locations has a dedicated desktop/mobile workspace menu entry beside Services,
using the existing settings visibility and access rules. Its selected navigation
state belongs to Locations. Existing paid-limit checks still control Add location;
at the limit, Upgrade to add locations opens the established billing journey.
