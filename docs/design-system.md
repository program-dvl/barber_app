# ClipperDesk UI/UX standards and product design system

Status: Canonical standard; the ClipperDesk visual and component foundation is
implemented, while individual product surfaces may still require review against
this document.

Last standards review: 2026-09-13

Last verified foundation evidence: 2026-08-25

Related requirements: FR-01 through FR-20, especially FR-02, FR-06, FR-08,
FR-09, FR-18, FR-19, and PRD Sections 3, 4, 10, and 12.

## Purpose, authority, and scope

This is the central UI/UX standard for ClipperDesk. Developers, designers,
reviewers, and coding agents must use it before creating or modifying any page,
feature, form, dashboard, modal, drawer, workflow, or reusable component.

It applies to the authenticated shop application, public booking and secure
self-service, platform administration, authentication, billing, generated
documents, and user-facing messages. It defines product-experience and
interaction rules; `product-requirements.md`, accepted records in
`decisions.md`, and the relevant module document remain authoritative for
product scope and domain behavior. If a UI proposal conflicts with those
sources, resolve or record the product decision rather than silently changing
behavior in the interface.

The words **must**, **should**, and **may** are normative:

- **must** is required unless an accepted decision records an exception;
- **should** is the default and needs a documented reason to depart; and
- **may** is optional guidance.

Adding a rule here does not claim that every existing screen already complies.
Verified implementation facts remain in `project-status.md`.

## Prompt 01–09 adoption baseline

The 2026-08-15 implementation review applied this standard to every existing
user-facing Vue surface delivered by Prompts 01–09. The shared shop shell,
authentication, subscription and billing, business configuration, operational
calendar, walk-in queue, public booking and secure self-service, Client CRM,
forms, consent, privacy, and platform readiness surfaces now share the same
hierarchy, tokens, controls, state language, and responsive conventions.

Dashboard users may choose among **Command desk**, **Rhythm board**, and
**Guided front desk**. The preference is remembered per signed-in user on the
current device. These are alternate presentations of the same tenant-scoped
calendar facts; they must not create separate operational truth or bypass
permission filtering.

The public booking journey remains a single focused step flow. Multiple public
views would add choice without helping the client complete the booking and are
therefore outside this pattern.

The public acquisition shell uses a white, lightly elevated sticky header over
the cool-gray product canvas. Navy establishes trust and structure, indigo is
reserved for primary actions, and cyan is a sparse identifying accent. The
footer and authenticated navigation use the same deep-navy closing frame.

## Experience standard: premium through clarity

ClipperDesk aims to be one of the world's best service-business software
experiences. Here, “premium” means calm, deliberate, fast, dependable, and
beautifully resolved. It does not mean ornamental, fashion-led, visually loud,
or black-and-gold luxury. The product must feel credible while a receptionist
is handling a queue, an owner is reviewing money, a stylist is with a client,
or a client is booking on a phone.

Every surface must demonstrate:

- a clear purpose and information hierarchy;
- one obvious next action where the workflow has a natural next step;
- restrained typography, spacing, colour, and elevation;
- the least complexity needed for the user's goal;
- immediate, honest feedback for system activity and results;
- thoughtful loading, empty, error, success, disabled, and permission states;
- responsive, touch-friendly, keyboard-operable behavior; and
- consistent language and components across the product.

Do not ship raw admin panels, database-shaped forms, generic CRUD screens,
unstructured field walls, decorative dashboards, or developer-facing
terminology as finished product UI. A technically complete screen is not a
complete experience.

## Non-negotiable product principles

### Design around the user's job

Start with the person and outcome, not the table schema or controller payload.
Before proposing a surface, identify:

1. the user and the context in which they are working;
2. the result they need, not merely the record they need to edit;
3. the information required to make the next decision;
4. the primary action and the most likely follow-up;
5. secondary and rare actions that can be deferred;
6. what the system can infer, remember, prefill, or automate; and
7. the failure, interruption, permission, and recovery paths.

The visible interface may group, rename, summarize, derive, or progressively
reveal backend data when that creates a clearer and safer workflow. It must not
expose internal identifiers, implementation constraints, enum keys, or storage
shape unless the user genuinely needs them.

### Optimize for salon operations

Owners, branch managers, receptionists, stylists, beauticians, therapists,
cashiers, inventory managers, accountants, and platform operators have
different priorities and permissions. Many shop users will operate ClipperDesk
while speaking with a client or delivering a service. The most frequent
workflows—booking, finding availability, check-in, staff assignment, payment,
schedule review, client lookup, stock updates, and today's performance—should
require few interactions and little typing.

Prefer recognition over recall. Keep today's work and time-sensitive exceptions
prominent. Preserve useful context such as Business, Location, local date,
filters, selected Staff, and the record the user came from. Do not force users
to reconstruct context after every save, modal, or page transition.

### Reduce cognitive load

Every field, label, card, statistic, button, confirmation, modal, and page
transition must earn its place. Remove repeated information and secondary
controls before adding visual treatment. Use whitespace and grouping to
express relationships; do not use a card, divider, icon, badge, or colour merely
to make a screen look busy or “premium.”

Users should always be able to answer:

- Where am I?
- What am I looking at?
- What requires attention?
- What happens if I take this action?
- Was the action successful, and what should I do next?

### Prevent errors instead of cleaning them up

Prefer this path:

> Controlled input → contextual guidance → validation → normalization → storage

Do not default to:

> Free text → arbitrary input → invalid or inconsistent data → later cleanup

Make impossible states difficult or impossible to create, but do not hide a
real business consequence behind an unexplained constraint.

### Make consequential behavior explicit

Smart defaults should reduce work, not surprise the user. A default affecting
money, tax, client consent, public availability, communications, historical
records, or destructive behavior must be visible, understandable, and
reviewable before it takes effect. Never infer legal or financial policy.

## Information architecture and page composition

### Page hierarchy

A product page should normally contain, in order:

1. persistent shell and current Business/Location context;
2. one page title describing the user's destination or outcome;
3. concise supporting context only when it changes the user's decision;
4. primary and secondary page actions;
5. the main working area; and
6. supporting detail, history, or advanced controls.

There must be only one page-level `h1`. Keep the primary action visually
dominant and place it where the user will look when ready to act. Do not present
several unrelated primary buttons. Preserve route-relevant filters, date,
Location, view, and selection in the URL when practical so back/forward,
refresh, links, and recovery behave predictably.

### Choosing a complexity pattern

Long, unrelated forms are prohibited unless research or a documented workflow
shows that a single continuous view is materially better. Choose the smallest
pattern that matches the mental model:

| Pattern | Use when | Avoid when |
| --- | --- | --- |
| Sections in one page | A short task has a few strongly related groups | Groups are independent or the page becomes difficult to scan |
| Tabs | Users switch between peer categories and may work in any order | The task is sequential, errors would be hidden, or users must compare panels |
| Step flow | Steps depend on earlier answers or a safe review is required | Expert users repeatedly edit one isolated value |
| Accordion / Advanced | Optional or rare detail can stay collapsed | Content is required, contains unresolved errors, or must be compared |
| Card | One meaningful, bounded concept needs a visual group | It is purely decorative or creates deeply nested containers |
| Drawer | A short contextual task should preserve the underlying page | The task is complex, needs deep navigation, or has major consequences |
| Modal | A focused decision or confirmation blocks the current flow | It contains a long form, reference-heavy work, or another modal |
| Dedicated page | A task is complex, consequential, linkable, or benefits from focus | It is a tiny adjustment that can be completed in context |

Show common and required choices first. Put rare or technical options under a
clearly named advanced area. Never hide errors, required fields, destructive
consequences, or safety information inside a collapsed section.

### Navigation and continuity

Prioritize today's work and keep high-frequency actions within one or two
interactions, as required by PRD Section 10. Navigation labels must be stable,
plain-language, and permission-aware. Hiding an unauthorized destination is
presentation only; the server must still enforce access.

After a task, return the user to the most useful state rather than an arbitrary
index. Preserve filters and scroll position when it helps, show the changed
record, and provide a clear route to the likely next action. Warn before
discarding meaningful unsaved work.

## Forms and structured data entry

### Form structure

Each form must have one clear purpose and a save boundary users can understand.
Group fields by user goal, not database model. Required and optional fields must
be distinguishable in text; do not rely on colour or an unexplained asterisk.
Explain why sensitive or unusual information is needed at the point of entry.

For large configuration tasks:

- show the minimum viable path first;
- separate everyday settings from advanced settings;
- use steps only when order or review matters;
- keep save actions scoped to the visible concept;
- preserve entered data across validation and recoverable failures;
- show completion as facts or named blockers, never a vague percentage; and
- support safe interruption and resumption when the workflow is not brief.

### Input selection matrix

Text input is for genuinely free or user-authored content, not a universal
fallback. Use the nature and size of the value set to choose the control.

| Data or decision | Default control | Requirements |
| --- | --- | --- |
| Boolean setting | Toggle for immediate settings; checkbox for form agreement or batch selection | Use a positive label that states the enabled outcome; communicate immediate-save behavior |
| 2–4 exclusive choices | Radio group or segmented control | Show all meaningful options and a short consequence when needed |
| Small predefined list | Native select or accessible listbox | Use user-facing labels, not internal enum values |
| Large predefined list | Searchable combobox/autocomplete | Support keyboard search, no-result state, clear selection, and accessible announcements |
| Multiple options | Checkbox group, multi-select, or tokenized combobox | Show selected count and make removal clear; do not hide selection limits |
| Country | Searchable country selector | Show country name; store a normalized ISO code |
| State / region | Country-dependent selector, searchable when large | Load only valid regions and permit a documented free-text fallback where the source is incomplete |
| Language / locale | Searchable language or locale selector | Use human-readable names and regional variants; store a normalized code |
| Time zone | Searchable time-zone selector | Show recognizable city/region and current UTC offset; store an IANA identifier |
| Currency | Currency selector | Show name, ISO code, and symbol where helpful; store normalized ISO currency |
| Status | Controlled selector or workflow actions | Offer only valid transitions; do not expose a raw status enum |
| Date | Date picker with keyboard-editable fallback | Use locale-aware display and explicit bounds |
| Time | Time picker | State the governing Location time zone when ambiguity is possible |
| Date and time | Date-time picker or composed date/time controls | Preserve local intent and communicate time zone/DST consequences |
| Duration | Preset choices plus constrained custom option when needed | Display in human units, not raw minutes alone |
| Money | Currency-aware numeric input | Show currency, decimal expectations, and formatted review value; never ask for “minor units” |
| Percentage | Numeric input with `%` affordance | Define min/max, precision, and whether the value is inclusive |
| Quantity | Stepper or constrained numeric input | Use sensible bounds and unit labels |
| Phone number | International phone input | Support country code, input formatting, and normalized E.164 storage without blocking valid paste |
| Address | Address search plus editable structured fallback | Do not make third-party lookup a hard dependency |
| Colour | Accessible colour picker plus value/name when colour is meaningful | Never make colour the sole stored or communicated meaning |
| File / image | Purpose-built upload | State accepted types, size, privacy, progress, error, preview, replace, and remove behavior |

If a structured data source is temporarily unavailable, record the limitation
and provide a safe fallback. Do not permanently encode a free-text control
because the master data or reusable component has not yet been built.

### Central option sources and reusable controls

Countries, regions, locales, languages, time zones, currencies, service types,
statuses, categories, roles, and other shared option sets must come from
central, versioned sources. Do not copy option arrays into individual pages.
Domain-owned lists such as roles, service categories, and valid status
transitions must also be tenant-scoped and permission-aware where applicable.

Once ClipperDesk has a compliant selector or field pattern for a shared value,
reuse it everywhere. Variation is acceptable only when the user context changes
the interaction materially, not because a page was built separately.

### Smart defaults and inference

Provide sensible defaults when confidence is high and the consequence is low:

- suggest time zone from the selected Location or device and let the user
  verify it;
- suggest locale and currency from Business location without treating that as
  tax or legal policy;
- use common duration and interval presets;
- inherit Location or Business values when the relationship is visible;
- remember safe, user-specific working preferences such as last Location or
  calendar view; and
- normalize casing, whitespace, phone numbers, URLs, codes, and monetary
  formatting without changing meaning.

Never silently overwrite an intentional choice. Explain inherited values and
provide a clear override or reset-to-default action.

### Contextual validation

Validation belongs with the affected control. Every invalid field must have a
text error connected programmatically to that field, a visible invalid state,
and a message that explains how to fix the issue. Preserve the user's input and
focus the first invalid field or a linked summary after submission.

Use validation timing deliberately:

- prevent impossible characters or values where doing so does not block valid
  input methods or paste;
- validate format after blur or when enough input exists, not on every
  keystroke by default;
- validate cross-field and server-owned rules on submit or at the point a
  dependent decision is made;
- show asynchronous checks such as slug availability near the control and
  distinguish checking, available, unavailable, and failed-to-check states;
- clear an error when it has genuinely been resolved; and
- keep a page-level summary only as a complement for navigation, never as the
  only explanation.

Messages must use normal language and must not expose exception text, table or
column names, regex rules, internal codes, stack details, or private scheduling
reasons. Client-side prevention improves the experience; server-side
validation and authorization remain authoritative.

## Actions, feedback, and system states

### Action hierarchy

Use one primary action per working context. Secondary actions support the
primary task; quiet actions are low-emphasis; danger actions are reserved for
destructive or difficult-to-reverse consequences. Use verbs that describe the
result: “Save booking rules,” “Check in client,” or “Issue refund,” not generic
labels such as “Submit,” “OK,” or “Process.”

Do not disable an action without an understandable reason. If an action is
temporarily unavailable, explain what is missing and provide the next step when
possible. Prevent accidental duplicate submission and keep idempotent retries
safe.

### Destructive, financial, and high-impact actions

Require confirmation only when it protects against a meaningful consequence.
The confirmation must name the affected object, state what will happen, explain
whether it can be undone, and give initial focus to the safe action. Never use a
generic “Are you sure?” prompt.

For money, tax, availability, communication, consent, privacy, staff access, or
historical changes, provide an impact preview when the consequence is not
obvious. Never imply that hiding or deleting a UI row erased append-only or
audited history.

### Required states

Every important page and component must intentionally define the applicable
states below:

| State | Experience requirement |
| --- | --- |
| Default | Clear purpose, current value, and available action |
| Hover | Helpful visual affordance without carrying unique meaning |
| Focus | Strong visible focus; logical keyboard order |
| Active / selected | Text, shape, or icon cue in addition to colour |
| Disabled / unavailable | Reason and recovery path when not self-evident |
| Loading | Preserve layout, announce activity, and prevent unsafe duplicates |
| Empty | Explain why it is empty and offer a relevant first or recovery action |
| Error | State what failed, what remains safe, and how to retry or recover |
| Success | Confirm the completed outcome and surface the logical next step |
| Stale / conflict | Preserve work, explain the newer state, and support review rather than blind overwrite |
| Partial / offline | Distinguish saved, pending, and failed work; never claim completion early |

Use skeletons when the page shape is known, a compact progress indicator for a
short bounded action, and named step/progress detail for a long job such as an
import or export. Avoid layout jumps and indefinite spinners without context.
Do not use optimistic success for actions that can fail with a consequential
result.

### Notifications

Use inline feedback for input and workflow errors, banners for persistent or
page-wide conditions, toasts for brief confirmations that do not require a
decision, and dialogs only when the user's attention must block the current
flow. Notifications must be dismissible when safe, accessible to assistive
technology, and must not be the sole record of a consequential outcome.

## Data-heavy and operational surfaces

### Dashboards

Dashboards must answer a defined operational question, not display every metric
available. Prioritize exceptions, next actions, and today's state. Every metric
must have a clear time range, Location/time-zone context, data-freshness state,
and a route to the records behind it when the PRD requires drill-down. Avoid
vanity metrics and decorative charts.

### Tables, lists, search, and filters

Choose tables for comparison across consistent columns and lists/cards for
scan-and-act workflows or narrow screens. Provide a meaningful default sort,
plain-language column names, an accessible caption, and explicit empty/no-match
states. Keep row actions discoverable without making every row visually noisy.

Search and filters must:

- use labels and user-visible selected values;
- distinguish no data from no matching results;
- support clearing individual filters and all filters;
- preserve useful state in the URL when practical;
- show the active-filter count on constrained screens; and
- avoid requiring exact internal identifiers for normal work.

Do not compress essential information below readable or touchable sizes. On
small screens, prioritize, stack, summarize, or provide a labelled internal
scroll region rather than creating page-level horizontal overflow.

### Calendars and queues

Time, Staff, Location, service, appointment/walk-in state, and conflicts must
not rely on colour alone. Preserve the current date and view during actions.
Drag, resize, and reorder interactions require an accessible non-pointer
alternative and a clear confirmation when a change affects scheduling.

## Responsive and touch-friendly behavior

Design from the user's task across widths, not from a desktop screenshot that
is later compressed. Core journeys must work from 360 CSS pixels through
desktop. Content should reflow before it shrinks; primary actions must remain
reachable; no essential control may depend on hover.

Touch targets must be at least 44 by 44 CSS pixels, with enough separation
for use at a busy front desk. Authenticated desktop workspaces may use 40px
standard controls, 32px quiet row actions and 36px menu options on fine-pointer
devices (ADR-037); mobile and coarse-pointer styles restore 44px targets. Respect safe areas and virtual keyboards. Keep
forms single-column where width or reading order demands it. Do not trap mobile
users in wide tables, nested scrolling containers, or full-screen keyboards
without a visible way to continue.

Desktop density may increase for expert operational workflows, but the order,
labels, permissions, and outcomes must remain consistent across form factors.

## Accessibility

WCAG 2.2 AA is the target for public booking and core shop workflows and the
default expectation for all new UI. Accessibility is part of implementation
quality, not a later polish pass.

Every UI change must consider:

- semantic HTML and correctly ordered headings and landmarks;
- visible labels and accessible names for all controls;
- complete keyboard navigation, logical focus order, and focus restoration;
- the implemented `:focus-visible` treatment;
- contrast for text, controls, boundaries, icons, and states;
- text/icon/shape cues so meaning never relies on colour alone;
- programmatically associated hints, errors, and status updates;
- screen-reader announcements for asynchronous results when appropriate;
- zoom, text resizing, reflow, and 360px behavior;
- reduced-motion preferences; and
- clear disabled, read-only, selected, current, expanded, and invalid states.

Placeholders are examples, not labels. Icons that trigger an action require an
accessible name. Tooltips cannot hold information needed to complete the task.

## Time, money, language, and location

ClipperDesk operates across Locations and potentially across time zones. Show
local dates and times using the governing Location's IANA time zone, and name
the time zone where ambiguity or consequence exists. Preserve original local
intent where the domain requires it and handle daylight-saving gaps and repeats
without asking users to reason in UTC.

Format dates, times, numbers, currency, percentages, names, addresses, and phone
numbers according to locale while storing normalized domain values. Do not
hard-code symbols, decimal assumptions, first-day-of-week rules, address shape,
name order, or string concatenation. UI copy and message templates must be
structured for translation and expansion.

The India/`en-IN` communications fallback accepted in ADR-019 does not remove
the need for Location time zones or grant permission to infer unresolved tax,
receipt, or privacy policy.

## Public acquisition shell and components

The Phase 1.5 marketing surface uses `MarketingLayout.vue`; it does not reuse
the authenticated sidebar or tenant `PublicBookingLayout`. The shell owns a
skip link, semantic header/primary navigation/main/footer landmarks, responsive
controlled mobile navigation and the global conversion action. Pages own their
single `h1`, metadata, content rhythm and contextual links.

Marketing components live in `resources/js/Components/Marketing/`:

| Component | Contract |
| --- | --- |
| `PublicContainer` | One centered 76rem maximum content boundary with a 1rem mobile gutter |
| `SectionHeading` | Optional eyebrow plus semantic configurable heading level; a page must not use it to create a second `h1` |
| `PublicCta` | Requires a privacy-safe context string; anonymous users reach registration, authenticated users reach Dashboard |
| `MarketingCard` | Calm raised content surface; never a substitute for semantic link/button behavior |
| `Breadcrumbs` | Visible ordered hierarchy using real links and `aria-current`; Prompt 23 derives matching JSON-LD |
| `FaqList` | Native `details`/`summary`; visible answers only; schema eligibility is separate |
| `ComparisonTable` | Semantic table in a labelled, keyboard-focusable horizontal region at narrow widths |
| `ProofFrame` | Reserved visual surface plus required factual caption; media must be synthetic/cleared and dimensioned |
| `ConversionBand` | One contextual close using `PublicCta`; never introduces a demo/contact/newsletter promise |

The mobile navigation exposes deterministic expanded state, closes on Escape,
outside pointer interaction and route change, returns focus on Escape, constrains
its viewport height and locks background scrolling while open. Global links are
filtered to named routes that currently exist so sequential delivery never
creates a public dead link. As approved hubs land, the same config exposes
Product, Solutions, Use cases, Pricing and Resources.

Public components use the ClipperDesk semantic palette and Manrope type system.
They do not introduce a second token set,
theme switcher, animation framework, social links, announcement, newsletter or
third-party tracker.

## Trust, privacy, permission, and auditability

Show only data and actions the current role needs, but never treat hidden UI as
authorization. Sensitive client, money, staff, consent, and support information
must follow least privilege and the tenant/access rules in the relevant module.

Collect the minimum information necessary. Explain why sensitive information is
requested, avoid exposing it in previews and notifications, and make private or
audited consequences clear. Access-denied states must not disclose whether a
cross-tenant record exists.

When a change is versioned, effective-dated, append-only, or audited, the UI
should communicate that model in user language. Show who/when/why history only
to authorized users, and never offer a visual “delete” that contradicts domain
retention rules.

## Performance and resilience

Performance is part of the experience. Follow the PRD target that typical
authenticated screens become usable within 2.5 seconds at p75 and common
calendar actions acknowledge within 500ms and commit within 2 seconds under
target load.

Render the most decision-relevant content first, avoid blocking a whole page
for a secondary panel, and use stable layout placeholders. Debounce expensive
search without making typing feel delayed. Paginate or virtualize only when the
data volume warrants it and preserve accessibility. On failure, state what was
and was not saved, keep safe user input, and provide an idempotent retry or
recovery route.

## Microcopy and content design

Write for the user, not the implementation. Labels, headings, hints, errors,
confirmations, and empty states must be short, concrete, consistent, and
action-oriented where appropriate.

- Use sentence case.
- Prefer familiar salon and service-business language from the glossary and
  PRD.
- Name the object and result: “Appointment cancelled” rather than “Success.”
- State local date, time, Location, money, and consequence when material.
- Explain recovery without blame: “Your appointment is unchanged. Try again.”
- Do not expose raw enum values, field names, IDs, provider codes, or backend
  exceptions.
- Avoid hype, beauty clichés, artificial urgency, vague reassurance, and
  unsupported claims.
- Avoid technical abbreviations such as ISO, IANA, E.164, TTL, UTC, or “minor
  units” in normal UI; translate them into user language.

## Brand posture

The product is **ClipperDesk**. Its acquisition brand promise is **Your whole
day. Beautifully run.** The product remains positioned around the reliable
booking-to-checkout operating loop. The identity should feel operational,
dependable, modern, energetic, and approachable. It is not barber-only,
childish, or coded to a single luxury tier.

The geometric mark is a restrained, interlocking **C** and **D** monogram with
no enclosing app tile. It remains legible in compact navigation and signals a
software product without relying on generic scissors, barber poles, or clip
art. Approved SVG assets live under
`public/images/brand/`; the source mark is
`resources/images/brand/clipperdesk-mark.svg`. Do not redraw, stretch, or
substitute the mark with a text glyph. Preserve clear space equal to at least
one quarter of the mark width. Use the horizontal lockup above 180px of
available width and the mark-only form where the product name is already
announced.

The voice is calm, capable, human, and clear:

- lead with the outcome or current state;
- use sentence case, short sentences, and concrete next steps;
- state local date, time, location, money, and consequences explicitly;
- reassure without hiding risk: “Your appointment is unchanged. Try again.”;
- never blame the user, create artificial urgency, or make unsupported claims;
- avoid beauty clichés, salon slang, “AI-powered”, and generic starter-kit copy.

Product language remains aligned with the PRD: Walk-in Queue, Checkout /
Sales, secure self-service, support access, and Subscription & Billing.

## Implemented tokens

Tokens live in `resources/css/app.css`. Components consume semantic names, not
raw palette names.

| Role | Token | Implemented value |
| --- | --- | --- |
| Brand primary | `--brand-primary` / `--brand-primary-strong` | `#172554` / `#0f172a` |
| Brand secondary | `--brand-secondary` / `--brand-secondary-hover` | `#4f46e5` / `#4338ca` |
| Brand accent | `--brand-accent` / `--brand-accent-soft` | `#0e7490` / `#cffafe` |
| Primary canvas | `--surface-canvas` | `#f5f7fb` |
| Subtle surface | `--surface-subtle` | `#eef2f7` |
| Raised surface | `--surface-raised` | `#ffffff` |
| Inverse surface | `--surface-inverse` | `#0f172a` |
| Strong text | `--text-strong` | `#0f172a` |
| Default text | `--text-default` | `#334155` |
| Muted text | `--text-muted` | `#64748b` |
| Accessible primary action | `--action-primary` | `#4338ca` |
| Primary hover / active | `--action-primary-hover` / `--action-primary-active` | `#3730a3` / `#312e81` |
| Focus ring | `--focus-ring` | `#0369a1` |
| Information | `--status-info` / `--status-info-soft` | `#0369a1` / `#e0f2fe` |
| Success | `--status-success` / `--status-success-soft` | `#047857` / `#d1fae5` |
| Warning | `--status-warning` / `--status-warning-soft` | `#a16207` / `#fef3c7` |
| Danger | `--status-danger` / `--status-danger-soft` | `#b91c1c` / `#fee2e2` |
| Disabled | `--surface-disabled` / `--text-disabled` | `#e2e8f0` / `#64748b` |
| Radius | `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl` | `6px`, `10px`, `14px`, `20px` |
| Raised elevation | `--shadow-raised` | two-layer cool-neutral shadow |
| Overlay elevation | `--shadow-overlay` | high-separation overlay shadow |

Typography uses self-hosted Manrope at 400, 500, 600, and 700 for display,
product UI, booking, tables, forms, and outbound messages. A single family
keeps the public and authenticated experiences related while weight, tracking,
size, and line height provide hierarchy. Font files and their SIL Open Font
License live under `public/fonts/clipperdesk/`. The operative scale is 12px for
eyebrows and supporting labels, 14-16px for product content and controls,
18px for lead copy, and a responsive 32-56px range for public section titles.
Authenticated page titles remain deliberately smaller. The shell uses a 4px
spacing base, 40px authenticated desktop controls (44px on touch devices),
and 48-64px primary mobile targets where the workflow benefits from them.

## Domain and message identity

Product name, company name, asset paths, public description, website URL,
booking host, support address, and the small server-to-client brand projection
live in `config/brand.php`. Environment-specific production domains and sender
addresses remain configuration, not component copy. The local booking-host
default is `book.clipperdesk.com`; it is a naming placeholder and does not
assert domain ownership. Do not publish links or send production mail until
trademark clearance, acquisition, DNS, SPF, DKIM, DMARC, return-path, bounce
handling, reply handling, and provider verification are complete.

## Shell rules

### Authenticated shop application

- Desktop uses a persistent 288px navigation rail and explicit working-shop
  context.
- Mobile and tablet use a top bar, focus-managed navigation drawer, and a
  five-destination quick-navigation bar.
- The required PRD destinations are always represented. A
  `navigationVisibility` map may remove presentation links after the server has
  evaluated permissions. It is never an authorization mechanism.
- Module URLs are stable and route-owned so later date, location, and filter
  context can be represented in the URL rather than hidden component state.
- Unimplemented modules render scoped placeholders with requirement IDs and
  explicit non-goals. They do not render sample appointments, clients, money,
  inventory, or reports.

### Public booking and self-service

- This is a separate mobile-first layout, not a reduced staff shell.
- Booking progress is textual and non-colour-dependent. At step zero it states
  that the client is ready to start; future steps use `aria-current="step"`.
- Secure self-service is visibly distinct from new booking and never asks for
  a raw reference while token verification is unimplemented.
- Public copy must state the governing local time zone when time becomes part
  of a workflow.

### Platform administration

- Deep navy navigation, cyan accent, and the persistent `Platform admin` label
  distinguish platform operations from tenant work without introducing a
  separate brand palette.
- The shell uses the existing global `admin` role gate. Ordinary users receive
  HTTP 403.
- Platform role does not grant tenant entry. The persistent warning states that
  support access is inactive until reasoned, scoped, expiring, visible, audited
  grants are implemented.

## Reusable component rules

| Component | Rule |
| --- | --- |
| `PageHeader` | One page-level `h1`, optional eyebrow/description, actions wrap rather than overflow. |
| `AppButton` | Primary, secondary, quiet, and danger variants; 40px workspace desktop / 44px touch height; disabled state uses native `disabled` plus `aria-disabled`. |
| `FormField` | Visible label is required; hint and error IDs are passed to the input through slot props; required and error meaning is textual. |
| `DataTable` | Requires a screen-reader caption and receives keyboard horizontal scrolling when content exceeds the container. |
| `SurfaceCard` | Raised grouped content with optional heading, description, and actions. Do not nest cards for decoration. |
| `AppDialog` | Native modal dialog, labelled title/description, cancel receives initial focus, Escape cancels, destructive confirmation uses danger language and styling. |
| `ToastRegion` | Polite live region by default; error messages use alert semantics; every message has a 44px dismiss action. |
| `SkeletonBlock` | Announces loading with `role="status"` and `aria-busy`; animation stops under reduced motion. |
| `StatePanel` | Shared empty, loading, information, error, and success structure with icon plus text so status never relies on colour alone. |

Destructive actions require an explicit dialog naming the affected object and
consequence. The safe action receives initial focus. Success toasts confirm the
result; error states explain recovery without removing the user's input.

## Accessibility and responsive rules

- WCAG 2.2 AA is the target for public booking and core shop work.
- Global `:focus-visible` uses a 3px blue ring with a 3px offset.
- Skip links target a focusable main landmark on all three shells.
- Mobile drawers use `role="dialog"`, `aria-modal`, an accessible name, focus
  entry, focus wrapping, Escape close, and focus return.
- Touch controls are at least 44 by 44 CSS pixels. The bottom shop navigation
  uses 64px rows plus safe-area padding.
- At 360px there must be no page-level horizontal overflow. Tables may scroll
  inside their labelled wrapper.
- Motion is optional. The reduced-motion media query collapses transitions and
  animations to 0.01ms, prevents repeats, and disables smooth scrolling.
- Every loading, empty, error, and success state has a textual title and may
  include a recovery action. Error and success states use live-region roles.

## Current Settings page: documented review example

The current Settings destination is served by the business-configuration setup
surface. It is an implementation baseline, not the approved final Settings
experience. This standards update intentionally makes no change to that page.

The current surface demonstrates problems these standards are intended to
prevent:

- many business identity, regional, contact, legal, booking, availability,
  import, preview, and publishing concerns appear in one long page;
- country code, locale, currency, and time zone are unrestricted text inputs
  even though ClipperDesk knows or can centrally source valid values;
- technical representations such as codes, minutes, and price in minor units
  are exposed directly to normal users;
- an aggregate “check the highlighted fields” notice is detached from the
  affected controls, while individual field errors are not presented
  contextually; and
- readiness information is useful for publishing but must not replace inline
  form guidance or make unrelated editing feel like one large launch task.

When the Settings experience is reviewed in a separate task, the team should
first map the settings jobs and frequency by role. It should then evaluate a
clear information architecture for concepts such as Business profile, regional
defaults, Locations and hours, services and resources, staff, booking rules,
communications, legal/privacy, imports, and publishing. That review must not
blindly turn this list into tabs; it must choose page, section, step, drawer, and
advanced patterns based on real workflows.

At minimum, the future Settings design must use shared structured selectors for
country, locale/language, currency, time zone, statuses, and other known option
sets; display human units rather than storage units; connect validation and
recovery to each affected control; use sensible and reviewable defaults; and
keep advanced or infrequent configuration out of the common path.

This example is a review benchmark, not a completed redesign or authorization
to modify Settings within the current documentation-only task.

## Mandatory workflow for every UI task

No significant UI implementation should begin from a list of backend fields.
Before code changes, the task owner must complete the following lightweight
experience brief in the task plan, design note, or pull-request description:

| Question | Required answer |
| --- | --- |
| User and context | Who is doing this, in which role, Location, device, and operational situation? |
| Outcome | What observable result must the user achieve? |
| Primary action | What is the single most important action or decision? |
| Supporting information | What must be visible before the user can act safely? |
| Secondary/advanced needs | What can be deferred, collapsed, moved, or omitted? |
| Data entry | Which values are known, inferred, inherited, selected, or genuinely free text? |
| Rules and permissions | Which requirement, domain rule, tenant boundary, and role govern the surface? |
| State model | What are the loading, empty, error, success, disabled, stale, partial, and permission states? |
| Responsive behavior | What changes at 360px, tablet, and desktop without changing the outcome? |
| Accessibility | How will keyboard, focus, labels, announcements, contrast, reflow, and motion work? |
| Trust and consequence | What affects money, time, consent, availability, privacy, history, or another person? |
| Reuse | Which tokens, components, option sources, and existing patterns should be reused or extended? |
| Simplification | What typing, click, field, confirmation, or page transition can the system remove? |

For a significant new page, multi-step workflow, or redesign, create or select
a visual target before production implementation. The target may be a reviewed
wireframe, mockup, prototype, or established ClipperDesk pattern. It must cover
the information hierarchy and primary responsive behavior; prose or a database
schema alone is not a visual target.

### Implementation review checklist

Before considering a UI task complete, confirm:

- [ ] The result matches an approved requirement and does not introduce Phase
  2 behavior.
- [ ] The page is organized around the user's outcome, not the persistence
  model.
- [ ] One primary action is clear and secondary actions do not compete with it.
- [ ] Known values use centralized structured inputs rather than free text.
- [ ] Defaults are helpful, visible when consequential, and safely reversible.
- [ ] Validation is contextual, understandable, programmatically associated,
  and preserves entered work.
- [ ] Default, focus, selected, disabled, loading, empty, error, success, stale,
  and permission states were handled where applicable.
- [ ] Dates, times, time zone, Location, currency, and units are explicit where
  they affect a decision.
- [ ] Authorization, tenant isolation, privacy, audit, and append-only history
  remain enforced beyond the visual layer.
- [ ] Existing semantic tokens and reusable components were used; any new
  pattern has a clear reuse case.
- [ ] The journey works without page-level horizontal overflow at 360px and is
  usable at tablet and desktop sizes.
- [ ] Touch targets, keyboard flow, focus behavior, labels, semantics,
  contrast, non-colour cues, zoom/reflow, and reduced motion were checked.
- [ ] Loading and interaction behavior meet the perceived and measured
  performance expectations.
- [ ] Microcopy uses ClipperDesk language and does not expose implementation
  terminology.
- [ ] Relevant automated, browser, and visual evidence is recorded before
  `project-status.md` claims the behavior verified.

### Evidence expectations

Verification should be proportional to the change, but significant journeys
must include representative real states rather than only an ideal populated
screen. Capture and review at least mobile, tablet, and desktop behavior when
layout changes materially. Exercise keyboard interaction, focus entry/return,
validation recovery, loading, empty, failure, success, and destructive or
stale-state behavior where relevant.

Automated accessibility checks are useful but do not replace keyboard,
screen-reader spot checks, responsive review, or visual comparison. A
screenshot alone is not proof that interaction, reading order, responsive
behavior, authorization, or state recovery works.

## Governance and exceptions

- Extend an existing pattern before creating a near-duplicate component.
- Add shared options to a central source; do not copy them into a page.
- Keep semantic component APIs and tokens independent of a single module.
- Do not change a global token to fix one local layout problem.
- Record a durable new pattern or product exception in this document or an
  accepted decision, and keep the relevant module document synchronized.
- A temporary exception must name the constraint, user impact, safe fallback,
  and follow-up. “Faster to implement” is not sufficient on its own.
- Review existing screens against these standards when they are materially
  changed; do not expand unrelated scope solely to make an untouched surface
  compliant.
- Do not mark a surface “premium,” accessible, responsive, or verified without
  evidence.

## Evidence

Browser screenshots are stored under `docs/evidence/product-shell/` for public
booking at 360px, the shop dashboard at 360px, 768px, and 1440px, and platform
administration plus the reusable interface-pattern reference at 1440px. The
ClipperDesk rebranding adds representative desktop and mobile evidence at the
same location. Exact automated and browser verification results are recorded in
`project-status.md` and the dated rebranding audit.


## Authenticated workspace refinement (2026-09-13)

ADR-037 establishes a compact operational layer in `resources/css/workspace.css`,
imported from `app.css`. Scope density rules to `.cd-workspace`; public booking
continues to use generous default control sizes. Preserve the existing navy,
indigo and cyan identity and readable 14–16px body text.

| Pattern | Workspace default |
| --- | --- |
| Spacing | 4px base; 12px related controls; 16–20px card padding; 20–24px major sections |
| Controls | 40px desktop, 44px mobile/coarse pointer; 8px corners |
| Row actions | Explicit small button variant, 32px desktop / 44px touch |
| Menus | 36px desktop / 44px touch options; 10px corners; restrained menu shadow |
| Surfaces | 12px panel corners; quiet border; avoid nested empty-state borders |
| Typography | 24px page heading; 15–18px section headings; 14px body; 12px supporting labels |
| Tables | 14px body; 12px column labels; approximately 11px vertical cell padding; accessible horizontal overflow |
| Shell | 240px desktop sidebar, 56px top bar, clearly separated navigation groups |
| Drawers | 576px maximum, full width on narrow screens, independent body scroll and reachable action footer |

Use `AppSelect` for authenticated single and multiple selection. It retains
native options, typed values, validation and change events; enhances them with
search for longer lists, bounded scrolling, selected indicators, keyboard
navigation, Escape and focus restoration. Keep dates/times native where that
preserves reliable regional entry. Use semantic labels and associate errors;
never remove the required reason or other audit inputs to achieve density.

Use shared `SurfaceCard`, `PageHeader`, `FormField`, `AppButton`, `AppDialog`,
`DataTable` and `StatePanel` before writing a local variant. Keep catalogues
readable at full width; open add/edit work in a drawer when it would otherwise
compete with the list. Prefer one searchable report selector over duplicate
navigation. Disclose secondary calendar filters while preserving visible
location/date and Day/Week/Team controls. Client section tabs have a tablist,
selected state, roving focus and keyboard-operable panel navigation.

The calendar details/attention panel starts collapsed so the schedule occupies
the available width. Its explicit toggle announces expanded state; selecting a
visit opens actions, and closing returns focus. On narrow screens the panel
appears above the schedule. Keep the first hour label inside the grid boundary;
do not move appointment positions to solve label clipping.

Reports display UTC source timestamps in the stated report time zone and use
human-readable column labels. Preserve the original data and export contracts;
provide full source references on demand rather than making them the primary
visual content.

Inventory is temporarily hidden from both navigation menus at the user's
request. This is menu visibility only; no domain implementation or entitlement
contract is removed or promoted to a new delivery phase.

Verified screen coverage and limitations: [workspace refinement audit](audits/2026-09-13-workspace-refinement/README.md).
