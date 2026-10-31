# Google address results visibility — 2026-10-31

Requirements: FR-02/03; ADR-064/075. The owner supplied an onboarding screenshot
showing a typed search and successful Places response with no visible results.

## Experience brief

- User: a new or existing owner entering a business address in signup/guided
  setup or Locations, with a desktop or mobile browser.
- Outcome: see Google suggestions, choose an address, review the selected address
  and continue. Searching or selecting alone must not save business data.
- Preserve: the current search/manual choices, entered details, optional entrance
  pin, Google attribution, keyboard/touch behavior, permission and save rules.
- States: retain loading, selected-address summary and the shared AppNotice/manual
  recovery path for denied or failed Google requests. No new user-facing copy or
  action was introduced.
- Responsive behavior: keep the field within the existing layout and allow its
  suggestion list to extend below the field/card at desktop and phone sizes.

## Source diagnosis and correction

`business-setup.css` applied `overflow:hidden` to `.cd-location-search`, the
direct parent of the Google autocomplete element. The guided onboarding card
also used `overflow-hidden`. Locations rendered the shared editor inside a
SurfaceCard that clips by default. These are clipping boundaries for Google's
dropdown, consistent with the supplied successful-response/hidden-results image.
This source diagnosis does not establish a fresh browser reproduction.

Removed the global clipping/search-widget rule. LocationEditor now owns the
shared field styling, visible overflow and a local focus-within stacking level.
The guided card allows overflow. SurfaceCard gains an explicit `overflowVisible`
opt-in used by the location-editing card only; other cards retain their clipping.
The Google widget, its shadow tree, native predictions, event listeners, session
tokens, API configuration and selection/details logic were not replaced.

[Google's widget reference](https://developers.google.com/maps/documentation/javascript/reference/places-widget#PlaceAutocompleteElement)
documents the prediction-list dropdown beneath PlaceAutocompleteElement and its
supported styling interface.

## Verification and limitations

- Existing focused location, loader and setup frontend checks: **25 passed**.
  These include mounted selection, minimal details fetching, manual recovery,
  denial and late async failure handling. They do not measure browser clipping.
- All frontend checks: **172 passed**, using installed Node 24.19.0.
- Client and SSR production builds passed. Whitespace check passed.
- No backend, provider credentials, API restrictions or saved business data
  changed. No new backend test run was needed for this presentation-only change.
- Browser tools reported **local browser use disabled in Work settings**. The
  owner was asked to enable it; actual Google results, selection via mouse and
  keyboard, card edges/scrolling, dark-device mode and desktop/390px visual checks
  remain outstanding. No screenshots or deployed verification are fabricated.
- Deploy these frontend changes with freshly built client/SSR assets. The
  reported production problem is not marked resolved without a deployed check.
