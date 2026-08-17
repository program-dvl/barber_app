# Prompt 07: Public booking, secure self-service, and waitlist

Work in `/Applications/AMPPS/www/barber_app`.

Implement the mobile-first customer journey in FR-09 and FR-10 on top of the
verified availability and operational lifecycle. Clients must not need an app
or password.

Read root `AGENTS.md`, the documentation index and project status,
`docs/product-brief.md`, the scheduling and configuration modules,
`docs/quality-and-testing.md`, accepted decisions, FR-09/FR-10, and all relevant
PRD Section 7 scenarios. Reuse the public design shell from Prompt 01.

Implement:

- branded public location/service/add-on/staff/date/time/details/policy flow;
- any-qualified-staff and preferred-staff choices without leaking private
  schedules or contact data;
- correct final price, "from" price, duration, deposit, cancellation policy,
  terms, and consent presentation before commit;
- capacity hold and confirmation through the shared booking engine;
- booking reference and calendar-add actions;
- short-lived, revocable, purpose-bound secure links for view, reschedule,
  cancel, rebook, contact update, waitlist, and deposit/payment status;
- shop controls for visibility, notice, advance window, cancellation cutoff,
  new-client rules, staff preference, and price display;
- waitlist preferences, duplicate prevention, matching, controlled-batch offer,
  expiring claim link, atomic first valid claim, and retained match history.

Make the flow resilient to refresh, back navigation, expired holds, stale slots,
duplicate submit, delayed payment state, and changed policy. Protect public
endpoints with validation, rate limits, enumeration resistance, safe errors, and
privacy-preserving analytics.

Test at 360 px and common mobile browsers, keyboard access, WCAG 2.2 AA basics,
slot races, link expiry/revocation, identifier tampering, replay, waitlist races,
and conversion instrumentation. Verify all public mutations still enforce
tenant, location, policy, and capacity server-side.

Update the scheduling module, project status, decisions, and any public journey
documentation. End with mobile and accessibility evidence, security test
results, conversion events, and known provider/payment placeholders.

