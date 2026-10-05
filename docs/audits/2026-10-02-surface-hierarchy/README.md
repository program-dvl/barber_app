# Workspace surface hierarchy refinement

2026-10-02. Limited follow-up to the owner's feedback that the light interface
felt excessively white/gray. This is a shared presentation change, preserving
compact sizing, workflows, user-entered data and business behavior.

## Changes

- Distinct cool tinted canvas, light navigation, toolbars and table headings,
  with white record bodies and subtle panel elevation.
- Navy primary/body/secondary text and stronger label contrast.
- Existing indigo used for record avatars and selected/action states.
- Both authenticated layouts use consistent desktop/mobile navigation surfaces
  and topbar treatment. Public palette and semantic status colors are unchanged.

## Fresh visual inspection

Isolated SQLite demo database and local server; no existing business data changed.
Client dialog opened and cancelled, without submitting any client/payment/message.

| Screen | Size | Checked |
| --- | --- | --- |
| Clients | 1280×800 | 8 records, canvas/navigation/toolbar/header separation |
| Add client dialog | 1280×800 | Persistent labels, optional metadata, input contrast |
| Clients | 390×844 | Record readability and no document-level horizontal overflow |
| Mobile navigation | 390×844 | Tinted surface, current selection, readable labels |
| Services | 768×1024 | Table headings, filters, badges; table scrolling remains local |
| Dashboard | 1280×800 | Empty appointment state, metrics and attention panel |

Screenshots are alongside this report. Computed styles confirmed the actual
workspace palette. Text contrast ratios: body on white **10.00:1**; secondary
text on canvas **5.46:1**; table labels on heading surface **6.90:1**; indigo
avatar text **7.07:1**; page title on canvas **13.66:1**. These sampled pairs
meet WCAG AA normal-text contrast; this is not an exhaustive accessibility audit.
Browser warning/error log was empty at the final inspection.

## Checks and limits

- Client and SSR production builds passed.
- Public asset budgets passed for 17 route entries without changing limits.
- Existing frontend tests: 9 passed; diff checks passed.
- Backend logic unchanged; full PHP suite was not rerun for this style-only pass.
- Only the screens/states above were freshly rendered. Protected platform admin,
  other roles, enlarged text, public booking and the remaining modules were not
  rerun. The preceding platform audit remains separate evidence.
- Build retained existing DaisyUI at-rule and Manrope asset-resolution warnings;
  no browser warning/error was observed on the reviewed screens.
