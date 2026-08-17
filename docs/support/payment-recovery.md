# Payment recovery and reconciliation

Use this playbook for a payment that is pending, duplicated, delayed, or not
linked to its appointment. Do not edit a payment, deposit, sale, receipt, or
cash-close row directly.

## Triage

1. Open the tenant-scoped payment intent by its public ID and compare its
   amount/currency/policy snapshot with the provider transaction reference.
2. Confirm the provider event ID, payload hash, signature-verification result,
   provider occurrence time, and inbox processing result. A browser success
   page is not payment evidence.
3. Check the related Capacity Hold, Appointment, `PaymentTransaction`, Deposit
   and `DepositAllocation` records. Reconcile original deposit value as applied
   plus refunded plus forfeited plus credited plus remaining.
4. Resolve only through a recorded action: replay a verified pending event,
   recover the eligible Hold, issue a provider-backed refund/credit, or record
   an approved compensating correction. Add the reason and provider evidence.

## Common outcomes

| Situation | Safe action |
| --- | --- |
| Duplicate webhook | No action: the provider event and payment idempotency keys converge to the existing evidence. |
| Successful payment, booking did not finalize | Work the reconciliation task; recover the valid Hold once or refund/credit exactly once. |
| Total below deposit | Apply only the final total, then use an explicit refund or credit allocation for the remainder. |
| Wrong sale allocation | Create a compensating correction linked to the original payment; never move or rewrite it. |
| Cash variance after close | Record an elevated, reasoned post-close adjustment; never rewrite the close. |

Escalate provider signature failures, reused event IDs with different payload
hashes, currency mismatch, or a charge with no tenant evidence. Redact payment
credentials and client details from tickets. Rotate any key disclosed outside a
secret manager before retrying live provider traffic.

## Good Hours subscription billing (Paddle)

Paddle is used only for the salon's Good Hours software subscription, not for
the salon's customer payments. For a subscription incident, verify the Paddle
event's `event_id`, `event_type`, occurrence time, HMAC signature, and the
matching `billing_provider_events` record before changing access. A hosted
checkout return does not prove payment; `transaction.completed` and
`subscription.updated` are the provider evidence. If plans do not appear in
the billing page, first run `php artisan billing:sync-paddle-catalog` to review
the intended two products and four prices. Use `--apply` only after confirming
the configured Sandbox or Live account; it preserves old price evidence and
replaces changed prices with effective-dated mappings. Then confirm active
monthly and annual application price rows reference valid Paddle `pri_…` IDs.
Never substitute a client-side token, API key, Retain key, or legacy Cashier
webhook value for the Paddle notification signing secret.

## Platform recovery workflow

Use the platform failure summary first; it returns provider, event type,
signature status, timestamps, attempts, and safe state without returning the
stored payload or raw exception. For a tenant-specific investigation, enter a
dual-approved grant with `webhook_failures` scope. Confirm the banner and exact
Business before replay.

Replay only a signature-verified `pending` or `failed` event through the
reviewed Stripe, Paddle, or appointment-payment processor. Supply the ticket in
the reason and a unique operation key. The replay ledger deduplicates the
command and the provider processor deduplicates the event; a repeat returns the
first result. Unknown providers, unverified events, completed events, and
generic queue failures have no replay path. Never paste payloads, card data,
API keys, webhook secrets, or provider exceptions into an account note.
