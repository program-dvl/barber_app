# Area-update signup review — 2026-11-14

MP-05, local evidence. Product Design audit used the owner-provided screenshot and
current browser captures. The owner subsequently clarified: compact newsletter UI,
immediate opt-in, no required confirmation email.

Findings and changes:

1. First signup: normal local MySQL had no subscription table. Applied only the three
   2026-11-14 launch migrations locally. Storage failures now return a safe reference
   and useful recovery, never an unhandled 500 followed by a misleading resend warning.
2. Form: replaced the tall, vertically centered warning layout with a compact, aligned
   newsletter row and single shared success state. Inputs stack on phones, retain
   visible labels, focus rings, explicit consent and 48px controls.
3. Subscription: immediately activates on explicit opt-in. Verification remains a
   separate field; old pending records are not silently promoted. Repeat submissions
   do not duplicate records or welcomes. “Add another area” requires fresh consent.
4. Email: optional welcome has no confirmation action. Mail failure leaves signup
   successful, records a safe operator reference and does not trigger automatic resend.
   Footer copy now describes area updates rather than account sign-in.
5. Unsubscribe/retention/sender: retained POST-only cancellation, original legacy-link
   expiry, encrypted recipients, bounded retention and operator location review.

Verification:

- 32 tests / 648 assertions passed across launch, email presentation, discovery and
  automatic publication. Includes welcome failure, storage failure, duplicate signup,
  direct opt-in, legacy links, unsubscribe and one-time sender safety.
- Client and SSR builds pass; pre-existing font/DaisyUI warnings remain.
- One authorized real test confirmation was accepted by SES; owner confirmed receipt
  in the sales inbox. This happened before the immediate-signup refinement. No second
  live welcome is claimed. Final welcome HTML rendered/tested locally.
- Browser screenshots in this directory record desktop and mobile form/success states.
  A reactive receipt bug and repeated another-area success rendering bug found during
  browser QA were corrected and retested. The newsletter IP limit is 20/minute, while
  recipient/area idempotency and daily welcome limits prevent duplicate sends.
  No production deployment or full accessibility conformance claim.


Final captures accepted after inspection: `newsletter-desktop.jpg`,
`newsletter-mobile.jpg`, `subscribed-mobile.jpg`, `welcome-email.jpg` and
`welcome-failure-still-subscribed.jpg`. The transport-outage browser check showed
“You’re on the list,” not a delivery warning. No new welcome email was sent to a
real recipient after the already-authorized inbox test. Final console check: no errors.
