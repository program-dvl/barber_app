# Worldwide SMS quota — 2026-10-31

Requirements: FR-01/13/19, PRD §9, ADR-076. The owner explicitly rejected the
plan destination restriction found in the deployed booking investigation.

## Reproduction and correction

The deployed booking to an Indian phone had valid SMS selection and consent but
was suppressed with zero attempts because the paid subscription snapshot stored
only `sms_routes={"+1":1}`. Twilio's successful direct test did not exercise this
guard. The first regression run reproduced the issue: 7 failed, 5 passed.

Delivery now reserves one credit per rendered GSM/Unicode segment, regardless of
the destination or business country. It no longer reads destination-prefix maps
or applies country multipliers. Retained pricing metadata remains unchanged:
no paid snapshots, rate-card fingerprints, Stripe Prices or financial history
require migration or reprovisioning. No environment setting was added. Existing
subscriptions benefit on new eligible deliveries after deployment.

The SMS-only channel, owner/client choice, explicit consent, valid international
number, subscription access, whole-message quota reservation, prepaid ledger,
quiet hours, sender configuration and provider safeguards remain in effect.
Unknown outcomes retain their reservation without an automatic repeat; explicit
provider rejections release the recorded reservation. Old suppressed messages
remain historical rather than being automatically replayed.

## Verified locally

- A US appointment with an Indian client processes its saved confirmation event,
  creates the platform SMS once, reaches the simulated Twilio Messaging Service,
  and reserves the actual final rendered segment quantity. Repeated processing
  does not create a duplicate confirmation; paid snapshot stays unchanged.
- US, Canada, India, UK, Germany and Australia use the same allowance, including
  old `+1` snapshots, missing maps, empty maps and weighted historical maps.
- Insufficient allowance causes zero provider attempts and no partial debit.
  Included/prepaid splitting and duplicate delivery consume credits exactly once.
- Invalid numbers and missing consent still suppress before debit or sending.
  Definitive carrier rejection releases credits; timeout remains held and is not
  resent. Previously suppressed historical work is not replayed.

Global quota suite: **17 passed / 101 assertions**. Earlier combined Billing and
Communications run: **353 passed / 2,553 assertions**, before the additional saved
booking-event test. Final full backend: **1,010 passed / 10,661 assertions**, with
30 existing skips.
Scoped Pint and whitespace checks pass. No frontend files or schema changed.

## Deployment and practical limits

Deploy the changed backend normally and reload long-running application workers
as in the existing release process. No `.env` update, data migration, Stripe
provisioning or subscription purchase is required. Do not bypass the release
process by editing production snapshots or installing an ad hoc delivery patch.
After deployment, make one authorized fresh booking with SMS selected to verify
the complete deployed flow and recorded callback. This correction has not yet
been deployed or proven with a real booking SMS. The prior production SES repair
and confirmed test-email receipt remain separate verified evidence.

Worldwide plan eligibility does not promise universal carrier delivery. Twilio
permissions, sender approval and local carrier requirements remain operational
requirements. US/Canada toll-free status was still IN_REVIEW at the last live
check. The platform owns country-specific provider costs under ADR-076; they
must not return as hidden plan restrictions or quota multipliers.
