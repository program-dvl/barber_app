# Glossary

Use these terms consistently in product copy, code, schemas, tests, support
material, and analytics. Product-facing labels may later use a chosen brand
voice, but domain meanings should remain stable.

| Term | Canonical meaning |
| --- | --- |
| Business / Tenant | The isolated SaaS customer account that owns operational data and a subscription |
| Location | A physical operating site with its own time zone, hours, resources, and service availability |
| User | An authenticated identity |
| Membership | A user's authorization relationship to a tenant |
| Staff Profile | A schedulable worker record, optionally linked to a user |
| Client | A person receiving services; authentication is optional |
| Normalized Client Identity | Tenant-scoped searchable name, email, and mobile values used for conservative matching while display values are retained |
| Duplicate Candidate | Evidence that two Client profiles may represent one person; it requires human review and never merges automatically |
| Client Merge | A permissioned, previewed command that selects one surviving profile, preserves relationships, and retains the losing identity as history |
| Sensitive Client Note | Encrypted allergy, sensitivity, formula, treatment, patch-test, warning, or related context protected by a narrower permission |
| Form Template Version | Immutable published wording and field definition used by future Form Requests |
| Form Request | A Client- and optionally Appointment-bound request for one exact Form Template Version |
| Form Submission | Immutable wording, answer, signature, identity, Appointment, and time evidence for a completed Form Request |
| Consent Event | Append-only grant, decline, or withdrawal evidence with type, wording/version, source, and time |
| Privacy Request | A tracked export, correction, withdrawal, or deletion/anonymisation case with deadline, review, and result evidence |
| Policy-blocked Privacy Request | A reviewed destructive request held without data mutation until jurisdiction and retention policy authorize an executor |
| Private Attachment Link | A short-lived, revocable bearer secret whose digest authorizes one private Client file; the raw secret is not stored |
| Service | A sellable treatment or appointment type |
| Add-on | An optional explicit appointment and sale line that can change price, duration, skill, tax, or resource use |
| Staff-Service Assignment | A staff-specific qualification and possible override of service price, duration, commission, or visibility |
| Resource | A finite physical capacity item such as a chair, room, station, basin, or equipment |
| Normal Hours | A Location or Resource's recurring weekly open windows; split windows are separate rows |
| Schedule Exception | A dated holiday, closure, special opening, leave, sick leave, temporary change, or personal block that overrides normal availability |
| Schedule | A staff or resource's recurring working availability |
| Time Off / Block | An exception that removes capacity from normal availability |
| Appointment | The customer-facing visit containing one or more service lines |
| Service Line | A versioned service or add-on selected for a specific appointment |
| Segment | Active, processing, or cleanup time within a service line with explicit capacity use |
| Capacity Hold | A temporary, expiring claim used during booking or payment |
| Public Booking Flow | A passwordless, expiring booking session identified by an opaque browser-held secret whose digest is stored server-side |
| Secure Appointment Link | An expiring, purpose-bound opaque token for one Appointment action; a booking reference is never a substitute |
| Booking Source | Online, phone, reception, walk-in, recurring, consultation, personal block, or staff break |
| Walk-in Queue | Clients physically waiting for near-term service |
| Walk-in Estimate | Persisted evidence of queue demand, service duration, future appointments, staff capacity, expected service time, and estimated wait at one calculation instant |
| Waitlist | Client preferences for a future opening |
| Waitlist Offer Batch | A controlled group offered one released slot; deterministic locking allows only the first valid claim to book it |
| Operational Exception | A reasoned late-arrival, overrun, staff-unavailable, or closure event with affected appointments and recovery evidence |
| Linked Replacement | A new revalidated Appointment connected to the terminal historical Appointment it replaces |
| Sale | The final commercial transaction, linked to but separate from an appointment |
| Payment | Money tendered by a defined method or provider |
| Deposit | Payment collected before final checkout and later allocated, transferred, refunded, or forfeited |
| Deposit Allocation | The record that applies deposit value exactly once |
| Refund | A controlled return of money linked to the original payment |
| Cash Close | Reconciliation of expected and actual cash for an operating period |
| Commission | A payroll input calculated from versioned rules and sale lines |
| Entitlement | A server-enforced feature or usage limit independent of plan name |
| Support Access | Approved, reasoned, temporary, and audited platform access to a tenant |
| Audit Event | Immutable evidence of a significant action and its actor, target, reason, and change summary |
| Transactional Message | A service communication required for an appointment or payment, separate from marketing consent |
| Idempotency Key | A stable request identity that prevents a retry from applying the same command twice |
| Readiness Blocker | A specific missing or invalid configuration that prevents publishing valid availability |
| Optional Improvement | A recommended setup enhancement that does not prevent valid availability from being published |
| Effective Service | The resolved staff/location-specific price, duration segments, tax, deposit, and visibility decision at one instant |
| Configuration Snapshot | Immutable evidence of effective commercial and duration values supplied to an appointment line |
| Impact Preview | Expiring evidence listing future appointment public IDs affected by a proposed configuration change |
| Import Row Key | Tenant-scoped source identity used with a fingerprint to make CSV replay deterministic |
| Reconciliation Exception | A mismatch between internal financial state and provider or settlement evidence |
| P0 | Required for a chargeable launch |
| P1 | Phase 1 scope that must not weaken or delay the P0 core loop |
| Phase 2 | Explicitly deferred scope requiring a promotion decision before implementation |
