# Fresh Stripe checkout remains unconfirmed — JSON identity root cause

Date: 2026-10-28. Requirements: FR-01, FR-19/20; ADR-072 follow-up.

## What was missing

The owner reports the same failure from a fresh account: 2 locations, 6 staff and
USD 90/month. Overview and Invoices reflect the successful payment, while Plan
still says the subscription is not confirmed. Changing the heading and repairing
only identically hashed stale records did not fix the underlying mismatch.

`CapacityPricingCatalog::fingerprint` hashes encoded pricing terms. Verification
saves that hash before the database stores the terms. MySQL normalizes JSON object
keys. The provider projector subsequently requested a retained quote from those
decoded terms and recalculated a different hash for the same verified rate card.
It activated the subscription and confirmed the attempt, but could not match the
original request's hash. The strict stale-record repair rejected the same mismatch.
SQLite fixtures preserved object insertion order and hid this defect.

## Reproduction and evidence

A new regression starts with a trial, reviews 2 locations/6 staff, confirms through
the actual Stripe SDK adapter using isolated transport, and processes the paid
return. Rate-card JSON is stored in database-normalized key order; quantities,
prices and all other values are unchanged. Both monthly and annual cases failed
before the fix: expected applied, received submitted. Both pass afterward.

A separate read-only expression against local MySQL returned:

```sql
SELECT JSON_OBJECT('long_key', 1, 'a', 2) AS normalized;
```

```json
{"a":2,"long_key":1}
```

No customer table was read or changed by this database probe. The flow tests use
isolated SQLite fixtures with the observed normalization, not a MySQL schema reset
or an actual Stripe payment. The deployed business/database was not inspected.

## Small architectural correction

- A verified rate card already has an immutable saved identity. Quotes reuse it;
  drafts without a verified card retain existing configuration hashing. Do not
  create new canonical hashes or alter cards, prices or commercial terms.
- Provider request/schedule matching and current-rate classification use that
  same immutable rate-card ID, rather than an encoding-dependent copied hash.
  Retained no-op selections remain no-ops; normal expansion stays immediate;
  different rates/cadences and reductions retain renewal behavior.
- Previously paid affected requests may carry the exact old hash of the same
  database-decoded card. Completion accepts the saved or that exact legacy form,
  while still requiring confirmed payment with a recorded confirmation time and identical tenant,
  subscription, price, card, quantities, cadence, currency and total. Arbitrary
  mismatches and unpaid attempts remain blocked. Preserve paid snapshots/invoices.

No new frontend component, wording workaround, endpoint, pricing layer or billing
system was added. The existing affirmative success flow can now complete normally.

## Verification

Focused Billing: **272 passed / 1,893 assertions**. Fresh monthly/annual completion,
previously affected paid accounts, retained-rate expansion/no-op validation and
scheduled renewal matching are covered. Formatting/whitespace pass.

The first full suite run exposed an unrelated existing assertion comparing an
HTML email against an unescaped randomly generated business name containing an
apostrophe. The assertion now checks the HTML-escaped name; no communications
product behavior changed. Its 15 tests / 165 assertions pass independently.
Final full suite: **918 passed / 9,892 assertions**, 30 existing skips. Raw
results are recorded in the accompanying backend result file.

No frontend files changed, so the preceding 128-test frontend run and client/SSR
build remain the latest frontend evidence; they were not repeated for this backend
correction. Browser access remains disabled and Forge access was previously
declined. No new rendered desktop/mobile run or deployed payment success is claimed.

## Rollout

Deploy the backend update normally. No migration, new environment value, rebuilt
frontend requirement or Stripe Dashboard/provisioning action is introduced here.
Do not buy another subscription, reset records or reprice existing customers.
Eligible already-paid requests recover on the next authorized billing visit.
Verify the deployed reported account and fresh monthly/annual sandbox purchases,
without clicking status, and confirm Plan/Overview/Invoices agree. Those deployed
checks remain outstanding; no real charge or production data change was performed.
