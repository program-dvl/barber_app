<span id="status-and-scope"></span>
## 1. Status and scope

This proposed Privacy Policy explains how the future legal operator of Good
Hours would handle personal data through the public website, ClipperDesk
business accounts, salon booking and self-service pages, communications,
subscriptions, appointment payments and support operations. It is a review
draft, is not yet effective, and must not be presented as a final privacy
notice until the legal operator, contact channels, data roles, transfers and
retention schedule are approved.

ClipperDesk is designed for appointment-led businesses such as salons and
barbershops. This Policy may apply to:

- visitors to the ClipperDesk public website;
- owners, staff and other Authorized Users of a Business Customer account;
- Salon Clients who book, complete a form, receive a message or make a payment
  through a ClipperDesk page; and
- people who communicate with the approved support, privacy or security
  channels once those channels are operational.

A salon or barbershop may have its own privacy notice. Salon Clients should
read the business-specific notice linked in the booking journey as well as this
Platform notice.

<span id="our-roles"></span>
## 2. Who decides how data is used

The legal ClipperDesk operator and public contact details are pending approval.
The proposed data roles are:

- for business registration, account security, Platform subscription billing,
  direct website activity and ClipperDesk' legal obligations, ClipperDesk expects
  to determine the purposes and means of processing and act as the applicable
  controller or data fiduciary;
- for Salon Client records, appointments, notes, forms, attachments, business
  communications and in-salon transactions configured by a Business Customer,
  the Business Customer generally determines the purpose and ClipperDesk expects
  to act as its service provider or data processor; and
- payment and communications providers may act as processors, independent
  controllers/data fiduciaries or merchants of record for their own functions,
  as explained in their notices and contracts.

These roles can vary by transaction and jurisdiction. The final notice and
data-processing agreement must confirm them rather than relying on this draft.

<span id="data-we-handle"></span>
## 3. Personal data we handle

Depending on how a person uses the Platform, ClipperDesk may handle:

| Category | Examples |
| --- | --- |
| Identity and contact | Name, email, mobile number, date of birth where supplied, profile details and normalized contact values used for matching |
| Account and access | Login identifiers, password hashes, email-verification state, multi-factor state, recovery and session metadata, workspace membership, role and permissions |
| Business and staff operations | Business identity, locations, hours, services, prices, staff profile, qualifications, schedule, leave, resource rules and operational notes |
| Booking and client records | Appointment details, preferences, referral source, cancellation/no-show state, waitlist requests, client history and secure-link evidence |
| Consultation and consent | Form questions and answers, typed-signature evidence, consent wording/version/time, allergies, sensitivities, formulas, treatment context, notes, images and attachments where a Business Customer chooses to collect them |
| Commerce and subscription | Plan, trial, invoice, amount, currency, tax and receipt evidence, transaction and provider references, payment status, refund/correction history and limited payment-method descriptors |
| Communications | Channel choice, consent or service basis, template/version, recipient, delivery state, suppression and provider reference; content hashes or minimized diagnostics where supported |
| Device, security and audit | IP address and request metadata where required for security, correlation ID, browser/session information, access evidence, errors, rate-limit and audit events |
| Support and privacy cases | Request type, identity-verification evidence, correspondence, reviewer, deadline, export/correction/withdrawal result and retained-data preview |

ClipperDesk is not designed to store full card numbers, card security codes or
online-banking credentials. Payment details entered in Stripe-hosted payment
components are handled by Stripe. ClipperDesk retains only the evidence
needed to reconcile the transaction and operate the related subscription,
booking, deposit, sale or refund.

### 3.1 Sources

Data may come directly from the person, a Business Customer or Authorized User,
the person's interaction with a booking or secure link, a payment or messaging
provider, a device/browser, or records generated when the Platform performs an
authorized action. A Business Customer must have authority to provide the data
it imports or records.

<span id="how-we-use-data"></span>
## 4. How and why data is used

ClipperDesk proposes to use personal data only as reasonably needed to:

- create, verify, secure and administer accounts and Business Customer
  workspaces;
- configure services, staff, availability and resources;
- search availability, hold capacity, create and manage appointments, operate
  a waitlist and support salon checkout;
- maintain client history, consultation forms, consent evidence and protected
  attachments as instructed by a Business Customer;
- process subscription and appointment-payment events, issue transaction
  evidence, prevent duplicate charges, handle refunds/corrections and reconcile
  provider records;
- send requested transactional messages and, only with the required choice and
  current permission, marketing messages;
- provide exports, investigate failures, prevent abuse, protect accounts and
  maintain append-only security or financial evidence;
- meet contractual, accounting, payment, tax, regulatory and legal obligations;
  and
- understand and improve reliability and product use through approved,
  minimized information that does not include sensitive request content.

### 4.1 Legal grounds

The lawful basis depends on the person, purpose and applicable law. It may
include performing a contract or requested service, complying with law,
protecting legitimate security and operational interests, or consent. Marketing
permission is separate from appointment or receipt delivery. Withdrawing
marketing consent does not cancel a booking or prevent messages needed to
provide the requested service.

The final notice must map each purpose to an approved legal ground for the
launch market. This draft does not make a universal legal-basis claim.

## 5. Sensitive salon information

Some Business Customers may record allergies, sensitivities, consultation
answers, images or other information that a person considers sensitive. Good
Hours uses encrypted application fields for specified notes, answers and
preferences; private files use tenant-scoped storage and expiring access links.
Only users with the required Business Customer permission should access that
information, and protected access is designed to leave content-free audit
evidence.

Business Customers must collect only what they need, explain why they need it,
limit staff access, choose appropriate retention and avoid using ClipperDesk as
an electronic medical record or for emergency care.

<span id="payments"></span>
## 6. Payments and payment providers

ClipperDesk currently separates two payment contexts:

1. **ClipperDesk SaaS subscriptions.** Stripe hosts checkout and the customer
   billing portal and processes subscription payments under Stripe's privacy
   notice and applicable service terms.
2. **Salon-client appointment payments.** Stripe is the implemented adapter for
   eligible appointment deposits or card payments. The salon's booking page
   identifies the service provider and the applicable amount, currency and
   cancellation policy.

ClipperDesk sends providers transaction identifiers, amount, currency, business
and purpose metadata needed to process and reconcile a payment. Providers may
collect identity, contact, billing, device, fraud-prevention and payment-method
information directly. Their use of that information is governed by their own
notices. A browser redirect or success message is not treated as final payment
evidence; verified provider events control transaction status.

<span id="sharing"></span>
## 7. When data is disclosed

Personal data may be disclosed only for an authorized purpose to:

- the Business Customer and its permitted Authorized Users;
- Stripe for SaaS subscription checkout, billing management, eligible
  appointment payment, fraud prevention, refund and reconciliation functions;
- Resend and Twilio for configured email and WhatsApp delivery;
- approved hosting, storage, monitoring, security and professional-service
  providers needed to operate the Platform;
- a successor in a proposed corporate transaction, subject to appropriate
  confidentiality, notice and legal safeguards; or
- a court, regulator, law-enforcement body or other party where disclosure is
  legally required or reasonably necessary to protect rights, safety and the
  integrity of the Platform.

ClipperDesk does not propose to sell personal data. No third-party advertising
pixel, fingerprinting or session-replay provider is approved in the current
release. An accurate public subprocessor/provider list, processing locations
and contract safeguards remain required before launch.

### 7.1 International processing

Providers and authorized support operations may process information in more
than one country. The final operator must identify relevant locations and use
the contract, notice and transfer safeguards required by applicable law. This
draft does not claim that every transfer mechanism or destination has been
approved.

## 8. Security

ClipperDesk uses documented tenant isolation, permission checks, purpose-bound
secure links, encryption for specified sensitive fields, protected file
delivery, webhook verification, idempotency and append-only evidence for
certain hicd-risk actions. Access is limited according to role and Business
Customer context.

No internet service is risk-free. Production malware scanning, target
infrastructure, independent penetration testing, live provider certification,
monitoring and recovery evidence remain launch controls. Current safeguards and
limitations are described on the [Security page](/security) without claiming a
certification or guarantee.

If the approved operator becomes aware of a personal-data breach, it must
investigate, contain, document and notify affected organizations, people and
authorities as required by applicable contracts and law. The final incident
contact and notification process are not yet approved.

<span id="retention"></span>
## 9. Retention and deletion

ClipperDesk keeps information according to its purpose, Business Customer
instructions, provider evidence needs and applicable legal obligations. The
current implementation preserves:

- account and Business Customer information while the relationship is active;
- immutable consent wording, submissions and related evidence;
- append-only audit, payment, refund, correction, inventory and commission
  history where rewriting would make the record unreliable;
- private privacy-export artifacts marked for 30-day availability, with short
  expiring download links; and
- a dated Business Customer data-export window of up to 30 days after
  subscription termination where the account remains eligible.

Those product windows are not a complete retention schedule. Record-specific
retention, attachment disposal, backups, legal holds and anonymisation remain
blocked by OPEN-10 pending named Indian Privacy/DPO, counsel and Product
approval. A deletion/anonymisation case currently records a retained-data
preview and may enter `blocked_policy`; it does not falsely report that
destructive processing occurred.

<span id="rights"></span>
## 10. Choices and privacy rights

Depending on applicable law and ClipperDesk' role, a person may be entitled to
ask for access, information, correction, completion, export, withdrawal of
consent, restriction, objection, erasure/anonymisation, grievance review or
another available remedy. Identity and authority must be verified before
disclosing or changing protected information.

Salon Clients should first use the contact details supplied by the salon or
barbershop that controls their booking record. Authorized Business Customer
staff can record and track export, correction, consent-withdrawal and
deletion/anonymisation cases in ClipperDesk. Requests involving ClipperDesk'
direct account or billing data require a public privacy/grievance channel,
which must be approved and tested before this notice becomes effective.

Withdrawing consent affects future consent-based processing; it does not
require erasing lawful historical evidence or prevent processing that another
valid legal ground requires. A person may complain to the competent regulator
where applicable. The final notice must identify the correct grievance and
regulatory path for the launch jurisdiction.

### 10.1 Account settings and communications

Authorized Users may update available profile and security settings. Salon
Clients can update permitted appointment contact details through a valid secure
link. Marketing choices can be withdrawn without opting out of necessary
booking, payment, receipt or security messages. Provider or channel-specific
unsubscribe mechanisms apply where shown.

<span id="cookies"></span>
## 11. Cookies and measurement

ClipperDesk uses browser storage and cookies that are reasonably necessary for
sessions, authentication, security, preferences and request protection. The
current marketing event adapter has no external network or storage consumer;
no advertising pixel, third-party marketing tracker, fingerprinting or session
replay is approved.

Necessary storage may be used without an optional marketing choice where law
allows. Any future non-essential analytics or advertising provider requires a
separate approved purpose, consent classification, retention/deletion process
and updated notice before activation. Browser controls can block storage, but
blocking necessary session or security storage may prevent parts of the
Platform from working.

## 12. Children and accounts

ClipperDesk business accounts are intended for people who have legal capacity
and authority to act for a Business Customer. Salons may serve minors and may
need to record a date of birth or guardian-supported consent for a service. The
Business Customer is responsible for determining whether that processing is
appropriate and obtaining any required parent or guardian authorization.

The final notice must state the approved age, guardian verification and child-
data rules for the launch market. ClipperDesk must not knowingly use a minor's
data for targeted advertising.

## 13. Changes to this Policy

The approved Privacy Policy must show its version and effective date. Material
changes should be explained through an appropriate direct or in-product notice
before they take effect, and renewed consent should be obtained when required.
Prior approved versions should remain available for transaction and acceptance
evidence.

<span id="contact"></span>
## 14. Contact, grievance path and approval gate

The legal operator, registered address, privacy contact, grievance officer and
security-reporting channel are not yet approved. Do not send identification,
payment credentials, consultation details or other sensitive information to an
invented or unverified address.

This draft may become effective only after named Privacy/DPO, Legal, Product,
Security and Operations owners approve the controller/processor roles,
purposes, provider list, international processing, retention schedule,
children's-data position, rights workflow, response handling and public
contacts. OPEN-10 remains a critical blocker and this draft does not resolve
it.
