# Subscription & Billing package integration — 2026-10-05

The user requested integration of `subscription-billing-update.zip` into the
original `/Applications/AMPPS/www/barber_app` source checkout. The archive's
README and application script were reviewed as package material, not additional
user instructions. Its claimed isolated-copy results were independently checked
in this checkout. The bundled application script was not executed.

## Source integration

All 52 package file hashes and all 52 original-file preconditions matched before
any source was replaced. Existing unrelated work was preserved. Originals are
retained at `tmp/subscription-billing-update-import/20261005T083143Z/originals/`.
The [manifest](import-manifest.json) records archive provenance, original/package
hashes and final integrated hashes. PHP formatting, correct FR-01/FR-13 module
links, current authorization provenance and original-checkout status evidence are
the integration adjustments. No dependency or credential changes were needed.

Implemented behavior and remaining commercial/provider gates are documented in
[Subscription & Billing](../../modules/subscription-billing.md), ADR-054 and
PRD section 9. Local rates remain draft examples. Current subscribers retain
their terms until a separate reviewed migration.

## Verification in the original checkout

- [Full backend](backend-tests.txt): 570 passed, 28 existing intentional legacy
  skips, 5,548 assertions. Tests use SQLite memory databases and fake providers.
- [Billing/communications after formatting](focused-tests.txt): 128 passed,
  685 assertions.
- [Frontend helpers](frontend-tests.txt): 70 passed.
- [Production build](production-build.txt): client and SSR both pass with bundled
  Node 24; the system Node 20 does not meet the repository's required version.
- Front-site budgets pass for all 17 route entries. Scoped
  [PHP formatting](formatting-check.txt) and whitespace checks pass.
- New billing routes and `billing:verify-capacity-rates` are registered.

## Local database integration

A complete local MySQL dump was saved before changing the schema:
`tmp/subscription-billing-update-import/database-backup-20261005T084623Z.sql`.
It is private local recovery material and excluded from Git with the source
backups. The dump command completed successfully and its completion footer was
checked; an independent restore rehearsal was not performed for this import.

Only these additive migrations were applied:

- `2026_10_05_000001_preserve_billing_invoice_provider_state`
- `2026_10_05_000002_create_capacity_billing`

Both succeeded on the existing local MySQL database. The five new billing/SMS
tables and expected invoice/subscription columns were checked. Before/after
fingerprints over existing columns in businesses, business subscriptions,
billing invoices, billing payments, subscription changes and checkout attempts
match exactly. The new plan does not migrate any existing subscription.
Migration output and non-content fingerprints are retained under the ignored
local integration directory. The financial-history rollback prohibition remains.

## Browser check

Chrome using the existing sign-in on `http://127.0.0.1:8017` loads the updated
Subscription & billing page and a paid invoice drawer. The existing Starter
subscription, recorded invoice amount and current allowance remain visible.
The account's unconfigured local pricing country is labeled as being prepared;
no price or ready purchase is invented. At a 360 px viewport both the overview
and invoice drawer have a 360 px document width. The temporary viewport override
was reset after verification. This check used read-only navigation and invoice
inspection; no provider refresh, cancellation, purchase or migration was sent.

## Limits and activation

`CAPACITY_BILLING_ENABLED`, country approval and paid SMS purchases remain off.
The application `.env` was preserved. No real payment, subscription transition,
client notification or remote provider object was created by this integration.
OPEN-22 and existing legal/provider/refund/dispute/accessibility/security/
production-concurrency gates remain. Local tests, additive MySQL migrations and
this sampled browser check do not certify those release gates.
