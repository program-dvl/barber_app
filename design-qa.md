# Homepage design QA — 2026-11-19

final result: blocked

Source visual truth:
`/Users/dhavalprajapati/.codex/generated_images/01a1efe9-3681-73a1-8fa7-3c07bf351416/exec-de9b6e42-574b-4f82-8158-711e0ce2b70e.png`

Implementation: `http://127.0.0.1:8019/`; Home.vue and SalonDayDemo.vue.
Implementation screenshot path: unavailable. One inline capture was returned
but was cropped after requesting 1440×1000. A full-page capture and DOM sizing
check were attempted; browser access then became unavailable for this chat.
Source approximately 876×1796; full-page concept, no device frame. Intended
comparison is a 1440px desktop layout normalized to source width. Pixel density
and rendered full dimensions could not be established, so no normalized
comparison or focused side-by-side evidence is claimed.

## Findings

- P1 verification blocker: no full rendered desktop/mobile evidence. Browser
  inventory reported zero browsers and local browser access unavailable.
- Fonts/typography: self-hosted Manrope retained; large two-line hero implemented.
  Wrapping and optical comparison remain unverified.
- Spacing/layout rhythm: hero/day selector/demo/journey sequence implemented.
  Mobile layout and all success/control spacing require visual inspection.
- Colors/tokens: porcelain/navy/indigo/cyan direction implemented; contrast and
  state readability need browser comparison.
- Image quality: custom generated salon artwork inspected; optimized WebP variants
  prepared. Browser crop and visual fidelity are not yet accepted.
- Copy/content: concrete salon descriptor, sample-data labels, truthful manual
  payment boundaries, original industry links and FAQ retained. No fake proof.

## Intentional implementation differences

Use a lightweight image with state transitions instead of real-time 3D. Demo
uses explicit named rows rather than invented employee portraits. Existing shared
navigation, FAQs, setup support and broader-industry content remain for continuity.
These choices are documented, not proof of pixel-level fidelity.

## Comparison history

1. Opened approved source. Loaded homepage in IAB; accessibility tree exposed
   expected headings, links and initial demo controls. Requested desktop viewport;
   screenshot was incomplete. Attempted full-page screenshot and sizing inspection.
2. Browser access unavailable; inventory confirmed no browser surfaces. No visual
   fixes or passing comparison can be asserted from this evidence.

## Implementation checklist

- Restore browser access and capture matched desktop/phone views.
- Compare full page and focused hero, demo and success notice against source.
- Test selection/replay/day selector, navigation, signup, FAQs and consent.
- Test keyboard, 360px/390px widths, long notice copy and reduced motion.
- Check console errors and performance; fix substantive issues and recapture.

Mounted interaction test, marketing suite and production builds pass; they do not
substitute for browser QA. No deployment performed.
