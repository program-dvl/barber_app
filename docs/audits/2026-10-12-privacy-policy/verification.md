# Verification — 2026-10-12

## Automated results

- **144 passed, 2,101 assertions, 1 existing intentional skip** in 8.17 seconds.
  Run: `php artisan test --compact tests/Feature/Marketing
  tests/Feature/GoogleAuthenticationTest.php tests/Feature/AuthenticationTest.php
  tests/Feature/RegistrationTest.php tests/Feature/Billing/SubscriptionLifecycleTest.php
  tests/Feature/ClientRecords/ClientCrmConsentTest.php` (one command).
  The skipped registration test requires registration to be disabled; this app
  enables it. Full sanitized output: [regression-tests.txt](regression-tests.txt).
- Tests cover public/guest/account-holder access, real article content with the
  optional SSR gateway unavailable, the SSR-response branch without duplicate
  titles/articles, stable anchors and internal links, Google disclosures,
  approved and incomplete publication configuration, canonical www redirection,
  indexation and sitemap inclusion only after approval, escaped configuration,
  authentication/provider-link/TOTP regressions, actual redirect scopes and
  non-persistence of the supplied Google profile picture.
- Production client and SSR builds pass on **Node v24.19.0**. The default shell's
  Node v20.11.0 was correctly rejected by the repository's version guard; the
  installed supported Node runtime was then used. No runtime/dependency version
  was changed. Build warnings concern the existing daisyUI @property rule and
  static font paths resolved at runtime; no build error remains.
- A read-only local database count found **0 Google links with retained legacy
  OAuth token values**. The sandbox denied the initial connection; the authorized
  read-only retry succeeded. No records or credentials were modified. This is
  local evidence, not a scan of the production database.
- Public asset budget check passes for **17 route entries**.
- Scoped formatting (`vendor/bin/pint --dirty --test`) and `git diff --check` pass.
- `legal:check-privacy` intentionally exits with failure in the current local
  configuration: final approval/date, provider/region inventory and operational
  review remain missing. This is a verified release safeguard, not a failed
  public route or an assertion that the text has been legally approved.
  [Current publication check](publication-check.txt).

## Browser and HTML checks

- Inspected local initial HTML and interactive rendering at **320, 360, 390,
  768 and 1440 pixels**. Each measured width matches document scroll width;
  one h1, one main landmark, no empty links and no broken loaded images.
  [Measurements](responsive-checks.json).
- Existing shared public header/footer render. No review banner or privacy
  footer's preview line remains. The sidebar is absent; the compact contents
  disclosure is closed initially and expands into two columns on desktop and
  one on mobile. It uses native details/summary and works independently of JS.
- Clicking the Google contents link updates the hash and focuses the target
  heading. The content remains below the sticky header.
- Enter expands the contents disclosure. Mobile navigation opens, Escape closes
  it, focus returns to Open navigation, and body scroll locking is released.
- Pricing footer -> Privacy Policy completes a standard full visit and renders
  the full article and page-owned title/canonical. The local configured canonical
  is http://barber_app.test/privacy-policy; publication checks require HTTPS in
  the production configuration. Local host aliases are not redirected to an
  external production website.
- Native initial HTML has the full policy and matching shell even without a Vue
  SSR response. This is verified by parsing the HTTP response, not by assuming
  that serialized Inertia JSON counts as visible legal content.
- Focus styles, heading hierarchy, skip link, 44px section/navigation targets and
  print styles are present. The small contents hint uses #475569 on #eef2f7
  (approximately 6.74:1 contrast) rather than the lower-contrast muted token.

Screenshots: [desktop](desktop.jpg), [mobile](mobile.jpg),
[expanded desktop contents](contents-desktop.jpg),
[expanded mobile contents](contents-mobile.jpg),
[Google disclosure](google-disclosure.jpg).

## External observations and limits

Before implementation, the production apex URL returned 200, www returned a
301 to the apex, robots.txt allowed access, and the policy sent noindex in both
its HTML and response header. Its HTTP HTML had no h1/h2/article policy content.
These production observations describe the old deployment. **No deployment or
Google resubmission occurred.** Recheck after authorized release.

This verification is not an independent WCAG assessment, real-device/multiple-
browser certification, database concurrency test, production privacy request
rehearsal, Google provider-console audit or legal approval. No live authentication,
payment, message, deletion or transfer was initiated during the audit.
