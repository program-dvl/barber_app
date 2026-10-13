# Public PNG logo — 2026-10-12

The owner requested that the public website use the attached ClipperDesk logo
and provide it as PNG. The attachment was already a 422×106 RGBA PNG. It was
copied unchanged to `public/images/brand/clipperdesk-logo.png`, preserving the
lettering, tagline, colors and white background. SHA-256:
`d43859950f255714f0a6f2ae892964bd6df175d9d9de3efa663cb2eca16648b4`.

`config/brand.php` now identifies that PNG as the primary logo. The shared
MarketingHeader uses ProductMark's explicit image option; the no-JavaScript
privacy header uses the same configured asset. The image includes its original
wordmark/tagline, so no extra text is rendered alongside it. The image has
intrinsic dimensions and accessible alternative text, fits a 280px maximum
width, and shrinks in the mobile header. Existing inverse/compact marks remain
available for their established contexts. No legal facts or publication state
were changed, and no deployment occurred.

Verification: client/SSR production builds and the 17-route asset budget pass.
PublicDesignSystemTest and PrivacyPolicyTest pass: 24 tests / 221 assertions.
Original and saved PNG checksums match. Browser checks at 320, 390, 768 and
1440 pixels found the correct PNG loaded and no horizontal overflow; mobile
logo widths were 202px and 272px at the two phone widths.

Screenshots: [desktop header](header-desktop.jpg), [mobile header](header-mobile.jpg).

## Square transparent CD monogram

The owner's follow-up requested **only** a square CD monogram with higher
resolution and alpha transparency, based on the second attached reference.
Saved separately as `public/images/brand/clipperdesk-cd-square-transparent.png`.
Built-in ImageGen was used; the final generation prompt called for a pristine
square CD monogram with navy C and indigo D, centered transparent padding,
crisp edges, no words/tagline/frame/background, and transparent negative space.
The delivered PNG is 1254×1254 RGBA (larger than the 306×332 supplied reference),
with an alpha channel and verified transparent corner. No header/logo reference
was changed for this asset-only follow-up. Earlier white-background wordmark
and reference artifacts remain preserved.
