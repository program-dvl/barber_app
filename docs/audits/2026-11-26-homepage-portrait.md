# Homepage portrait refinement — 26 November 2026

User authorized refining the latest likeness without tattoos and placing it in the editorial section without a founder label. Built-in image generation edit mode was used with the latest approved portrait as reference.

## Prompt

Refine this approved portrait for the ClipperDesk homepage editorial section. Preserve EXACT recognizable identity, face proportions, expression, gaze, hairstyle with side fade, sharply outlined dense beard, black crewneck, pose and composition. No tattoos, jewelry, apron or straps. Change only photographic finish and colour: reduce orange/yellow cast substantially, soft neutral ivory plaster background and neutral warm natural skin, rich black knit. Keep beautiful diagonal architectural shadows and directional sunlight but with restrained highlights and gentle shadow detail. Improve authentic photographic skin texture: subtle pores, natural tonal variation, no etched/illustrated skin lines, no waxy smoothing or artificial beauty filtering. Photoreal luxury editorial camera portrait, subtle grain, crisp individual beard hairs. Keep full top of hair and generous upper margin, chest-up vertical 4:5. No text, logos or watermarks.

## Delivery

- `public/images/marketing/homepage/in-the-moment-owner.webp`: 900 × 1125, 62,260 bytes.
- `public/images/marketing/homepage/in-the-moment-owner-small.webp`: 600 × 750, 26,600 bytes.
- WebP compression/resizing only after generation; original editorial asset retained.
- Client and SSR build passed; all 27 route budgets passed. Desktop Chrome inspection confirmed portrait, heading and benefit composition.
- Follow-up browser verification completed at 320px, 390px and 430px phone widths, 768px tablet and 1440px desktop. Phone/tablet document widths matched viewport widths with no horizontal overflow. Full hair, beard and black knit remain visible on phones; desktop crop preserves the face. Tablet spacing was improved with a 32px column gap, vertically centered portrait and rounded lower corners; the revised 768px composition was visually verified. Temporary viewport overrides were cleared.
- Client/SSR build and all 27 route budgets passed again after the tablet CSS change.
- Local implementation only; no deployment performed.
