# Business email notifications

Status: Implemented locally and verified. Amazon SES production sending remains
blocked on verified production identities, DNS authentication, account
production access, queue operations and deliverability rehearsal.

Authoritative scope: [FR-01](../product-requirements.md#fr-01-saas-registration-and-subscription-management),
[FR-05](../product-requirements.md#fr-05-staff-profiles-schedules-roles-and-permissions),
[FR-13](../product-requirements.md#fr-13-notifications-and-communication),
[FR-19](../product-requirements.md#fr-19-shop-settings-and-audit-history),
[FR-20](../product-requirements.md#fr-20-platform-super-admin-and-support-operations),
and [ADR-040](../decisions.md#adr-040-use-amazon-ses-for-clipperdesk-business-account-email).

## Boundary

Business-account email is addressed to owners and login-bearing team members.
It is separate from the client Communications domain:

- account, security, team access and SaaS billing messages use Laravel
  Notifications and the account email delivery ledger;
- appointment, waitlist, walk-in, receipt and other client messages continue to
  use `CommunicationIntent` so tenant branding, client consent, quiet hours,
  channel fallback and delivery history remain authoritative;
- transactional account and security messages are not marketing subscriptions
  and do not include promotional content.

## Owner and staff journey

| Stage | Email | Recipient and trigger |
| --- | --- | --- |
| Registration | Verify email address | New password registrant; signed, expiring verification link |
| Verified owner setup | Workspace ready and trial started | Owner, after verified Business/Membership/Subscription creation commits |
| First publish | Booking page is live | Verified Business owners, once on first successful publish |
| Sign-in | New browser/device sign-in | Verified user on first successful sign-in for a browser fingerprint; configurable to every sign-in or off |
| Password recovery | Reset password requested | Account address; signed, expiring reset link |
| Password security | Password changed | Account address after a settings change or completed reset |
| Email security | Address changed | Old address receives a masked warning; new address receives verification |
| Two-factor security | Enabled, disabled, recovery codes regenerated, recovery code used | Account address immediately after the Fortify security event |
| Team access | Secure invitation | Exact invited address; single-use, expiring, business-bound link |
| Team access | Invitation accepted | New team member after Membership creation commits |
| Team access | Role/modules/locations changed | Affected team member |
| Team access | Access revoked | Affected team member after sessions/tokens are invalidated |
| Team access | Access restored | Affected team member |
| Platform security | Support access approved, entered or revoked | Verified owners, with operator/reason/expiry context where applicable |
| Business lifecycle | Workspace suspended, closed or restored | Verified owners after the platform status transition |
| SaaS billing | Trial ending, trial expired or trial extended | Verified owners through the deduplicated billing notice queue |
| SaaS billing | Subscription activated, plan change requested | Verified owners after the authoritative lifecycle transition |
| SaaS billing | Cancellation scheduled, reactivated, canceled or ended | Verified owners after the lifecycle transition |
| SaaS billing | Renewal failed/retry failed, access restricted, payment recovered | Verified owners through the deduplicated billing notice queue |

The client journey already supplies booking confirmation/pending/approval/
rejection, appointment change/cancellation/reminder, deposit request/receipt,
payment receipt, waitlist opening, queue update, feedback request and rebooking
reminder email where an address exists and the Client/Appointment has not opted
out. Those messages are documented in [communications.md](communications.md).

## Delivery contract

- Production uses Laravel's native `ses` transport and the official AWS SDK.
- Local and test defaults remain `log`/`array`; credentials alone do not make
  development runs send production mail.
- Queued business notifications use the `emails` queue, retry with bounded
  backoff and dispatch only after the surrounding database transaction commits.
- Verification and password-reset links remain synchronous so authentication
  does not depend on a worker accepting the first job.
- Account, Security and Billing are separate sender identities/configuration
  streams. They may initially share one verified SES address.
- The SES configuration-set name is optional and supplied through
  `AWS_SES_CONFIGURATION_SET` for deployment-owned event publishing.
- `email_notification_deliveries` records notification type, tenant/user
  references, stream, mailer, masked/hashed recipient, attempts, accepted/
  failed state and provider message ID. It does not store message bodies or a
  plain recipient address.
- `accepted` means the configured mail transport accepted the message. It does
  not claim inbox delivery; SES bounce/complaint events still require the
  deployment's configuration-set destination and operational monitoring.
- Sign-in history is user-scoped, expires by policy and never changes tenant
  authorization. Browser fingerprinting uses a keyed hash; alert text includes
  the observed IP and local business time for investigation.

## Environment contract

Set `MAIL_MAILER=ses`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and
`AWS_DEFAULT_REGION`. Temporary AWS credentials may also use
`AWS_SESSION_TOKEN`. Configure the three sender streams and reply-to values from
`.env.example`; every address must be verified by SES until the AWS account and
region leave the SES sandbox.

The queue worker must consume `emails`, and the scheduler must continue running
`billing:send-notices` and the daily `account-email:prune-login-activity`
retention task. `ACCOUNT_LOGIN_ALERTS` accepts `new_device` (default), `always`,
or `off`.

## Production gates

Before enabling real mail, Operations must verify the final production domain,
SPF, DKIM, DMARC, custom MAIL FROM/return-path, SES production access and sending
region; configure bounce/complaint/delivery event destinations; define alarms
and suppression handling; run seed-list and major-client rendering checks; and
prove the queue, retries and failed-job recovery process. OPEN-11 remains a
launch blocker, so local `clipperdesk.com` examples are configuration direction,
not evidence of domain ownership or sender certification.

## Verification

`tests/Feature/AccountNotifications/BusinessEmailJourneyTest.php` covers the
registration-to-welcome sequence, new-browser deduplication, dual-sided email
change notice, two-factor events, privacy-safe delivery recording, ClipperDesk
rendering, support-access/business-status owner notices, retention pruning and
the installed SES transport contract. Adjacent authentication, team access,
onboarding and subscription lifecycle suites protect their trigger boundaries.
