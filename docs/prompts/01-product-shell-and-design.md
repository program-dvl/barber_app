# Prompt 01: Product shell and design foundation

Work in `/Applications/AMPPS/www/barber_app`.

Establish the reusable product shell and design foundation for the barber shop
and salon SaaS. This prompt covers navigation, layout, design tokens,
accessibility, responsiveness, and product branding integration. It does not
implement scheduling, CRM, payments, or other domain workflows.

Read root `AGENTS.md`, `docs/README.md`, `docs/project-status.md`,
`docs/product-brief.md`, PRD Sections 3, 4, 10, and 12,
`docs/architecture.md`, `docs/quality-and-testing.md`, and
`docs/decisions.md`. Inspect the existing Vue/Inertia components and visual
conventions before changing them.

Resolve OPEN-01 before making permanent brand choices. If brand details are not
available, implement neutral semantic tokens and document temporary values
instead of inventing a final identity.

Build:

- a responsive authenticated application shell for Dashboard, Calendar,
  Walk-in Queue, Clients, Checkout/Sales, Staff, Services, Inventory, Reports,
  Settings, and Subscription/Billing;
- a distinct mobile-first public shell for booking and secure self-service;
- a distinct, clearly marked platform-admin shell;
- semantic color, typography, spacing, elevation, focus, status, empty, loading,
  error, and success patterns;
- keyboard and touch-friendly navigation with preserved context;
- permission-aware navigation presentation without treating hidden links as
  authorization; and
- reusable page-header, action, form, table, card, dialog, toast, skeleton, and
  destructive-confirmation conventions.

Use real product language from the docs, not generic Larafast copy. Avoid
building fake domain data flows. Preserve clear placeholders for modules not yet
implemented.

Verify at 360 px, tablet, and desktop sizes; keyboard navigation; visible focus;
contrast; reduced-motion behavior; screen-reader labels; loading/empty/error
states; frontend build; and relevant automated tests. Use screenshots or
browser evidence for the key shells.

Update `docs/project-status.md`, `docs/architecture.md`, and any accepted design
decision. Add `docs/design-system.md` only if it records concrete implemented
tokens and component rules. End with changed surfaces, accessibility evidence,
remaining brand decisions, and exact verification results.

