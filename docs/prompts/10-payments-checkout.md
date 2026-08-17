# Prompt 10: Deposits, checkout, payments, and cash close

Work in `/Applications/AMPPS/www/barber_app`.

Implement the P0 money loop in FR-14 and FR-15: deposit protection, appointment
checkout, payment methods, tips, receipts, refunds/corrections, and daily cash
close.

Read root `AGENTS.md`, the documentation index and status,
`docs/domain-model.md`, `docs/architecture.md`,
`docs/modules/money-and-commerce.md`, scheduling and client modules,
`docs/quality-and-testing.md`, decisions, FR-14/FR-15, and payment scenarios in
PRD Section 7. Resolve OPEN-02 and OPEN-05 before final tax, gateway, receipt,
and local-payment behavior.

Implement:

- no/fixed/percentage/full deposit plus new-client, service, threshold, and
  prior-no-show policies;
- clearly displayed cancellation cutoff, refundability, cancellation fee, and
  no-show fee;
- idempotent payment intents and verified, deduplicated, out-of-order-safe
  webhooks;
- capacity hold while payment is pending and recovery when payment succeeds but
  booking finalization fails;
- deposit bind, apply, transfer, refund, forfeit, credit, and reconciliation
  exactly once;
- appointment-derived checkout with explicit service, add-on, and product
  lines, tax, discounts, overrides, tips, deposit applied, and balance;
- cash, card, approved local methods, bank transfer, payment link, custom
  methods, partial, split, and pay-later;
- full/partial refund, void, correction, and wrong-allocation recovery through
  compensating entries;
- tenant-branded digital and printable receipts; and
- opening cash, expected cash, actual close, variance reason, method summary,
  outstanding balance, and controlled post-close adjustment.

Centralize integer-minor-unit calculations, currency, rounding, tax ordering,
discount ordering, and source-value snapshots. Keep appointment and sale records
linked but distinct. Never delete completed financial history.

Test deposit plus split tender, total below deposit, duplicate submit, delayed/
duplicate/out-of-order webhook, payment-success/finalization-failure, partial
refund, manager waiver, excessive discount denial, cross-tenant payment access,
receipt reproduction, and cash variance. Reconcile every tested total from
underlying entries and provider evidence.

Update the commerce module, domain model, decisions, project status, metric
definitions, and support recovery guidance. End with calculation rules,
financial state tables, reconciliation proof, provider evidence, and tests.

