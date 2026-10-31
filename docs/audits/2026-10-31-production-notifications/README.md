# Production booking notification diagnosis — 2026-10-31

Follow-up: the owner explicitly removed plan-level phone-country restrictions
in ADR-076. The [worldwide quota correction](../2026-10-31-global-sms-quota/README.md)
supersedes the recovery discussion below requiring separate Indian coverage.
The diagnosed deployed records remain unchanged historical evidence.

Requirements: FR-13; ADR-054/067/073. Read-only investigation of the owner's
reported production booking to their controlled Indian test number. No booking,
consent, subscription, credit balance or historical provider message was changed
or resent. Subsequent owner-authorized email recovery is recorded below.

## Verified on the deployed application

- Forge's deployed revision matches local `cf5f7ec`. The minute scheduler is
  installed; the effective queue connection is `sync`, so a missing background
  queue worker is not the explanation for these records.
- Live Twilio configuration, account authentication, owned number, service pool,
  delivery and inbound callbacks pass `communications:check-twilio --provider-read`.
  Toll-free approval still reports `IN_REVIEW`, a separate US/Canada gate.
- Latest matching confirmed booking selected email and SMS. SMS consent exists.
  Its confirmation SMS is **suppressed**, **zero attempts**, with
  `suppression_reason=destination_not_included` and no provider SID.
- Both matching active subscriptions' captured capacity snapshots contain only
  `sms_routes={"+1":1}`. `SmsSegmentCounter::credits` returns null for `+91`, and
  delivery suppresses it before a provider call or credit reservation. Twilio
  geographic permissions do not extend application subscription coverage.
- A bounded read of Twilio messages to the controlled number returns only the
  delivered one-segment operator test from October 29. That direct adapter test
  did not exercise booking consent, allowance or destination coverage. It is not
  evidence that Indian booking SMS is supported by the existing subscription.
- The matching booking confirmation email is **failed** after one attempt with
  `last_error_code=provider_not_configured`, provider `ses`, no provider SID.
- Effective mail driver is SES. Sender is valid and not a placeholder; AWS region
  is present. `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` are absent from both
  effective configuration **and the live .env file**. Configuration is cached.
  This is not merely a stale-cache finding. Local SES configuration has both
  credentials present. Diagnostic output contains booleans only, never secrets.

## Recovery boundaries

The owner explicitly authorized copying the three existing local AWS settings
into the live Forge environment and reloading configuration. Updated only
`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and `AWS_DEFAULT_REGION`; a comparison
verified all unrelated environment lines were preserved. Forge saved the file
with its configuration-cache/worker-reload options enabled. A subsequent live
check reports client email configured. AWS identity-inspection returned
`AccessDenied`, so sender verification could not be established by that read;
configuration presence is not a delivery claim. No IAM permissions were widened.
The private local rollback snapshot is mode 0600, outside the repository; the
temporary credential-transfer file was deleted and the clipboard cleared.
Forge's [environment update activity](production-environment-updated.png) records
the saved production change without displaying secret values.

The owner then supplied a controlled receiving email in response to the request
for one live test. A single branded SES test was submitted from the deployed
application with an exclusive pre-send guard against repetition, separate from
all booking records. The command completed its SES-acceptance branch and the
owner **confirmed receipt of the test email**. This establishes actual sending
and receipt after the production configuration repair; AWS inspection denial
was not a sending denial. No IAM access was changed. Replaying historical mail
is not part of this recovery. A fresh booking/reminder round trip remains a
separate check. Forge's command-output file was unavailable for the successful
test, so no provider ID is invented or claimed from that screen.

The Indian SMS destination requires an explicit country/credit coverage decision
and reviewed provisioning of applicable routes. Do not mutate immutable paid
subscription snapshots, add arbitrary free routes, bypass the allowance guard or
silently replay suppressed messages to make an operator test succeed. Existing
US/Canada coverage still needs toll-free approval before an authorized delivery
rehearsal. OPEN-15/22 remain applicable.

No product code or application subscription coverage changed during diagnosis.
