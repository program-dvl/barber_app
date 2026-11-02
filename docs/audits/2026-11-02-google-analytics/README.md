# Optional Google Analytics setup — 2026-11-02

Scope: owner-requested Google Analytics setup for ClipperDesk, PRD sections
4/12/15, FR-01/19, ADR-079. This record separates implementation from external
setup. **Google collection is not live or independently verified.**

## Implemented locally

- One server-only environment setting, `GOOGLE_ANALYTICS_MEASUREMENT_ID`.
  No package, Google API secret, Tag Manager container or credential is needed.
- Production-only exact primary HTTPS origin, guest and explicit curated public
  route/path gates. Auth, registration, Help, booking, private links, app,
  arbitrary article slugs, errors and signed-in visits expose no tracker config.
- Prior opt-in, equal Accept analytics / Reject analytics actions, footer
  preferences/withdrawal, 180-day versioned choice and GPC/DNT override.
  Storage errors fail closed. Shared AppNotice/tokens and focus restoration.
- Separate Google command layer; advertising consent denied, Signals and ads
  personalization off, sanitized manual pageviews, bounded CTA/interval fields,
  host-only `cd_ga…` cookies capped at 60 days without activity refresh.
- Suspended/failed/private navigation stops measurement. Private destinations
  use a new document after a tag has loaded. Script failure/timeout never
  retries automatically and keeps collection disabled across navigation.
- Privacy v1.2 discloses purpose, fields, choice, limitations and Google links.
  Existing legal environment flags, credentials and operational records unchanged.
  The intended two-month Google retention setting remains an external setup step.

## Local verification

- Marketing backend suite: **146 passed / 3,455 assertions** in the current
  workspace, including the new PublicAnalytics boundary and privacy disclosure
  cases. The workspace also contains another chat's sitemap tests/edits.
- Frontend: **213 passed**, including **23 analytics tests** for consent,
  retention/expiry, privacy signals, bounded fields, route/race handling,
  withdrawal, script errors/timeouts, storage failure and private transitions.
- Production client and SSR builds pass with Node 24.19.0. Public budgets pass
  for **21 routes**. Scoped Pint and `git diff --check` pass. Existing build
  warnings concern DaisyUI `@property` and Manrope TTF runtime resolution.
- Real Chrome local harness imports the actual consent component, shared styles
  and analytics controller. Its injected browser boundary intercepts the Google
  tag; **no request goes to Google**. It is component/controller evidence, not a
  complete deployed website or provider test.
- At desktop 1512×700 and phone 390×844: initial consent has zero scripts/events;
  rejection has zero scripts/events; footer reopening permits acceptance;
  simulated tag readiness emits one sanitized pageview, and pricing navigation
  adds one. Withdrawal saves rejection and reloads. Phone accept target is
  152×44px, actions wrap and the page has no horizontal overflow.
- Phone storage failure says analytics is off and browsing can continue. Browser
  privacy mode offers no acceptance action. Simulated tag failure leaves pricing
  usable with one failed attempt, zero events and collection disabled. The browser
  pass caught and fixed re-enabling the disable flag after a failed-tag visit.
- Sign-in navigation after a loaded synthetic tag opens a fresh excluded document.

Evidence: [desktop consent](consent-desktop.jpg), [phone consent](consent-mobile.jpg),
[tag failure](tag-failure-mobile.jpg), [storage failure](storage-failure-mobile.jpg),
[browser privacy signal](privacy-signal-mobile.jpg).

## Google and Forge observations

- Signed-in Google initially opened **QRxpress**, property `532462876`, stream
  measurement ID `G-HT37ER6T5E`. It is unrelated and was not changed or reused.
- Prepared a separate new **ClipperDesk** account and **ClipperDesk — Website**
  property, USD reporting, India reporting time zone, Computers & Electronics,
  small business, traffic and engagement objectives. Optional account data
  sharing is unchecked. These were the initial prepared form values; creation was subsequently completed as recorded below.
- Creation initially reached Google's Terms of Service dialog. Google defaulted
  to UK; automatic review required direct confirmation before selecting Canada.
  The owner explicitly replied **Yes—Canada; accept both terms**. Selected Canada,
  accepted the Data Processing Terms and Analytics terms, and completed creation.
  Google showed all four creation stages completed, then explicitly confirmed
  **You successfully created your account and property**.
- [Initial terms screen](google-terms-pending.jpg) records the earlier approval
  checkpoint. It does not depict the completed account.
- Inspected Forge site `3340514`, server `1241533`, `clipperdesk.com`, repository
  `program-dvl/barber_app:master`. Latest deployed revision was `f777e42`, matching
  local HEAD at task start. No deployment, environment save, command, scheduler,
  worker or existing value was changed.
- An unrelated Stripe Directory CLI lookup was rejected by automatic approval
  review due to protected Stripe configuration access. It was abandoned; Google
  documentation and the authorized browser provided the working path.

## 2026-11-02 approved continuation

- Created one **ClipperDesk public website** stream at `https://clipperdesk.com`,
  stream ID **16253588035**. Explicitly turned off Enhanced measurement and
  confirmed Turn off in Google's confirmation dialog before creation.
- Google showed the stream row and its No data received status, then the account/
  property creation success screen. The wizard URL still contained the prior
  account/property context, so it is not evidence of the new resource IDs.
- Browser access then failed with **Local browser use is disabled in Work
  settings**. A fresh surface inventory also returned zero browsers and that
  error. No alternative browser mechanism or protected-state access was used.
- No measurement ID, account/property numeric ID, two-month retention readback,
  activity-reset setting, Signals/user-provided-data setting or real Google
  receipt was obtained. **No Forge environment or deployment changed.**
- Final code review removed initial stream-scoped page defaults so automatic
  events can follow the current sanitized public page, and added bounded consent
  expiry checks that disable/unload a still-open tag when consent expires.
  The Google script request itself uses no-referrer. These have local regression
  evidence, not a live Google delivery certification.

## Required continuation

Restore authorized browser access and use Continue to Home on the existing
created account success screen. Read the new account/property/measurement IDs;
**do not create another account or stream**. Verify Enhanced measurement remains
off, disable ads/signals/user-provided data, and set two-month user/event
retention with activity reset off. Append only the measurement ID to Forge,
verify preservation, deploy only the analytics files and check real network plus
the new property's Realtime receipt. Verify CTA delivery before navigation and
delayed automatic engagement carry only the sanitized current public page. See [deployment and recovery](../../deployment/google-analytics.md).

No local screenshot, mocked tag or passing build closes that live evidence gap.
No consent banner test grants regional legal/transfer approval or waives other
launch controls. Unrelated concurrent sitemap edits must remain outside this
change's deployment.

## Browser recovery and saved configuration

- Browser access recovered on the next continuation. The existing success link
  led to account **414302511**, property **561865152**. No duplicate resources
  were created. Stream **16253588035** uses **G-G14K5KCP1X**.
- Verified Enhanced measurement off and zero connected tags in stream details.
  Signals and user-provided data show Turn on, so remain disabled. Disabled
  granular location/device collection and ads personalization for all regions;
  saved readback confirms **0 out of 307 allowed** for personalization.
- Saved event and user retention at **2 months**, activity reset off. Google says
  retention-setting changes take effect after 24 hours; aggregate reporting differs.
- Forge environment editor was unlocked with Reveal. Before saving, copied text
  comparison confirmed all existing content preserved with only the appended
  `GOOGLE_ANALYTICS_MEASUREMENT_ID=G-G14K5KCP1X` line. Forge reported Environment
  settings updated; reload/copy confirms production env and exactly one matching
  Analytics ID. Forge normalized the trailing newline, so a byte-for-byte full
  readback comparison was false; no value change was indicated. No credential
  values were persisted in evidence, and copied credentials were cleared.
- Trial-click delivery now uses the documented processing callback with an
  independent **200ms maximum** before full signup navigation. Callback/timeout,
  double click, withdrawal, competing navigation and native modified-click
  regressions pass. Processing is not proof of Google receipt.

[Stream](google-stream.jpg), [retention](google-retention.jpg),
[personalization disabled](google-personalization-off.jpg).
