# Google Analytics 4 — public acquisition

Scope: PRD sections 4/12/15, FR-01/19 and ADR-079. No new JavaScript package,
Tag Manager container, API key, OAuth credential or Measurement Protocol secret.

## Minimal application configuration

Only add `GOOGLE_ANALYTICS_MEASUREMENT_ID=G-…` to the existing Forge environment
after the separate ClipperDesk web stream is ready. Preserve all existing lines
and values. Do not reuse a measurement ID from another product. The application
reads this value on the server, so rebuilding cached Laravel configuration is
required; a `VITE_` variable or duplicate tag in Blade is unnecessary.

Collection is disabled without a valid ID, outside production, on a different
origin, for signed-in users and on excluded routes. The configured brand URL
must already be the HTTPS primary origin. Do not change it solely for analytics.
Leave the local `.env` unchanged. Never load analytics on book.clipperdesk.com.

## Google setup

Create a dedicated ClipperDesk account/property and one web stream for
`https://clipperdesk.com`. Preserve unrelated accounts/properties/settings.
Account creation requires the owner's explicit approval of the operator country,
Google Analytics Terms of Service and applicable Data Processing Terms. Do not
accept on the strength of repository facts alone.

Disable optional account data sharing. Set reporting currency to USD and the
owner's reporting time zone (initially India / Asia/Kolkata). Disable enhanced
measurement entirely, including page-history, search, form, outbound-click,
scroll, download and video tracking. The app sends manual sanitized pageviews;
automatic measurement risks duplicate counts and unreviewed fields.

Keep Google Signals, user-provided data collection, advertising personalization
and product/Ads links off. Use two-month user/event data retention and turn off
Reset user data on new activity. Do not promise aggregated standard reports
expire under that retention setting. Configure no API secrets, audiences,
advertising conversion links, cross-domain or server-side tracking.

## Release and verification

1. Run Marketing backend tests, frontend tests, client/SSR builds and public
   asset budgets. Verify the actual consent panel at desktop and phone widths.
2. Save only the measurement ID in Forge. Verify the environment editor retained
   all existing entries unchanged. Refresh cached configuration using Forge's
   normal save/deploy behavior.
3. Deploy only the reviewed analytics change. Preserve concurrent workspace work.
4. In a guest browser, verify no Google tag/request before a choice or after
   rejection. Accept analytics and inspect the real network: one sanitized
   page_view per public navigation; no queries/referrers/customer information.
5. Verify preferences reopening and withdrawal, retained rejection after reload,
   script outage, GPC/DNT, and hard navigation to registration/private pages.
6. Confirm receipt in the dedicated property's Realtime report; configuration
   presence or a successful build alone does not establish live collection.

The audit records exact IDs and observed outcomes, without credentials or
personal browser identifiers. Local intercepted tests are not real Google receipt.

## Recovery and privacy requests

Missing/blocked Google requests must never block reading, pricing, trial signup
or account access. There are no automatic script-retry loops. Visitors can use
Analytics preferences to reject; this stops future collection, removes accessible
`cd_ga…` cookies and reloads a document that had loaded the tag. Preference storage
is versioned and expires after 180 days; analytics cookies expire after 60 days.

For rollback, clear only `GOOGLE_ANALYTICS_MEASUREMENT_ID` and refresh cached
configuration; verify new guest documents no longer expose analytics config.
Already open documents stop when reloaded. Do not delete the Google property or
historical data as part of routine rollback.

The operator reviews requests received at support@clipperdesk.com. Withdrawal is
not retroactive deletion; no account identity is attached to these records.
Review requests through Google Analytics Admin → Data deletion requests where
applicable, verify the requester and scope, and obtain required approval before
irreversible deletion. No deletion, DPO appointment or regional legal sign-off is
implied by this setup.

References: [manual pageviews](https://developers.google.com/analytics/devguides/collection/ga4/views),
[privacy controls](https://developers.google.com/tag-platform/security/guides/privacy),
[data retention](https://support.google.com/analytics/answer/7667196),
[enhanced measurement](https://support.google.com/analytics/answer/9216061).

## Trial-click transport

Mark `marketing_cta_clicked` as a key event in the dedicated property using
Create with code, **no default monetary value** and once-per-event counting.
The event means a trial-button click only. It is not a completed trial signup,
purchase or revenue. Google's normal events can batch; key events transmit
immediately. Do not create URL-based automatic events or add Ads links.

The browser permits at most 200ms for Google's processing callback before signup
navigation. After Google loads, never truncate its command array: the library
owns a cursor into that live array and may be inside a callback. Disable through
the supported ga-disable flag and unload on private navigation/withdrawal.
Verify the real library, outgoing POST body and actual completed navigation;
a mock callback alone did not catch the production processing failure.

[Google event grouping](https://support.google.com/analytics/answer/9322688),
[callback parameters](https://developers.google.com/tag-platform/gtagjs/reference/parameters).
