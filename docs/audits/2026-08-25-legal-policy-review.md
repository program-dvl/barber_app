# Legal policy and payment-provider website review

Review date: 2026-08-25

Scope: Good Hours Terms and Conditions, Privacy Policy, and Refund, Return and
Cancellation Policy; public-page structure and provider-facing disclosures.
This is an engineering/content review, not legal advice or provider approval.

## Outcome

The prior Terms and Privacy routes contained only short launch-blocker notices,
and no refund-policy route existed. They now use a shared, readable and
printable legal-document layout with version, review state, effective-date
state, accountable owner, in-page navigation, canonical metadata and stable
routes. The new `/refund-policy` distinguishes Paddle merchant-of-record SaaS
subscriptions from Stripe appointment payments and salon-owned booking rules.

The drafts address the public policy fields described by Stripe: what is sold,
currency/provider context, cancellation and refund eligibility, request path,
processing timeline, original payment method, service delivery/no-shipping,
privacy, terms and pre-payment links. They remain `noindex` and explicitly
non-effective because required legal and operational facts are absent.

## Primary sources reviewed

- [Stripe website checklist](https://docs.stripe.com/get-started/checklist/website)
  — product/service description, currency, direct customer service, fulfilment,
  refund/cancellation, privacy, business identity, trial/promotion and payment
  security disclosures.
- [Stripe India URL requirements](https://support.stripe.com/questions/url-requirements-for-stripe-india-users)
  — public Terms, Privacy and Return/Refund/Cancellation URLs, eligibility,
  initiation, processing timeline and delivery information.
- [Stripe refund documentation](https://docs.stripe.com/refunds) — original-
  method handling, typical card-credit visibility of 5–10 business days,
  reversals, references and failed refunds.
- [Stripe Checkout policy/contact configuration](https://docs.stripe.com/get-started/account/branding)
  — support details and links to Terms, Privacy and full refund policies in
  provider-hosted checkout.
- [RBI payment aggregator/payment gateway discussion and requirements](https://www.rbi.org.in/Scripts/PublicationReportDetails.aspx?ID=943&UrlPage=)
  — merchant website terms, return/refund timelines, complaint handling,
  privacy and responsibilities.
- [Digital Personal Data Protection Act, 2023](https://www.meity.gov.in/static/uploads/2024/02/Digital-Personal-Data-Protection-Act-2023.pdf)
  and [Digital Personal Data Protection Rules, 2025](https://www.meity.gov.in/documents/act-and-policies/digital-personal-data-protection-rules-2025-gDOxUjMtQWa?pageTitle=Digital-Personal-Data-Protection-Rules-2025)
  — India privacy notice/consent/rights framework and phased implementation.
- [Paddle Buyer Terms](https://www.paddle.com/legal/buyer-terms) and
  [Paddle Refund Policy](https://www.paddle.com/legal/refund-policy) — Paddle's
  merchant-of-record subscription cancellation/refund path and provider-side
  timing.

## Verified implementation

- Stable routes: `/terms-of-service`, `/privacy-policy`, `/refund-policy`.
- All three return `noindex, follow, noarchive`; none enters the sitemap or
  structured-data graph.
- Terms/Privacy remain the Jetstream registration route names.
- The footer, Pricing and subscription checkout link the refund policy.
- Focused legal/SEO tests pass with route, metadata, content and draft-safety
  assertions.
- Production client and SSR builds pass using the repository-required Node
  runtime.
- Browser verification at 360px found one `h1`, one `main`, no page-level
  horizontal overflow, working in-page anchors and no console warnings. Tables
  scroll within the article rather than widening the page.

## Unresolved approval and operational gates

1. Identify and verify the Good Hours contracting legal entity, registration,
   registered/business address and country of domicile.
2. Operate direct customer-service methods and named legal, privacy/grievance,
   security and billing contacts; Stripe's checklist is not satisfied by a
   policy page alone.
3. Approve governing law, disputes, liability, indemnity, warranty, child-data,
   transfer, subprocessor and record-specific retention terms.
4. Confirm the live Stripe merchant/connected-account model for salon payments.
5. Implement and certify provider-backed full/partial Stripe refunds, failures,
   webhook evidence, reconciliation and settlement. The current commerce
   service records internal refund transactions/allocations but does not call a
   certified Stripe refund executor.
6. Approve and staff the proposed request acknowledgment, decision and refund-
   submission timelines; then remove draft qualifiers only after verification.
7. Store and reproduce the legal version accepted at registration, subscription
   checkout and each applicable payment confirmation.

These gaps are tracked as OPEN-10, OPEN-11 and OPEN-13 and keep public paid
launch at **NO-GO**.
