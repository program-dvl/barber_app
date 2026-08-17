# Feature cluster decisions

Status: Implemented and locally verified in Prompt 17 (2026-08-16)

| Candidate | Primary intent | Evidence depth | Overlap/cannibalisation | Route/disposition |
| --- | --- | --- | --- | --- |
| Online booking and secure self-service | Evaluate public client booking | Deep: FR-03–FR-10 | Owns client-facing booking; links to operations/CRM | `/features/online-booking` |
| Calendar, availability, resource scheduling, staff scheduling and walk-ins | Evaluate daily schedule operation | Deep: FR-03–FR-08 | One connected operational intent; separate pages would repeat capacity rules | `/features/calendar-and-walk-ins` |
| Client CRM, forms, consent and communications history | Evaluate durable client context | Deep: FR-11–FR-13 | One record/privacy intent | `/features/client-management` |
| Deposits, checkout, payments, inventory, commissions and reporting | Evaluate closing and explaining the money loop | Deep: FR-14–FR-18 | One completed-Sale evidence chain; separate inventory/commission pages would be thin | `/features/checkout-and-reporting` |
| Reminders/communications | Delivery reliability | Implemented but provider-qualified | Consolidated into booking/client context; no standalone acquisition intent yet | Consolidated/deferred |
| Staff permissions | Access control | Deep but supporting rather than acquisition-led | Woven into calendar/client/money limits | Consolidated |

Every published page has one definition, four workflow steps, three concrete
proof statements, two visible limitations, requirement evidence, two adjacent
links, a canonical and a trial CTA. No database/module name is exposed as the
customer-facing architecture.
