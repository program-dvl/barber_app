# Compact SMS booking links — 2026-11-05

## Outcome and scope

Owner requested a professional, unique and secure replacement for the long
signed URL in client SMS. New SMS uses `/s/{22-character-code}` on the application
origin: **48 characters including HTTPS** on `clipperdesk.com`, without a visible
expiry/signature query. The 22 characters encode 128 random bits rather than
reducing private appointment access to a 6–7-character lookup code. ADR-081
records the security and content decision under FR-09/10/13/19.

The existing action row remains authoritative. A SHA-256 lookup hash has a
unique index; an encrypted copy supports stable retries. Both fields are hidden
from model projections. Codes are generated under a row lock only when rendering
SMS, including previously queued messages. Nothing rewrites sent messages,
historical financial records or old signed links. Email keeps its existing link
and detailed content. The endpoint is not a general-purpose URL redirector and
ignores destination/tenant query parameters.

SMS with an appointment action omits the separate appended location footer and
Google Maps URL. Its destination is the existing private appointment page with
the captured address, arrival/parking instructions and directions. Messages
without that appointment action retain their footer. The actual shortened body
is rendered before credit reservation; a capacity-subscription test checks the
reserved quantity against that exact body's SMS segments.

## Verification

- Final scoped backend run: **225 passed / 2,583 assertions**. Covers all
  Communications/PublicBooking tests, location intelligence, private article
  links, HTTP security, public analytics and technical SEO.
- New/extended checks cover stable and distinct random codes, encrypted storage,
  hidden credentials, malformed/unknown/altered codes, expiry/revocation/use,
  tenant-target mismatch, purpose boundaries, per-IP throttling, signed-link
  compatibility, correct appointment and retained location details, shorter SMS,
  email content retention, exact segment metering, provider retry body identity,
  reusable receipts and atomic waitlist claim through the compact URL.
- Actual waitlist GET exposed an existing controller return-type rejection
  (`Inertia\Response` versus Symfony response). Convert the rendered offer with
  `toResponse($request)`. The HTTP test now previews the offer without claiming,
  POSTs the compact URL, rejects reuse and still proves only one winning booking.
- The original reliability fixture's fixed November 2 appointment had become
  past-due. General fixtures now remain upcoming relative to the real clock;
  only the DST-boundary assertion uses its explicit historical date. No production
  reminder or entitlement behavior was changed to make the tests pass.
- Scoped Pint and whitespace checks pass. Four initial SES signature checks
  required a rerun with OpenSSL temporary-file access; the final run passes.
- Isolated disposable SQLite/storage preview, fake messaging, queued dispatch
  disabled and no provider calls. Opened separate real compact links at desktop
  **1512px** and phone **390×844**. Both render the correct synthetic appointment,
  address/unit, expanded arrival/parking and Google Maps action without horizontal
  overflow. The phone directions action measures **44px**. No Google Maps
  navigation or real booking mutation was performed. Reviewed expired-link 410
  at phone width; it retains the existing generic unavailable-page experience.
  Browser viewport override was reset.

[Desktop](desktop.png), [phone](phone.png), [expired phone](expired-phone.png).
Screenshots contain synthetic records only. The foreground app's full-page
captures were inspected; its viewport-only image output was scaled unexpectedly.

## Deployment and limits

Not deployed in this task, and no real SMS/email was sent. Run the additive
migration before the new delivery code is active:

```sh
php artisan migrate --force
```

Use the normal release route/config-cache refresh and long-lived worker restart.
No new environment settings, credentials, DNS, external shortener or Twilio
console configuration is needed. Preserve the existing public application URL
and its database routing. After deployment, verify a newly authorized SMS and its
link against production before claiming carrier/device delivery.

Existing expiry and consumption semantics remain: appointment bridges are
single-use (including GET), receipts reusable until expiry, waitlist GET is only
a preview, and claim POST uses existing atomic ownership checks. Browser/link
previews can still consume an appointment bridge as before; this change does
not certify a different scanner or reopening policy. Old queued messages using
embedded custom directions retain those immutable template words; the omitted
footer is the application-appended footer, not arbitrary historical content.
No live-provider or legal launch gate is waived.

Design references: [OWASP generated token guidance](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
and [Twilio first-party branded links](https://www.twilio.com/docs/api/errors/30525).
