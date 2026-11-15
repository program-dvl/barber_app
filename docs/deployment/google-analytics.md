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
Cookie settings to reject; this stops future collection, removes accessible
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

## Article/source measurement release — 2026-11-15

Owner-approved scope refinement: eligible published articles, finite source
families and an internal aggregate outcome report. Deploy the reviewed PHP/config/
Vue sources with the matching client and SSR builds. Refresh normal application
configuration so Privacy 1.3 is displayed. No new environment variable, API secret,
database migration, Ads link or automatic event is needed.

Consent payload 2 renews old acceptance while retaining unexpired rejection in
`clipperdesk:analytics-consent:v1`. The key stays stable deliberately. New rejections keep payload version1 so the previous release also respects them during rollback; expanded acceptance uses version2. Staff and
release testers should choose Reject optional cookies in their normal browser;
use a separate controlled session for the acceptance test and label its date in
report notes. This is not a verified server-wide internal-traffic filter.

`entry_source` is a custom event parameter with six values: `google`, `bing`,
`other_search`, `other_referral`, `direct_or_unknown`, `internal_or_unknown`.
It is derived only after consent and lasts only in the current document, including
Inertia public navigations. It contains no raw URL, query, campaign text or identity.
Google/other-search families are referrer families, not proof of organic traffic;
paid clicks, apps and privacy behavior can make source evidence ambiguous.

After deploying and observing a controlled event in the dedicated property, use
GA4 Admin → Data display → Custom definitions → Create custom dimension:
**Public entry source**, Event scope, event parameter **entry_source**. First
check for an existing matching definition; do not create duplicates. This enables
custom reporting for future processed events, not a historical backfill or repair
of GA4's standard channel grouping. Do not mark source labels, pageviews or trial
activation as new key events or assign monetary values. Preserve the existing
bounded `marketing_cta_clicked` key-event meaning: button click only. Normal
processing/custom-definition reporting may lag; check actual receipt and the
reported definition before treating the report as ready.

Release verification must cover an existing published article, draft/unavailable
article, home→article→product navigation, article trial click→fresh registration,
renewed acceptance, retained rejection, withdrawal, GPC/DNT, blocked Google script,
storage failure and duplicate-click handling. No Google request before consent or
on registration/private routes. Confirm real Google receipt in addition to mocks.
Use realistic mobile/desktop viewports and review the actual notice/actions.
[Implementation evidence and pending checks](../audits/2026-11-15-seo-followup/README.md).

The custom dimension and deployed receipt were not changed/verified in this
implementation chat because browser access became unavailable. The new entry
parameter cannot be analysed in normal GA custom reports until the definition and
new data are available.

### Aggregate registrations and verified trials

Run through authorized operator CLI access on the desired deployed revision:

```sh
php artisan analytics:acquisition-report --from=2026-11-04 --to=2026-11-10 --timezone=Asia/Kolkata
```

The JSON has no account names, contacts or IDs. `owner_registrations` counts saved
owner intents created in the local-date range; `verified_trial_activations` counts
distinct completed verified-owner onboarding records with a saved trial subscription
by completion date. Pending/unverified accounts are not trial activations. Explicit
pitch-demo provenance is excluded; unmarked testing and missing/deleted historical
records remain coverage limits. Never infer test status from names, country or OS.

`trial_cohort_booking_setup_published` is the current published-setup state of
that trial cohort. It is not a first-visit metric, a publication-within-24-hours
metric or proof of a valid future booking slot. Registration and trial counts use
different event dates, so dividing them is not a registration-cohort conversion
rate. No Google visitor/account join or organic attribution is performed. Keep
this internal aggregate report separate from CTA counts and revenue reports.

Rollback: clearing only the existing measurement ID and rebuilding configuration
stops new guest measurement as before. Reverting the code should preserve cookie
rejections and stored account/article history. Do not delete data, change historical
publication timestamps, remove the Google property or weaken private gates.
