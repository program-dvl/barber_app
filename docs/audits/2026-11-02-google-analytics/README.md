# Optional Google Analytics setup — 2026-11-02

Scope: owner-requested Google Analytics setup for ClipperDesk, PRD sections
4/12/15, FR-01/19, ADR-079. This record separates implementation from external
setup. **Enabled and verified on production revision 981dd17:** sanitized public
pageviews and the trial-click event reached Google, and signup opened a fresh,
responsive document without Google tracking.

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
  Two-month Google retention and its disabled activity reset were subsequently
  saved and read back, as recorded below.

## Local verification

- Final Marketing backend suite: **153 passed / 3,503 assertions** in the current
  workspace, including the new PublicAnalytics boundary and privacy disclosure
  cases. The workspace also contains another chat's sitemap tests/edits.
- Frontend: **215 passed**, including **25 analytics tests** for consent,
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

## Earlier continuation checkpoint — completed

At that checkpoint the remaining steps were to restore browser access, read the
existing resource IDs, confirm the privacy settings, add only the measurement ID
in Forge and verify deployed delivery. The subsequent sections record their
completion; no duplicate Google account or stream was created. See
[deployment and recovery](../../deployment/google-analytics.md).

Local screenshots, mocked tags and passing builds were not treated as provider
receipt. Consent UI and live delivery do not establish regional legal/transfer
approval or waive other launch controls. The concurrent sitemap work remained
separate from this implementation.

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

## Production verification and temporary rollback

- Owner deployed revision **467634e**, containing Analytics commit **cb8b77d**;
  Forge showed Deployed from master. The agent's push had been rejected by
  automatic review and was never performed. Owner deployment is independently
  observed in Forge, rather than inferred from the owner's message alone.
- Actual production guest test: zero Google requests before consent; zero after
  rejection. Acceptance loaded the tag (200) and sent sanitized page_view to the
  intended stream (204). Inertia pricing navigation added exactly one pageview;
  user-selected annual pricing sent marketing_pricing_interval_changed (204).
- A fresh pricing visit with a synthetic email-like query and fragment sent only
  `https://clipperdesk.com/pricing`, fixed title ClipperDesk and no referrer. No
  test query/fragment marker appeared in the collection query or POST body.
- Realtime showed one active test visitor, two pageviews and the pricing event.
  This establishes Google receipt for those paths, not a signup or paid conversion.
- Actual withdrawal reloaded with zero Google scripts and zero subsequent Google
  requests. Phone 390×844 choices wrapped without horizontal overflow and had
  approximately 44px touch height. Registration was a separate tracker-free page.
- Ordinary trial events initially did not appear. Marked **marketing_cta_clicked**
  as a key event using Create with code, no default monetary value, once per event.
  This is a button click, not qualified signup/revenue. Added no automatic event
  rules, Ads links or financial values. Google documents immediate key-event
  transmission rather than ordinary batching.
- Real Google processing exposed unsafe loaded-queue truncation the stub missed:
  a trial-click request included the CTA in its POST batch while browser automation
  observed a navigation/inspection stall. Mutating Google's live command array
  during its processing callback was unsafe and was corrected. Later tool
  timeouts were resolved using native accessibility inspection, which confirmed
  successful signup navigation; those timeouts alone do not prove a persistent
  customer renderer freeze.
- Corrected disable() to preserve Google's array after load while retaining
  ga-disable, consent withdrawal, expiry and fresh-document private navigation.
  A cursor-draining callback regression fails under the old truncation and passes
  under the correction. All 215 frontend tests/25 analytics, client/SSR builds and
  21 budgets pass. Deployment was pending at this historical checkpoint.
- To protect new signup visits pending correction, cleared **only the value of
  our newly added GOOGLE_ANALYTICS_MEASUREMENT_ID** in Forge. Before saving,
  copied-text equality confirmed the rest unchanged. A new actual production
  homepage exposes no Analytics preferences/config and trial navigation remains
  available. Existing unrelated keys and Google resources are preserved.
- An independent local harness then used the actual current controller, consent
  component and **real Google tag**, forwarding its real command layer and
  declaring only sanitized public test pages. The corrected trial click emitted
  marketing_cta_clicked and completed a new local /register document in **4ms**;
  the destination had no tag and remained responsive. No registration or payment
  was submitted. This is stronger than the mock but is not fixed-production proof.

Evidence: [live desktop consent](live-consent-desktop.jpg),
[live phone consent](live-consent-mobile.jpg),
[real-tag local signup](real-tag-signup-local.jpg),
[account sharing off](google-account-sharing.jpg).

## Final production verification — 981dd17

- Forge independently confirmed revision **981dd17** deployed. Restored only
  `GOOGLE_ANALYTICS_MEASUREMENT_ID=G-G14K5KCP1X` using targeted Find in the existing
  environment editor. No other environment value was changed and no duplicate
  Google resources were created. Collection is enabled with prior opt-in.
- On the actual production pricing page, the page_view request used the intended
  stream and sanitized public location. A trial click sent
  **marketing_cta_clicked with HTTP 204**, then opened a full **/register**
  document displaying **Create your account**. The destination was responsive,
  contained **zero Google tags** and sent **no further Google requests**.
- At **390×844**, the actual production trial action also opened the signup form
  in a fresh tracker-free document. Mobile network capture did not follow the new
  renderer, so this is navigation/exclusion evidence; it is **not a separate
  mobile HTTP 204 observation**. The desktop network result above establishes
  the observed trial-event delivery.
- Both live legal pages were checked at **1440×900 and 390×844**: no obsolete
  review/draft notice, no Terms review-status field and no horizontal overflow.
  Terms showed version 1.0 and last updated/effective 2 November 2026; Privacy
  showed version 1.2 and the same public date. See the
  [legal publication evidence](../2026-11-02-legal-publication/README.md).
- Final withdrawal recheck on revision **981dd17** passed in a fresh Chrome tab:
  accepting through the actual UI loaded one Google script; reopening preferences
  and rejecting caused a full-document reload with **zero Google scripts and zero
  Google requests**. Clicking Pricing afterward opened the production pricing
  page with **zero Google scripts and zero Google requests**. Network capture
  followed this check without cursor truncation. Earlier withdrawal evidence is
  retained as chronological context.

[Corrected production signup](live-fixed-signup.jpg). No registration or payment
was submitted. Public legal presentation does not change operational approval,
indexation, sitemap or other release gates. Temporary local verification servers
were terminated after the checks.

## Received event report

The later Realtime snapshot shows 3 test browser sessions, 8 pageviews,
4 marketing_cta_clicked events, 1 pricing interval event and 1 trial-click key event.
These include deliberate production and local real-tag checks; they are not
customer acquisition, completed registrations or revenue. [Realtime screenshot](google-realtime.jpg)
and [safe verification summary](verification.json) omit browser identifiers,
credentials and raw collection payloads. The corrected production trial
navigation and HTTP 204 delivery are separately recorded above.
