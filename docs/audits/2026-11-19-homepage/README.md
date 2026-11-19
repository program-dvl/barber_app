# Combined homepage implementation — 2026-11-19

Status: implemented locally; visual acceptance blocked by browser availability.
Requirements: FR-01, FR-06, FR-08, FR-09, FR-11, FR-15, FR-18; public acquisition under PRD sections 1–4.

## Approved direction

Owner approved the combination of the architectural salon concept and hands-on
sample walk-in demonstration. Visual reference:
`/Users/dhavalprajapati/.codex/generated_images/01a1efe9-3681-73a1-8fa7-3c07bf351416/exec-de9b6e42-574b-4f82-8158-711e0ce2b70e.png`.

Home uses a generated editorial salon image, semantic HTML headline/actions,
three time-of-day buttons, changing scene labels/copy and light image treatment.
This is a lightweight 2D implementation, not a real-time 3D salon or animated people.
The sample walk-in has two available 30-minute slots. Find a time exposes the
choices, selection places Jamie, and Replay resets the demonstration and focus.
No API requests, real records, tracking expansion or booking side effects.
A shared AppNotice explains the simulated result. Existing FAQs, industry links,
setup support, pricing and feature destinations remain below the new experience.
Existing shared navigation/footer and licensed Manrope font are reused.

## SEO and performance

No metadata, route, canonical generation, schema or indexation policy changed.
Existing SSR renders the Vue page; a new Blade fallback supplies readable main
content and conversion links when SSR is unavailable. Interactions are optional.
Hero is responsive WebP: desktop 189,240 bytes (1604×980), phone 68,374 bytes
(900×550). Hero has dimensions and high fetch priority; supporting images are lazy.
No new animation library or runtime dependency. Reduced-motion CSS disables
transitions and placement animation. This is not a measured Core Web Vitals result.

## Evidence

- Client + SSR production builds passed using Node 24.19.0.
- Public bundle budgets passed for 26 route entries.
- Marketing suite: 302 passed, 2 skipped, 5,107 assertions.
- Homepage: 3 passed, 42 assertions, including server HTML with SSR disabled.
- Mounted Vue sample demo test passed: either selection, double-placement guard,
  booked-slot preservation, replay and focus restoration.
- Local GET returned readable H1, 146 main words, eight links, one title,
  description, canonical and structured-data script, index/follow directive.
  Generic SEO checker compared root URL with trailing slash against existing
  framework root canonical without trailing slash; it reports that exact-string
  mismatch. Canonical generation was not changed in this task.
- In-app browser loaded the new homepage and exposed the controls and destinations.
  A desktop screenshot attempt followed a 1440×1000 viewport request but returned
  a cropped view. Subsequent browser calls reported local access unavailable;
  inventory contained no browsers. No complete desktop/mobile/reduced-motion
  visual certification or interactive browser success evidence is claimed.
- No deployment, live signup/conversion outcome, Google indexing or production
  performance verification.

## Remaining acceptance

Restore browser access, capture full desktop and 390px/360px phone layouts,
compare the hero/demo against the approved reference, test day buttons, both
slots, replay, keyboard focus, reduced motion, FAQ, primary navigation, signup,
pricing and cookie notice. Review support links and long success copy on mobile.
Measure page loading and layout shift before deployment. See root design-qa.md.
