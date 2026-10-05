# Local SMS diagnosis — 2026-10-04

Scope: read-only review of the main local environment, communication records,
scheduler registration and Twilio account APIs. No SMS was sent; environment,
sender profiles, consent, allowances and queued messages were not changed.

## Confirmed findings

- `COMMUNICATIONS_TRANSPORT_MODE` and `COMMUNICATIONS_LIVE_SEND_ENABLED` are
  absent from the local `.env`. Effective values are `fake` and `false`.
  Recent SMS records for business 17 use provider `fake`; these are simulations.
- Twilio authenticated read-only requests with the configured Account SID/Auth
  Token and API key/secret (HTTP 200). Account status is active and type is Full.
  This validates authentication for the inspected reads, not message-create
  permissions or end-to-end delivery.
- The configured account's IncomingPhoneNumbers API returned zero owned numbers
  and no next page. A separate exact-number query also returned zero matches for
  `TWILIO_SMS_FROM`. The configured numeric SMS sender is therefore not an owned
  SMS-capable phone number on this account. Geographic permissions do not
  provision a sender.
- Test Account SID and test Auth Token equal the live credentials. These do not
  establish Twilio's separate test-credential setup. `twilio_test` is a simulated
  API mode, not real handset delivery.
- `communications:process-events` is registered every minute.
  `QUEUE_CONNECTION=sync` means delivery jobs execute inline; a separate queue
  worker is not required with this configuration. No `schedule:work` process was
  visible in the local process snapshot. No SMS was due at the database snapshot.
- Historical records include authentication failures (`twilio_http_401`), sender
  readiness suppression and plan-limit suppression. Current successful read
  authentication does not establish the cause of the older 401 responses.
- Business 17 has an active simulated platform profile with the test sender
  `+15005550006`. `CommunicationSenderService::resolve` returns an existing active
  profile without reconciling a transport change; live `TwilioSmsProvider` prefers
  that saved identifier. Changing the environment flags alone can therefore
  retain the test sender. Sender promotion needs to address this code path and
  existing queued simulation work before real delivery is enabled.
- The generated callback URL uses `http://barber_app.test`, which Twilio cannot
  reach from the public internet. Public callback routing is needed for delivery
  status and inbound STOP/START processing. This is separate from the observed
  no-provider-call simulation behavior.

## Required setup sequence

1. Provision/verify an SMS-capable sender on the configured Twilio account and
   put that identifier in `TWILIO_SMS_FROM`.
2. Reconcile saved simulation sender profiles and queued simulation work as part
   of transport promotion; do not assume environment changes refresh profiles.
3. After approved live setup, use `COMMUNICATIONS_TRANSPORT_MODE=live` and
   `COMMUNICATIONS_LIVE_SEND_ENABLED=true`.
4. Clear cached configuration if applicable and restart long-running scheduler
   and queue processes from this project. For the current sync queue, local
   `php artisan schedule:work` is sufficient for scheduled dispatch.
5. Configure a reachable public HTTPS application URL and the delivery/inbound
   callbacks, then verify actual delivery and compliance for the target country.

References:

- [Module specification](../../modules/communications.md), including transport
  promotion and existing launch gates.
- [Twilio sender error 21606](https://www.twilio.com/docs/api/errors/21606).
- [Twilio test credentials](https://www.twilio.com/docs/iam/test-credentials).
- [Twilio SMS Geo Permissions](https://www.twilio.com/docs/messaging/guides/sms-geo-permissions).

No credential values, client destinations or message bodies are recorded here.
