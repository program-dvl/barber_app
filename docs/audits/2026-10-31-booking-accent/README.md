# Saved booking accent — 2026-10-31

Requirements: FR-02/05; existing saved branding and ADR-074 private-preview
contract. This repairs the presentation of the owner's existing accent setting;
it does not change booking, permissions or notification branding.

## Experience brief and diagnosis

An owner saves an accent in Business profile and expects clients to see that
business's colour throughout its booking page. The same saved presentation must
appear in the actual private booking preview, including at phone widths. Keep
ClipperDesk's identity, readable text, visible focus, validation/error meanings,
and the existing booking controls and flow.

`BookingPageData` already returns the saved `brand_color`. The public Welcome
page did not pass it to PublicBookingLayout, and the layout used only default
platform tokens. Saving the colour therefore had no effect on the booking flow.
No database or credential repair is needed for this boundary.

## Change

Welcome passes the saved colour to the shared public layout, for both public
booking and private preview. The layout derives scoped CSS variables on its own
root, without mutating document styles or platform brand colours. The exact saved
accent colours the progress bar. Buttons, service/staff selections, accent links
and focus treatments inherit a shade of the chosen hue made dark enough to keep
white labels and text on white/subtle/tinted light surfaces readable. Hover
colours and light selection tints derive from the same saved colour.

Missing or malformed colours inherit the existing platform defaults. Only six
hex digits are accepted by the presentation helper, matching server validation;
arbitrary CSS values are not accepted. The chosen colour is not applied to owner
workspaces, platform identity, semantic danger/warning/success tokens or emails.
Business-neutral management pages continue using the default platform theme.

## Verification

- **176 frontend checks passed**, Node 24.19.0. Four new regression checks cover
  saved accents reaching the actual page's layout boundary in public/preview,
  actual layout SSR with scoped styles across multiple businesses, safe fallback,
  and calculated contrast of at least 4.5:1 for action text/labels. Colour cases
  include coral, navy, white, black, bright yellow/cyan/green/magenta and violet.
- Existing mounted booking tests cover staff selection, time selection and
  private-preview behavior without creating booking work from the preview.
- Client and SSR production builds and whitespace check passed.
- No backend/schema/provider setting or saved business record changed. Existing
  saved colours are read when the page is loaded; no re-save or migration needed.
- Browser inventory still reports local browser use disabled in Work settings.
  Actual desktop/390px appearance, browser navigation and the reported live
  business have not been visually verified. No screenshot evidence is fabricated.
- Not deployed. Deploy these frontend sources with the rebuilt client/SSR assets
  and check the saved accent on the actual public booking URL and private preview.
