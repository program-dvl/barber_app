# Trust, company and legal publication matrix

Status: Accepted Prompt 21 implementation record; legal drafts refreshed 2026-08-25

| Surface | Source / accountable owner | Review state | Indexation disposition |
| --- | --- | --- | --- |
| Company | Product brief, ADR-011, Product + Engineering | Product purpose approved; operator/domain facts pending OPEN-11 | Indexable with explicit identity qualifier |
| Security | Architecture, module evidence, Prompt 13; Security + Engineering | Engineering-reviewed controls; provider/ops/independent assurance open | Indexable; no certification schema |
| Terms | Counsel + Product | Substantive review draft `0.2-review`; operator, governing law, liability, notices and named approver absent | Stable route, `noindex` until approval |
| Privacy | Privacy/DPO + Product; OPEN-10 | Substantive review draft `0.2-review`; controller identity, transfers, retention and request/grievance path unapproved | Stable route, `noindex` until approval |
| Refund/cancellation | Finance + Operations + Counsel + Product; ADR-020/021, OPEN-13 | Stripe/Paddle-aware review draft `0.1-review`; merchant role, direct contacts, service targets and provider-backed Stripe refund execution unapproved | Stable route, `noindex` until approval and live-provider certification |
| Contact/support | Operations + Privacy | No owned public receiving workflow, SLA or retention | Deferred; no form/route/email |
| Security reporting | Security + Operations | No approved mailbox/intake/response workflow | Deferred; gap stated on Security |
| Accessibility statement/feedback | Accessibility + Support | Independent audit and owned intake absent | Deferred; no conformance claim |
| Cookies/data protection | Counsel/DPO | No distinct approved content or analytics decision | Deferred; OPEN-10/12 |
| Status | Operations/SRE | No maintained external service or uptime history | Deferred; no fake dashboard |

## Approval checklist

Before public launch, named counsel/DPO/Product/Operations owners must approve
legal operator and address, governing/commerce terms, privacy roles and
purposes, recipients/transfers, cookie/analytics position, retention and
deletion executor, rights/contact path, support/security/accessibility intake,
refund/cancellation eligibility and service timelines, live Stripe merchant and
refund-executor responsibility, review/effective dates, version/acceptance
storage and canonical indexation. OPEN-10, OPEN-11 and OPEN-13 remain blockers;
no route in this change resolves them.
