# PRD Section 17 chargeability decision

Representative shop: synthetic Pine & Palm Studio in isolated local MySQL,
India/`en-IN`/INR, one location, three working staff, resource pool, service,
eight front-desk appointments, two walk-ins, and one block. Browser server ran
with production error settings. No real provider or customer data was used.

An item is complete only when executed evidence covers the required boundary.

| Section 17 statement | Result | Exact evidence and gap |
| --- | --- | --- |
| Register, verify and subscribe without assistance | BLOCKED | Registration/verification/trial automation and the in-app Paddle checkout pass. The reported USD 100.00 Pro purchase is now reconciled through Paddle's authenticated API to an active local plan, invoice, payment, and saved-method evidence. Localhost still received no signed event and no independent new-shop operator completed the full path, so the launch checkbox remains blocked. |
| Configure business, location, services, employees, resources, availability | PASS-LOCAL | Registration/configuration tests and seeded published business with browser settings/availability |
| Import clients and resolve errors/duplicates | PARTIAL | CSV preview/commit/error and merge tests pass; no representative shop migration session |
| Publish branded mobile booking link | PASS-LOCAL | Pine & Palm public page at 1280×720 and 390×844, no sampled overflow or unnamed visible control |
| Accept online, phone, reception and walk-in demand | PARTIAL | Automated sources and queue pass; browser availability/hold plus staff queue pass; no complete production day |
| Prevent concurrent staff/room/chair/equipment conflicts | PASS-LOCAL | 12-case dedicated MySQL suite; exact-one and pooled-capacity outcomes |
| Send confirmations/reminders/change notifications reliably | BLOCKED | Local contracts pass; no Resend/Twilio sender/template/deliverability/callback burst |
| Collect/transfer/refund/forfeit deposit by policy | BLOCKED | Ledger/provider-event contracts pass; no live Stripe transaction, refund, transfer/settlement |
| Check in, serve, complete and rebook | PARTIAL | Lifecycle/rebook automation and calendar browser pass; no uninterrupted operator/browser execution |
| Split payment, tips, receipt, safe correction | PARTIAL | Money tests reconcile; checkout screen/browser pass after shell fix; no full UI/provider transaction |
| Update client history, inventory, commission | PASS-LOCAL | Exact-once inventory/commission/reporting assertions pass |
| Close day and reconcile by payment method | PASS-LOCAL | Cash close and report/source reconciliation automation pass |
| Review/export operational/financial reports | PASS-LOCAL | Browser reports and scoped CSV/print/hash tests pass |
| Export business data and manage/cancel subscription | BLOCKED | Export/lifecycle contracts pass; no live provider portal/cancellation/export inspection with a shop |
| Receive support without invisible/unaudited access | PARTIAL | Dual-approved scoped visible grant automation passes; no staffed incident drill/on-call escalation |

## Decision

**NO-GO.** Five statements are blocked and five are only partial. The PRD
decision rule is not met because live provider repair/reconciliation,
production operations, legal review, and named support ownership have not been
proven self-serve. General availability is also no-go.

The next candidate may be reconsidered only after every Critical/High blocker
in the release README has dated evidence and named sign-off, Section 7 is rerun
against the target topology, and all Section 17 rows are PASS without routine
database work or product-team assistance.
