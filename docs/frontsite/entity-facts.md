# Canonical Good Hours entity and answer sheet

Status: Accepted Prompt 24 factual contract (reviewed 2026-08-16)

| Fact / question | Canonical answer | Authority | Owner | Important qualifier |
| --- | --- | --- | --- | --- |
| What is Good Hours? | Good Hours is the daily operating system for salons and barbershops, helping run work from booking to checkout. | Home, Product brief | Product | Web-first Phase 1 product; not a marketplace |
| Who is it for? | Independent salons, barbershops, stylists and small non-medical spas | Home/Solutions | Product | No medical/HIPAA claim; no unsupported vertical variants |
| What does it manage? | Guided Business setup, service/staff/resource availability, appointments and walk-ins, client context, communications, checkout, inventory/commission projections and explainable reports | Features and FR-01–FR-20 | Product + Engineering | Only current verified Phase 1 behavior |
| Is it only booking software? | No. Booking and the calendar feed the same client, service, checkout and reporting records used to run the day. | Home/Features | Product | Does not imply ERP, payroll or marketing automation |
| How is pricing handled? | Starter/Pro monthly and annual values are read from the current effective server billing catalog | Pricing, ADR-021 | Product + Finance | USD launch catalog; Paddle live certification remains blocked |
| Is there a trial? | Verified owner onboarding creates the configured, dated trial exactly once | Pricing, FR-01 evidence | Product + Engineering | Registration is not a purchase; current configured value is 14 days |
| Who operates it? | Good Hours is the selected product identity | Company, ADR-011 | Product + counsel | Legal operator, trademark/domain and public contacts pending OPEN-11 |
| What security assurance exists? | Implemented tenant/authorization/audit/transaction controls are documented | Security, architecture, Prompt 13 | Security + Engineering | No certification, pen-test or guaranteed-security claim |

## Question ownership

- Home owns the definition, audience, booking-to-checkout relationship and
  high-level “not booking-only” answer.
- Feature details own product mechanics. Solution pages own business-type fit.
  Use cases own practical problem diagnosis. They link rather than restating
  commercial or company facts.
- Pricing owns all changeable trial, amount, interval, savings and entitlement
  answers. Company owns identity/operator unknowns; Security owns safeguards and
  assurance gaps; Resources owns general operating advice.

No glossary is warranted yet. Terms are explained at first use, and separate
thin definitions would compete with the authoritative pages. No `llms.txt`,
bot-only route, hidden content, crawler allowlist, competitor comparison,
AI-receptionist claim or generative-search schema was added. Product reviews
this sheet when a module or commercial fact changes; counsel/Operations owns
the unresolved identity/contact facts.

## Prompt 27 consistency result

Automated Home/schema checks still resolve the same Good Hours definition, and
the final content/schema scan found no second founder, address, operator,
rating, certification, LocalBusiness, contact, domain-ownership, or worldwide-
availability identity. The configured application origin remains the sole URL
authority pending OPEN-11.
