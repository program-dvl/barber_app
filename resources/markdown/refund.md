<span id="status-and-scope"></span>
## 1. Status, scope and payment roles

This proposed Refund, Return and Cancellation Policy is a review draft and is
not yet effective. It is structured to disclose the information expected for
an online service and India payment-provider review, but it cannot support live
collection until the legal operator, customer-service contacts, provider
accounts, refund execution, timelines and owner approvals are operationally
verified.

ClipperDesk has two separate payment contexts. They must not be confused:

| Payment | Seller or service provider | Current payment role | Policy that controls |
| --- | --- | --- | --- |
| ClipperDesk software subscription | Paddle is the selected merchant of record; ClipperDesk supplies the software | Paddle processes the subscription purchase, renewal, cancellation and approved refund | Paddle buyer/refund terms, mandatory law and this approved ClipperDesk policy where it gives an additional right |
| Salon appointment, deposit or in-person sale | The salon or barbershop shown on the booking/receipt provides the service or product | Stripe is the implemented online appointment-payment adapter; the Business Customer also records supported local tenders | The Business Customer's displayed booking/cancellation policy, mandatory law and the applicable payment-provider rules |

ClipperDesk does not ship physical goods. Retail products recorded in ClipperDesk
are presently sold and fulfilled by the Business Customer in person unless
that Business Customer separately publishes an approved delivery policy.

Nothing in this draft limits a refund, cancellation, withdrawal, chargeback or
consumer right that applicable law does not permit the relevant seller or
service provider to limit.

<span id="subscription-refunds"></span>
## 2. ClipperDesk subscription cancellations and refunds

### 2.1 Trial and first charge

A verified owner registration currently begins a 14-day trial and does not
charge a payment method. A Business Customer reviews the plan, billing interval,
currency, amount and applicable provider information before entering payment
details. To avoid a first paid charge, the Business Customer must not complete
paid checkout or must cancel any converting trial before the deadline shown in
the checkout and billing account.

### 2.2 Renewal and cancellation

Paid monthly or annual subscriptions renew automatically until cancelled. An
authorized owner can use the ClipperDesk billing page or the manage-subscription
link in Paddle's transaction email to schedule cancellation. Unless mandatory
law or the checkout says otherwise, cancellation takes effect at the end of the
current paid billing period and prevents the next renewal. Access continues
until that date, subject to payment, security and acceptable-use restrictions.

Cancelling renewal does not automatically refund the current billing period.
An approved refund may end access to the refunded product or period.

### 2.3 Subscription refund requests

Paddle is the merchant of record for the proposed ClipperDesk subscription
purchase. A Business Customer may request a refund through the **View receipt**
or **Manage subscription** link in Paddle's transaction email, the Paddle buyer
support flow, or an approved ClipperDesk billing-support path once that path is
operational.

Eligibility is determined by Paddle's terms, the ClipperDesk commitment shown at
purchase and mandatory law. The current Paddle policy may allow statutory or
discretionary refunds and does not remove non-waivable consumer rights. Good
Hours must not promise a refund that Paddle, as seller, has not approved and
processed.

<span id="appointment-refunds"></span>
## 3. Salon appointments, deposits and sale cancellations

The salon or barbershop shown on the booking page is responsible for the
appointment, professional service, in-person product, cancellation decision and
client support. Before payment, its ClipperDesk booking journey is designed to
show:

- the salon, selected service and location;
- the amount and ISO currency;
- whether a deposit is required and how it is calculated;
- the free-cancellation cutoff, deposit refundability, cancellation fee and
  no-show fee configured by that Business Customer; and
- links to the Business Customer's own terms and privacy notice.

The exact policy shown and accepted at booking is retained with the appointment
and controls the normal refund decision. A later policy change must not
retroactively reduce the rights shown for that transaction.

Where permitted by the displayed policy, a deposit may be applied to the final
sale, transferred to an approved replacement appointment, refunded, forfeited
against an applicable cancellation/no-show rule, or converted to an approved
credit. Its total value must not be allocated more than once.

<span id="eligibility"></span>
## 4. Proposed appointment-payment eligibility rules

Subject to the Business Customer's displayed policy and mandatory law, a full
or partial refund should be approved when:

- the Business Customer cancels and cannot provide a reasonable replacement;
- a cancellation is made before the displayed cutoff and the accepted deposit
  policy says the deposit is refundable;
- the final amount is lower than the deposit and the excess is not validly
  transferred or accepted as credit;
- a payment was duplicated, the amount or currency was incorrect, or the
  transaction was unauthorized after appropriate verification;
- payment succeeded but the booking or sale could not be completed and the
  service cannot be recovered; or
- applicable law or the payment provider requires the refund.

A refund is not normally automatic for:

- a late cancellation or no-show where the accepted policy clearly permits a
  deposit forfeiture or fee;
- a completed service or consumed in-person product merely because the client
  changed their mind;
- the portion of a service, package or product already properly supplied; or
- a request that cannot be matched to a transaction or safely verified.

These exclusions never override a mandatory remedy for a service or product
that was not supplied, was materially misdescribed, was defective, or otherwise
qualifies under applicable law. A manager waiver or exception must be reasoned
and recorded; it must not erase the original transaction.

<span id="request-process"></span>
## 5. How to request a cancellation or refund

### 5.1 Salon Client request

Use the salon's contact details in the booking confirmation or receipt, or the
purpose-bound secure appointment link supplied for that booking. Include only
the booking reference, payment date, amount, reason and enough information to
verify the request. Do not send a full card number, card security code, password
or one-time banking code.

The Business Customer should confirm whether the appointment is cancelled,
whether a refund is approved or declined, the approved amount, the policy basis
and the provider reference when available. Cancellation of an appointment is
not by itself evidence that money reached the client's account.

### 5.2 ClipperDesk subscription request

Use Paddle's receipt/manage-subscription link or buyer support process. An
authorized Business Customer owner may also review subscription state and
provider links in the authenticated ClipperDesk billing page. A public Good
Hours billing-support email, telephone number and response owner must be added
before this draft can become effective.

### 5.3 Information and decision timeline

The final approved policy should require the responsible seller or service
provider to acknowledge a complete request within 2 business days and approve,
decline or request necessary information within 7 business days. These are
proposed service targets only; they are not operational promises until named
Support/Operations owners approve staffing, escalation and tracking.

<span id="processing-times"></span>
## 6. Processing and bank timelines

Once an appointment refund is approved, the responsible merchant should submit
it to the original payment provider within 7 business days. Refunds should be
returned to the original payment method wherever the provider supports it. A
different destination must not be used merely for convenience; if the original
method cannot receive the refund, the merchant and provider must verify a safe,
lawful alternative.

After Stripe accepts a card refund, the customer's bank or card issuer commonly
shows the credit in approximately **5 to 10 business days**. A very recent
refund may appear as a reversal, meaning the original charge disappears instead
of a separate credit appearing. Provider or bank failure can take longer to
identify. Where available, the merchant should supply the refund reference so
the customer can ask their bank to trace it.

For a ClipperDesk subscription, Paddle's current buyer refund policy states that
eligible refunds are processed using the same payment method where possible and
within **14 days after approval**. The Paddle transaction record and current
buyer terms control the provider-side timing.

These timeframes begin after approval and provider submission; they do not mean
the credit is guaranteed to appear on a specific day. Weekends, bank holidays,
payment method rules, issuer processing, fraud review, insufficient provider
balance or a failed refund may affect delivery. The responsible merchant must
track failed refunds and arrange an appropriate alternative where required.

<span id="returns-and-delivery"></span>
## 7. Returns, exchanges, shipping and service delivery

ClipperDesk is a web-based software service. There is no physical ClipperDesk
product to return and no ClipperDesk shipping or delivery charge. Subscription
access is delivered electronically to the registered Business Customer account
after the provider confirms the applicable trial or paid state.

Salon services are delivered at the location, date and time shown in the
booking. In-person retail return, exchange, hygiene, opened-product and defect
rules belong to the Business Customer and must be disclosed by that Business
Customer before sale. ClipperDesk does not create a mail-return right or address
for a salon's physical products.

<span id="disputes"></span>
## 8. Disputes, chargebacks and payment safeguards

Customers should first contact the responsible seller or service provider so it
can investigate and honor the displayed policy. This does not waive a right to
contact the payment provider, bank, card issuer, consumer authority or court.

Submitting both a refund request and a payment dispute can result in duplicate
credits or delay. Tell the responsible merchant if a bank dispute is already
open. The merchant should not retaliate for a good-faith complaint, submit
misleading dispute evidence, or issue a second refund without checking the
provider state.

ClipperDesk is designed to use verified webhooks, provider identifiers and
idempotency controls so the same event does not create multiple payment or
refund records. Financial corrections remain linked to the original payment.
However, the current application records refund allocations and internal
refund transactions without a certified end-to-end Stripe refund executor.
Production appointment collection must remain disabled until Stripe test-mode
refund, failure, webhook, reconciliation and settlement evidence passes.

## 9. Approval gate and changes

Before this policy becomes effective, named Finance, Operations, Legal,
Privacy and Product owners must:

- identify the ClipperDesk operator, registered address and direct customer
  service contacts;
- confirm whether the live Stripe account, connected Business Customer or
  another entity is the merchant for each appointment-payment flow;
- implement and certify provider-backed full and partial refunds, failure
  handling and reconciliation;
- approve request acknowledgment, decision and submission timelines;
- confirm Paddle seller/support language for ClipperDesk subscriptions;
- verify that checkout and receipts display the policy before payment; and
- publish an effective date and retain the version accepted for each relevant
  transaction.

Material changes must not retroactively reduce rights for a completed purchase.
The policy version shown when a booking, subscription or payment is confirmed
should remain reproducible with the related transaction evidence.
