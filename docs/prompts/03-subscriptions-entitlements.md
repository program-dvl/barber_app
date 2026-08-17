# Prompt 03: Subscription lifecycle and entitlements

Work in `/Applications/AMPPS/www/barber_app`.

Implement FR-01 as a self-serve SaaS subscription and entitlement foundation.
This is business subscription billing, not appointment deposits or checkout.

Read root `AGENTS.md`, `docs/README.md`, the platform module,
`docs/architecture.md`, `docs/domain-model.md`, `docs/quality-and-testing.md`,
`docs/decisions.md`, and all of FR-01 plus PRD Section 9. Inspect the verified
tenancy result from Prompt 02 and audit existing Stripe/Cashier, Lemon Squeezy,
Paddle, invoice, product, price, order, and webhook code.

OPEN-04 must be accepted before selecting the launch provider. Keep an
application-owned billing contract so provider payloads do not become the
domain model. Remove or disable competing production paths only if the accepted
foundation audit authorizes it.

Implement:

- verified owner registration and exactly-once tenant/trial creation;
- clearly dated trial, monthly/annual selection, saved payment method,
  automatic renewal, invoices, and payment history;
- upgrade, downgrade, cancellation at period end, support cancellation,
  reactivation, and coupons;
- failed-renewal retries, notices, grace period, progressive restrictions, and
  safe recovery;
- data export availability before closure and a documented termination state;
- reusable feature and numeric entitlements, including location/staff limits,
  messaging allowance, deposits, inventory, reporting, branding, and support;
- server-side entitlement checks for UI actions, APIs, jobs, and imports; and
- effective-dated, audited plan and entitlement changes.

Design downgrade behavior when usage exceeds a new limit. Do not hard-code
Starter/Team/Business conditionals into domain operations. Ensure duplicate and
out-of-order webhooks are signature-verified, deduplicated, and replay-safe.

Test the full trial-to-paid lifecycle, monthly/annual behavior, retry and grace
states, cancellations, plan changes, over-limit downgrades, duplicate events,
cross-tenant billing access, invoices, restrictions, and export availability.
Use provider sandbox/contract evidence where available.

Update the platform module, decisions, architecture, and project status. End
with lifecycle state diagrams or tables, entitlement catalog, provider failure
recovery, reconciliation evidence, and exact test results.

