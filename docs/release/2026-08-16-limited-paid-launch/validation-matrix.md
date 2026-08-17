# PRD Section 7 and journey validation

Result labels: **PASS-LOCAL** means executed automation or browser evidence;
**PARTIAL** means an important layer was executed but the required
production-like/provider/UI layer is missing; **BLOCKED** means the release
dependency was unavailable. Every recovery path avoids direct row editing.

Common fixture: synthetic Pine & Palm Studio, India/`en-IN`, INR,
`Asia/Kolkata`; owner/manager/receptionist/stylist/accountant permissions as
defined by the starter-role matrix. Automated evidence uses SQLite `:memory:`
unless explicitly identified as dedicated MySQL.

## Booking and availability

| Scenario | Result and execution evidence | Expected/observed state | Safe recovery |
| --- | --- | --- | --- |
| Two online customers claim one slot | PASS-LOCAL: dedicated MySQL parallel-process test | Exactly one Appointment; loser receives `STAFF_UNAVAILABLE` | Refresh authoritative availability; choose another slot |
| Reception and online claim one staff/resource slot | PASS-LOCAL: same gated MySQL test | Exactly one commit and complete capacity history | Retry losing command with a fresh slot |
| Staff free, resource occupied/pooled | PASS-LOCAL: MySQL quantity 1/2 races | Success count never exceeds resource quantity | Pick another resource/time; capacity is non-overridable |
| Closing, break, leave, closure overlap | PASS-LOCAL: scheduling rule and operations suites | Commit rejected or impact record created; private cause not leaked | Authorized reschedule/cancel workflow with reason |
| Multi-service/provider/processing period | PASS-LOCAL: booking and calendar suites | Segments and provider handoff remain atomic | Correct by linked replacement, never partial row edits |
| Staff becomes unavailable with future bookings | PASS-LOCAL: operations impact tests | Impacted visits are visible and require explicit resolution | Reasoned reassignment/rebook/cancel plus notification intent |

The browser also selected a service and date, received 32 authoritative slots,
and created a held 09:00 selection before displaying the named client-details
step. Final booking submission was not used as evidence for live chargeability.

## Client and privacy

| Scenario | Result and evidence | Expected/observed state | Safe recovery |
| --- | --- | --- | --- |
| Same mobile, spelling variation | PASS-LOCAL: Client CRM tests | Candidate for review; no fuzzy automatic merge | Authorized duplicate preview/merge |
| Returning client without login | PASS-LOCAL contract tests | Purpose-bound hashed expiring link | Reissue from normal appointment workflow |
| Contact change | PASS-LOCAL | History retained; vulnerable old links revoked | Issue fresh purpose-bound link |
| Export/correction/withdrawal/deletion | PARTIAL | Export, correction, and withdrawal tested; destructive execution blocked by OPEN-10 | Set `blocked_policy`; counsel-approved future executor only |
| Merge duplicate profiles | PASS-LOCAL | Appointments, forms, payments and audit lineage preserved | Use preview and versioned reasoned merge |

## Appointment operations

| Scenario | Result and evidence | Expected/observed state | Safe recovery |
| --- | --- | --- | --- |
| Walk-in near future booking | PASS-LOCAL | Assignment revalidates staff capacity and estimate | Leave queued or assign a safe provider/time |
| Late arrival alternatives | PASS-LOCAL | Continue/shorten/rebook/cancel is explicit and reasoned | Linked replacement or reasoned terminal state |
| Service overrun | PASS-LOCAL | Impact communicated without rewriting future capacity | Resolve each impact explicitly |
| Reassignment | PASS-LOCAL | Capacity, notification intent, resource and attribution refresh | Revert through another validated change |
| Remove deposited service | PASS-LOCAL money/operations tests | Immutable original and deposit allocation/correction retained | Refund/credit remainder through ledger action |
| Unexpected closure | PASS-LOCAL | Impact records and communication intents created | Authorized batch resolution; no silent mutation |

## Payments and billing

| Scenario | Result and evidence | Expected/observed state | Safe recovery |
| --- | --- | --- | --- |
| Deposit success, booking finalisation fails | PARTIAL: local provider-event contract | Visible reconciliation task; charge not treated as booking | Safe verified-event replay or explicit refund/credit |
| Delayed confirmation/hold expiry | PASS-LOCAL: MySQL hold race | One expiry/confirmation outcome; no overbooking | Re-search or reconcile verified success |
| Deposit plus cash/card split | PASS-LOCAL | Exact tender and allocation ledger | Compensating correction/refund |
| Final total below deposit | PASS-LOCAL | Remainder remains explicit refund/credit/forfeit choice | Apply approved policy action |
| Partial refund affects reports/stock/commission | PASS-LOCAL | Append-only offsets reconcile to sources | Further compensating entry only |
| Duplicate/out-of-order verified webhook | PASS-LOCAL | Event ID/hash/idempotency converges; stale event ignored | Safe replay only after signature review |
| Renewal failure through restricted state | PARTIAL | Lifecycle tests pass; no live Paddle/test-clock event | Provider reconciliation and audited lifecycle command |
| SaaS subscription checkout amount/completion | PASS-SANDBOX via authenticated API recovery; live webhook BLOCKED | Pro payment completed at USD 100.00; plan, subscription, invoice, payment, and Visa ending 4242 reconciled locally; pending/cross-tenant cases do not activate; late webhook is idempotent | Configure a public signed notification destination and repeat in the live-like target |

Paddle Sandbox payment and authenticated API recovery are evidenced, but
end-to-end signed-webhook provisioning and live provider certification remain
BLOCKED.

## Permissions and security

| Scenario | Result and evidence | Expected/observed state | Safe recovery |
| --- | --- | --- | --- |
| Staff reads owner/other-staff protected data | PASS-LOCAL | 403/404 and scoped result sets | Correct role/assignment through audited permission change |
| Receptionist refund/excess discount API attempt | PASS-LOCAL | Server denial independent of navigation | Manager performs approved action with reason |
| Identifier changed to another tenant/attachment | PASS-LOCAL | Scoped binding/policy returns not found/denied | Use owning tenant route and authorized link |
| Former employee session | PASS-LOCAL | Membership removal deletes sessions/tokens and rotates remember token | Reinvite/regrant through normal identity workflow |
| Support without reason/after expiry | PASS-LOCAL | Entry denied; no hidden membership/impersonation | New dual-approved, scoped, expiring grant |

## Cross-journey execution

Automated feature suites exercise online, phone/reception, walk-in, service,
checkout, rebooking, subscription lifecycle, exports, and support. The local
browser exercised public availability/hold/details and authenticated dashboard,
calendar, queue, checkout, reports, inventory, configuration, and subscription
screens. It did not complete a real external payment, message delivery,
provider settlement, subscription purchase, or staffed support incident.
Therefore the end-to-end production-like journey gate is **PARTIAL**, not pass.
