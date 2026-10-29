# Twilio SMS launch preparation — 29 October 2026

Requirements: FR-13; ADR-041/054/067/073. User confirmed SMS for launch and
requested WhatsApp requirements only. This record separates account configuration,
local verification, and deployment evidence.

## Findings and changes actually verified

| Item | Evidence / outcome |
| --- | --- |
| Account | Active Full account; live Auth Token and existing API key authenticated read requests. No credentials printed. |
| Owned sender | +1 855 362 0201 supports SMS/MMS. Toll-free verification remains **IN_REVIEW**. Approval cannot be completed by changing an environment setting. |
| Sender pool | Service `MGc9c098bed1a7e3331a69990989ca7047` originally contained only `CLIPPERDESK`. Added the existing owned number; verified both the service API and phone console. No number purchased. |
| Delivery callback | User-configured `/api/webhooks/twilio/status` returned 404 on the deployed app. Changed service callback to `https://clipperdesk.com/communications/webhooks/twilio`, the existing route which rejects an unsigned request with 400. Added the API-style aliases locally for compatibility after deployment. |
| Incoming messages | Configured both service inbound handling and number SMS webhook to `https://clipperdesk.com/communications/webhooks/twilio/inbound`, POST. Service uses its own inbound webhook. |
| Local configuration | Live mode was already enabled. Replaced a sender that did not match the owned number, selected the Messaging Service, and reduced the environment to the seven-setting block below. |
| Geographic permissions | Reviewed the logged-in Chrome page. US/Canada, Europe, South America and many Asian/Oceanian destinations enabled. Africa disabled. Some Asian destinations remain unchecked (including Bangladesh, Pakistan, Sri Lanka and Oman); unavailable countries also remain disabled. Permissions were not expanded. This is not a country-by-country carrier/registration certification. |
| Real SMS test | User authorized one test to their Indian number and confirmed receiving the exact test text. Twilio subsequently reported **delivered**, one segment, no error. No appointment/customer record was created and no WhatsApp message was sent. This proves that destination/route, not US/Canada delivery. |
| Scheduler | Schedule lists `communications:process-events` every minute. At inspection no local `schedule:work` or queue worker process was running. Queue connection is `sync`; a separate queue worker is unnecessary for this connection. Did not start a scheduler or dispatch the existing backlog. |
| WhatsApp | Only the offline sandbox sender was present; no Content templates returned. No production WhatsApp channel or SMS fallback enabled. |

The test used the production SMS adapter and Messaging Service. Its callback went
to the deployed application, while its outbound request originated locally. It
was a provider delivery test, not a saved booking/reminder/callback round trip in
the local database. Twilio reported a network numeric sender on that India route;
the display sender is not guaranteed to be the alphanumeric name in every market.

Screenshot: [saved number configuration](phone-after.png).
Only sender association and messaging callbacks were changed on Twilio. Smart
encoding, validity period, voice settings and registrations were preserved.
Private rollback snapshots were saved locally, outside version control; the old
API credentials were removed from `.env`, not revoked in the Twilio account.

## One shared production SMS block

The actual private values are grouped at the end of the ignored local `.env`.
Copy **that entire seven-setting block** to the live server after deploying this
code. Do not copy the rest of the local environment or commit the token.

```dotenv
COMMUNICATIONS_TRANSPORT_MODE=live
COMMUNICATIONS_LIVE_SEND_ENABLED=true
TWILIO_ACCOUNT_SID=<existing_account_sid>
TWILIO_AUTH_TOKEN=<existing_auth_token>
TWILIO_MESSAGING_SERVICE_SID=MGc9c098bed1a7e3331a69990989ca7047
TWILIO_SMS_FROM=+18553620201
TWILIO_WEBHOOK_BASE_URL=https://clipperdesk.com
```

Only two credentials are needed: account SID and Auth Token. The same token
verifies Twilio signatures. No separate webhook secret, test credentials,
WhatsApp variables, or separate inbound/status URLs are required. Legacy API-key
and per-endpoint overrides remain supported internally for existing deployments;
they are not part of this launch block. `.env.example` retains safe fake/off
defaults for fresh installations and documents this compact structure.

The base URL derives the two existing callback paths. Keeping it identical is
valid for live provider requests from either environment. Delivery updates and
client action links still need to reach **the same application/database that
created the message**. A local-only hostname cannot receive Twilio callbacks or
serve a remote client's action link. A full local rehearsal needs a deliberately
configured public HTTPS endpoint reaching that local application; none was opened.

## US/Canada handoff

1. Deploy this backend change and the shared seven-setting block to the live app.
   Clear/rebuild cached configuration according to the deployment process and
   restart long-running app/queue/scheduler processes so they reload settings.
2. Run `php artisan communications:check-twilio --provider-read` on the live
   server. It performs GET requests only. Today it exits nonzero specifically
   because toll-free verification is `IN_REVIEW`; it does not claim delivery from
   the presence of credentials. Recheck until the returned status is
   `TWILIO_APPROVED` and the checks pass. No further sender switch is intended.
3. Keep Laravel's scheduler running every minute in production. Locally the
   corresponding command is `php artisan schedule:work`. If the server uses
   a queued connection instead of `sync`, also run its queue worker under the
   existing process manager. The scheduler processes real saved events; it is not
   a harmless readiness check.
4. Confirm owner SMS switch, client SMS permission, valid +1 recipient, eligible
   notification event and plan/credit allowance. The approved USD capacity route
   includes `+1`. Geo permission and a pool entry do not override those checks.
5. After approval, perform an authorized US and Canada booking/reminder test,
   verify delivery in the same database, client action links and STOP/START.
   No US/Canada recipient was supplied or tested in this audit. No deployed
   booking/reminder journey was certified. Country approvals/policies under
   OPEN-11/15 remain operator release controls.

New eligible SMS will use the service and its approved number automatically;
alphanumeric IDs cannot replace a number for US/Canada. Carrier approval is the
remaining sender-side gate, not an automatic deployment or scheduler setup.
Do not automatically resend old failed/test/unknown-outcome messages after
approval. Configuration changes retire captured platform profiles, and the
delivery guard suppresses stale queued sender/transport work before a provider
attempt or credit debit. Review any legitimate historical work separately.

Incoming replies on a shared platform number resolve only an unambiguous tenant
and client. Ambiguous replies never open another business's conversation or
restore permission. Twilio's sender-level STOP protection remains applicable;
global application-level shared-number preference routing is OPEN-24.

## Application safeguards

- SMS requests use `MessagingServiceSid` without `From`, allowing Twilio to select
  the appropriate pool sender. Historical branded profiles retain their own path.
- Live callbacks require HTTPS and valid Twilio signatures, including an exact
  configured public origin behind TLS termination. CSRF exceptions are limited
  to the specific callback routes; no unsigned callback was accepted.
- Stable callback payloads deduplicate across retry tokens. Delivered state cannot
  regress to sent/failed. Early verified callbacks remain pending until the SID
  is saved, then reconcile after acceptance and on the scheduled event sweep;
  that reconciliation does not resend the SMS.
- Safe numeric error codes survive without retaining raw provider error text.
  Unreachable phones do not automatically become opt-outs; Twilio 21610 does.
- No consent, allowance, tenant or appointment-history bypass was introduced.

## WhatsApp prerequisites — deferred

Before a future explicitly approved release:

- Complete Twilio/Meta production sender onboarding, business/display-name
  approval, number registration and applicable account limits. The sandbox sender
  is not the production sender.
- Record a separate, explicit client WhatsApp permission with reviewed country
  copy. Do not reuse SMS permission or silently move existing clients.
- Obtain approved utility Content templates for each supported operational use
  case, locale and required business-initiated message. Map template numeric
  variables to captured appointment data and test links/content rendering. The
  retained legacy adapter alone is not exit evidence.
- Specify when free-form replies are allowed in the customer-service window.
  Test signed inbound/delivery callbacks, opt-outs and tenant routing.
- Rehearse SMS fallback only after a definitive WhatsApp rejection/failure and
  only with independent SMS permission/allowance. Timeouts and unknown/delivered
  outcomes must never trigger a duplicate message. Confirm billing and regional
  availability before enabling the channel.

## References

- [Twilio alphanumeric sender support](https://www.twilio.com/docs/glossary/what-alphanumeric-sender-id)
- [Messaging Service selection and status callbacks](https://www.twilio.com/docs/messaging/api/message-resource)
- [Toll-free approval](https://www.twilio.com/docs/messaging/compliance/toll-free/console-onboarding)
- [WhatsApp production and template requirements](https://www.twilio.com/docs/whatsapp)

Backend test evidence is recorded in `docs/project-status.md`. Local code and
environment were changed; backend deployment remains outstanding.
