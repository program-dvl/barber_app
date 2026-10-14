# Platform-managed client notifications — 2026-10-14

Implemented locally; not deployed. FR-13 approved scope update and ADR-067 replace
owner template editing and branded sender setup. SES/Twilio remain the existing
providers. No live mail/SMS, production deployment or provider object was created.

## Outcome

The owner no longer builds notification messages, inserts variables, publishes
drafts or onboards a branded text line. Notifications offers email/SMS switches,
quiet hours, coverage cards and one read-only sample gallery. Delivery history
retains the existing scope, pagination, filters and safe retry investigation.
Prepared deposits stay internal under OPEN-21. No extra reminders, marketing,
conversational inbox, payment collection or provider-enable action was added.

New operational intents use a versioned platform template and the shared sender.
Custom published content is retained but is not selected for new events. Captured
queued versions and historical branded profiles remain available for their
existing delivery/callback evidence. Retired owner write routes return 410 after
authorization; foreign records are still rejected. The owner's default-language
and sender selector are removed; current content is English, without a claim of
translation. Every text identifies ClipperDesk and the business.

SES sends structured event-specific HTML and a plain-text alternative. Email has
the existing ClipperDesk PNG/wordmark fallback, status/preheader, introduction,
local visit details, relevant recorded payment facts, arrival instructions and a
purpose-labelled secure action when available. Pending/cancelled/waitlist wording
preserves the actual state. Interpolated values are escaped; malformed, credential-
bearing and non-HTTP(S) links cannot become buttons. Historical content receives
a branded escaped wrapper instead of a new event statement. SES retries,
unknown-acceptance holds, verified address and callback policy are preserved.

## Local database change

Applied only `2026_10_14_000001_add_notification_channel_preferences.php` to local
MySQL, after saving a private snapshot of communication settings. The migration
adds default-true email/SMS booleans; it does not rewrite prior fields, message
content, preferences, sender identities or history. All **8 settings rows** retain
their original fields. Counts remain **153 messages, 23 templates, 11 sender
profiles and 80 intents**. Both new channel choices are enabled for every existing
row. Environment transport flags and provider credentials were not changed.

Managers pause a channel with an audited revision-checked save. Waiting queued,
retried and held work is suppressed under locks and existing SMS reservations
are released in their original window. Already claimed provider work cannot be
recalled. Dispatch rechecks enabled state, consent and client withdrawals. Re-enable
does not revive suppressed records or recreate an idempotent event.

## Browser journey and design review

Review used the in-app browser, a copied synthetic notification-workspace-review
SQLite database on port 8033, fake email/SMS and no live client data. The standalone
email gallery on port 8034 renders the actual Blade view with sample values.

1. **Find the controls — Good.** Two sections replace three. Channel cards and
   grouped coverage replace twelve editable notification rows. The preview is
   one optional read-only action, with no content decisions required.
   [Before](01-before-workspace.png), [desktop](02-notifications-desktop.png),
   [phone](05-notifications-mobile.png).
2. **Save preferences — Good locally.** A phone switch change/save reached the
   saved status and disabled Save control; the SMS choice was restored after the
   check. Application and API tests reject stale revisions, unauthorized edits and
   retained branded-sender selection. Primary business settings were not changed
   by these browser checks.
3. **Read the client email — Good in local rendering.** Confirmation hierarchy,
   timezone, arrival notes and named links replace the flat text screenshot.
   Desktop and 390px phone renders were inspected; the email and owner page both
   have document width equal to viewport width at 390px. PNG branding is used
   when the generated application URL is valid; the text identity remains as a
   fallback. [Desktop](03-email-desktop.png), [phone](04-email-mobile.png).
4. **Preview another event/channel — Good.** Switching to Receipt changes the
   status, copy, recorded amount and action. Email/SMS sample views are read-only;
   the email iframe is sandboxed and shows no historical client data or real token.
   [Receipt email](07-receipt-email-preview-mobile.png),
   [receipt SMS](06-receipt-preview-mobile.png).
5. **Investigate delivery — Existing behavior retained, covered by regressions.**
   Scoped history, financial visibility, accepted/uncertain-send replay exclusions,
   signed callbacks, duplicate dispatch, consent/STOP and reminder rules pass.
   A complete repeated browser history/filter/retry journey is not claimed.

Native labelled switches, visible focus styles, existing guarded dialogs and
44px mobile controls support keyboard/touch use. Screenshots and local checks do
not certify screen-reader behavior, WCAG conformance, every email client or real
inbox/carrier rendering. Email uses fluid table layout and inline essential styles
with a small mobile media query; Outlook-specific presentation tables provide a
width fallback. Real inbox/device certification remains outstanding.

## Verification

- Complete backend suite: **750 passed / 7,021 assertions**, 30 existing skips.
- Focused communications suite: **48 passed / 390 assertions**, including sample
  isolation, platform content/history, retired endpoints, tenant boundaries,
  top-level pauses, dispatch consent checks, HTML escaping and mocked SES HTML/Text.
- Complete frontend suite: **98 passed**. Client/SSR builds pass on installed
  Node 24.19.0; existing CSS `@property` and runtime font-resolution warnings remain.
- Scoped Pint and diff whitespace checks pass. No live sending or load/production
  certification is inferred from these results.

## Rollout and recovery

Deploy the code, built client assets and Blade view with the additive migration.
Run the normal migrations on the intended server and restart long-running workers
and SSR. Retain current SES address/credentials and Twilio transport locks; this
change does not need a provider object or live SMS flag change. Check the public
HTTPS application host serves the logo and that secure action links resolve there.
Send any live rehearsal only through the separately authorized recipient process.

For a code rollback, retain the additive columns and immutable template/sender
history. Restore the prior application build; do not delete message/consent/audit
rows or bulk replay suppressed work. Restoring retired owner customization is a
product-scope reversal and requires an explicit decision. Existing country/legal,
provider, SMS registration, security and payment launch gates remain unwaived.

Design references: [Gmail CSS support](https://developers.google.com/gmail/design/css)
and [W3C descriptive link text](https://www.w3.org/WAI/WCAG21/Techniques/general/G91).
These informed the responsive styling and action labels; they are not evidence of
live inbox certification.
