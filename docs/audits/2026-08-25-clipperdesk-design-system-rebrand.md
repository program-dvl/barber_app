# ClipperDesk design-system and rebranding audit

Date: 2026-08-25

Status: Implemented and locally verified. Production launch remains governed by
the open legal, domain, provider, security, and operational gates in
`project-status.md` and `decisions.md`.

## Architecture discovered

The product is Laravel 13 with Inertia, Vue 3, Tailwind CSS 4, DaisyUI 5, and a
separate Filament 5 platform-administration surface. Customer-visible output is
split across Vue public/auth/booking/shop/platform layouts, Blade email and
document templates, SEO metadata/schema, Filament, and static public assets.

`resources/css/app.css` already provided an appropriate semantic-token seam, but
the old Good Hours pine/oat/poppy values, `gh-*` component namespace, duplicated
Jetstream control styling, raw Tailwind palette utilities, document-local colors,
and repeated brand strings meant the theme was not yet a complete single source
of truth. DaisyUI and Filament are retained as existing framework layers; no
parallel styling framework was added.

## Implemented system

- `config/brand.php` centralizes product/company name, promise, description,
  light/inverse logos and marks, favicon, social image, support address, public
  URL, booking host, and the palette values needed by framework-owned surfaces.
- `resources/css/app.css` centralizes semantic brand, background, surface, text,
  border, action, focus, disabled, navigation, semantic-status, radius, shadow,
  type-scale, spacing, and content-width tokens. Retained DaisyUI roles map to the
  same values.
- The component namespace is `cd-*`. Shared buttons, form controls, checkbox,
  cards, navigation, status, dialog, table, empty/loading/error patterns, and
  Jetstream profile controls consume semantic roles.
- Filament receives the same primary color, favicon, brand name, canvas, border,
  and navigation identity. Email, notifications, PDFs, receipts, reports, SEO,
  browser titles, and structured data consume the central brand configuration or
  the shared print partial.

## Visual direction

ClipperDesk uses navy (`#172554`) for trust and structure, indigo (`#4338ca`) for
primary action, restrained cyan (`#0e7490`) for recognition, cool-gray canvas
(`#f5f7fb`), white working surfaces, slate text, and accessible semantic green,
amber, red, and blue states. Hover and active variants are darker indigo; focus
uses a high-contrast blue ring. The palette intentionally moves away from the
washed-out cream/green identity while retaining a calm operational interface.

Self-hosted Manrope 400/500/600/700 is the single public and product family.
Weight, tracking, size, and line height create hierarchy without a second
editorial font. Product screens prioritize 14–16px information density and
public headings use a bounded responsive scale.

The approved asset set contains a flat interlocking C/D monogram, horizontal and
inverse lockups, light/inverse marks, SVG favicon, ICO fallback, and 1200×630
social image. The first dark-tile/gradient concept was removed after review; the
final mark has no enclosing app tile. The public footer was also changed from a
dark violet/navy block to a light neutral continuation of the page.

## Migration coverage

The public homepage, pricing, feature/solution/use-case/resource/trust/legal
pages, authentication, registration, booking/self-service, onboarding,
dashboard, calendar, walk-ins, Clients, Staff placeholder, inventory, reports,
billing/checkout, profile/settings, platform operations, error states, email,
notifications, PDFs, favicon, metadata, Open Graph/Twitter image, and schema now
present ClipperDesk.

Visible plan names are migrated with an exact-match forward-only migration.
Obsolete public Good Hours logo and Newsreader assets are removed. Historical
screenshots remain only in `docs/evidence/` as dated QA history and are no longer
bundled by the homepage.

The remaining `good_hours`/`good-hours` references are intentionally retained
compatibility or historical identifiers:

- Paddle shared-account `application`, plan-code, and interval metadata;
- Stripe payment metadata;
- the `good-hours-client-export-v1` exported schema version;
- the iCalendar UID host, whose stability prevents duplicate calendar entries;
- the original billing migration values and the exact-match rebrand migration;
- `GoodHoursDemoSeeder`, its stable demo slug, and dependent fixtures;
- provider/test IDs; and
- superseded ADRs, dated audits, implementation prompts, and historical evidence.

These do not render as current customer-visible product branding. Renaming them
without a compatibility migration could break provider correlation, imports,
calendar identity, repeatable seeds, or audit history.

## Accessibility and responsive verification

Semantic landmarks, one-H1 page structure, visible labels, named buttons,
focus-visible outlines, reduced-motion behavior, disabled/error semantics, and
mobile focus-managed navigation remain in place. Browser QA at 1440/1488 desktop
and 360/390 mobile widths found no horizontal page overflow and no visible old
brand copy on checked pages. The marketing and tenant drawers moved focus into
the menu. Mobile QA discovered undersized dashboard-report, billing-interval,
and invoice-document targets; those were raised to the 44px interaction target.

Representative browser coverage: homepage, pricing, login, registration, public
booking, 404, dashboard, calendar, Clients, Staff, billing, business setup,
platform administration, and public/shop mobile navigation. New evidence lives
under `docs/evidence/product-shell/`.

## Verification

- `vendor/bin/pint --test`: passed.
- `npm run check:frontsite-budgets`: passed for 17 route entries.
- `npm run build`: client and SSR production builds passed. Vite reports only
  retained DaisyUI `@property` optimizer and runtime public-font resolution
  warnings.
- Marketing/design regressions affected by centralization: passed after updating
  assertions to the `cd-*` namespace and `config('brand.description')` contract.
- Full PHP suite: **255 passed, 2,296 assertions, 28 intentional skips, and one
  unrelated failure** in 19.24 seconds. All rebranding/design-system tests pass.
  The unrelated
  pre-existing date-sensitive scheduling test remains: it attempts to create a
  fixed `2026-08-17T10:00` appointment while the current date is 2026-08-25, so
  no appointment is created before `firstOrFail()` at
  `CalendarWalkInOperationsTest.php:274`. Dedicated destructive MySQL concurrency
  tests remain intentionally skipped unless explicitly enabled.

## Decisions still required

OPEN-11 must be resolved before public launch: counsel-led ClipperDesk trademark
clearance, final production domain acquisition, defensive-domain policy, and
verified sender/DNS configuration. Existing legal/operator and live-provider
launch gates also remain unchanged by this visual refactor.
