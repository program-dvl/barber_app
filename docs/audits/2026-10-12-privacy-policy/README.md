# Clipperdesk privacy and Google OAuth audit

Date: 2026-10-12. Requirements: FR-01, FR-05, FR-09–FR-14, FR-19/20;
PRD privacy, security and accessibility quality attributes. This is a repository
and public-page audit, not legal advice or Google acceptance evidence.

## Outcome and publication boundary

The privacy notice has substantive, present-tense disclosures, the owner-supplied
Clipperdesk identity/contact, a dedicated Google section, international rights,
honest retention limitations, and readable HTML even if the optional Vue SSR
service is unavailable. It uses the existing MarketingLayout/Header/Footer on
interactive visits. A matching server-rendered shell preserves navigation when
JavaScript is unavailable. The long sidebar was replaced at the owner's request
with a compact native disclosure above the centered policy.

The owner explicitly requested removal of the public review banner and supplied:

- Operator name: **Clipperdesk**.
- Postal contact: **111 Galileo Dr, Stoney Creek, ON L8E 0B5, Canada**.
- Support/privacy email: **support@clipperdesk.com**.

These facts are configured defaults, not an assertion of incorporation, company
registration, an appointed DPO, or a statutory representative. No message was sent
to test the mailbox. The final notice's legal approval and effective date were
not supplied. Nothing was deployed, published, submitted to Google, changed in
provider consoles, or altered in the environment's OAuth credentials/scopes.

`privacy.approved` and `privacy.operations_verified` default to false. An approved
notice has an effective date, index/follow directives and a sitemap entry only
when the publication configuration is complete. Without that evidence it remains
noindex; this status is internal and has no public draft banner. Removing a
banner is not evidence that retention, transfer or rights operations work.
`php artisan legal:check-privacy` reports missing facts without printing secrets.

## Why Google may have rejected the old policy

The public endpoints were inspected before changes at approximately 13:41 IST:

| Finding | Evidence and implication |
| --- | --- |
| Apex policy returns 200 | No login redirect observed. HTTP access itself works. |
| www policy redirects to apex with 301 | Existing nginx canonical behavior works; DNS/TLS configuration was not changed. |
| Zero h1, h2 or article elements in the HTTP HTML | Meaningful policy content existed only in Inertia page data until JavaScript rendered it; a non-JavaScript reviewer could see no readable notice. |
| Both HTML robots and X-Robots-Tag specify noindex, follow, noarchive | Deliberate counsel-review indexation state, not a robots.txt block. Google does not state that search indexing itself is mandatory for branding; changing noindex alone does not fix insufficient content. |
| robots.txt permits access | All-agent Allow: /; sitemap advertised. |
| Old brand and internal legal review content | policy.md still referred to Good Hours, a future legal operator, OPEN-10, blocked_policy, named Indian approval owners and a non-effective draft. Terms/refund also had broken “Good Hours”/“Clipperdesk Hours” remnants. |
| No Google Sign-In section | No description of actual scopes, Google data, account linking, transient credentials, retention or revocation. |
| Stale provider statements | Resend/WhatsApp described as delivery providers despite the current SES binding and SMS release scope. |
| Unknown operator and rights contact | No accountable direct request path, until the owner supplied the above details during this task. |
| Excessive future promises and implementation language | No clear separation of present behavior, outstanding destruction policy, or optional payment capabilities. |

Google's exact rejection reason beyond the supplied message is unknown. The
combination of missing Google disclosures, non-effective content and empty
initial rendering plausibly explains insufficient content; it is an inference,
not a finding from Google's internal review.

Google requires the privacy notice to explain access, use, storage and sharing
of Google data and be linked from the homepage and consent screen:
[brand verification requirements](https://developers.google.com/identity/protocols/oauth2/production-readiness/brand-verification).
All Google Sign-In use is subject to the general
[Google API Services User Data Policy](https://developers.google.com/terms/api-services-user-data-policy).
Additional Limited Use scope requirements apply as specified by Google;
openid/profile/email alone are not evidence of restricted-scope access or of a
required annual security assessment. No unverified compliance certificate is claimed.

## Verified Google authentication data flow

Sources: `SocialiteController`, `CompleteGoogleRegistration`, `SocialAccount`,
`User`, social-account migrations, installed Socialite GoogleProvider, and
`GoogleAuthenticationTest`. Installed manifests/lockfiles remain authoritative.

1. Guest-only, throttled GET `/auth/google/redirect`, GET `/auth/callback/google`
   and POST `/auth/google/register` are the only social routes. Generic drivers
   remain disabled. The redirect is stateful and requests select_account.
2. Installed Socialite requests exactly **openid, profile, email**. Redirect
   regression now asserts the actual query scopes, not a duplicated constant.
3. Socialite's userinfo response maps subject, name, email, verification state
   and avatar URL. The controller reads subject/email/name/verification. It
   rejects absent subjects/emails or an unverified email. Picture and other
   raw profile fields are not persisted or displayed by this integration.
4. Existing subject authenticates its linked User. An unlinked subject with an
   exact normalized email links to the matching User only if no conflicting
   Google link exists. Database uniqueness protects subject/user-provider pairs.
5. A new subject enters a ten-minute server-session continuation containing
   subject, name, email and allowed pricing preferences. No User or Business is
   created before business details and legal acceptance are completed.
6. Completion stores name/email on User and provider/subject on SocialAccount.
   The token column is explicitly null. OAuth access/refresh tokens are used
   transiently by Socialite, not retained by the application's account storage.
   A read-only aggregate local-database check found **zero Google links with
   non-empty legacy token values**. No token value or account identity was read
   into the audit output; repeat this check in the production environment.
7. Existing confirmed TOTP challenges remain enforced. Google-created accounts
   can recover password-based access using verified email password reset.
8. There is no public unlink endpoint, complete self-service privacy erasure,
   or Google revocation callback. Revoking through Google does not terminate a
   Clipperdesk session, subscription or local provider link. An affirmative
   later sign-in can reauthorize. The support request path must operate unlink
   and closure safely; the policy does not invent a working unlink button.
9. No Gmail/Drive/Calendar/contact scopes or SDK calls were found. Google-derived
   account name/email can enter ordinary workspace, email and billing flows;
   the Google identifier is not used as Stripe/Twilio metadata. No advertising
   or generalized model-training use is implemented in this sign-in flow.

## Provider inventory and production evidence limits

| Integration | Verified implementation/configuration | What must still be confirmed for production |
| --- | --- | --- |
| Google | Reviewed Socialite integration; local credential presence checked only as booleans | Cloud project consent configuration, exact redirect origins, verified domain ownership, live state/error paths |
| Stripe subscriptions | Business-owned adapter, hosted Checkout/Portal, invoice/payment references and webhook projection | Live account configuration and certified delivery/reconciliation; no live charge made |
| Stripe appointments | PaymentIntent adapter and deposit/payment records | Public payment UI remains OPEN-16; live refunds remain OPEN-13. A local refund record is not a provider transfer. |
| Amazon SES | EmailChannelProvider bound to SesEmailProvider; local account mailer is ses; client email configuration is ses | Account/client sender verification, SES region and provider agreement; mailbox delivery evidence |
| Twilio SMS | Current client_mobile_channels contains sms; local transport_mode live and live_send_enabled true | Actual production sender, registration, consent, delivery and countries. Config flags are not production evidence. |
| Resend | Installed SDK, historical webhook support and configuration; not current client-email binding | Any continued processing of historical messages; do not describe as current without evidence |
| WhatsApp | Deferred adapter/sandbox/configuration remains, excluded by SMS-only release gate | Do not list as an operational product capability |
| AWS/S3 | SES integration and installed AWS SDK; S3 disk template exists | No evidence of S3 as the current file store. Local default/private storage is local; exact host/backup providers and countries unknown. |
| Hosting/monitoring | nginx observed publicly; Sentry package installed, no locally configured DSN | nginx is software, not a verified hosting operator. Confirm production host, database, storage, CDN, monitoring and all subprocessors. |
| Legacy billing/AI/social services | Retained/quarantined code and packages | Do not list disabled Lemon Squeezy, AI or generic social services as operating providers |
| Public analytics | Bounded first-party DOM events without external consumer; first-party onboarding/security records | No third-party tracker, pixel or replay configured by this implementation. Infrastructure/CDN/production-injected code still needs review. |

Public provider categories are disclosed without inventing a production vendor.
Confirmed names and processing locations can be supplied through `config/privacy.php`.
Runtime presence of a secret or SDK never auto-publishes a provider claim.

## Retention, deletion and export findings

- Customer-record export, correction and consent-withdrawal workflows exist with
  permissions, tenant checks and audit records. Deletion/anonymisation produces
  a retained-data preview and blocked_policy; it performs no destruction.
- Privacy export JSON currently omits internal notes/security metadata, provider
  credentials and actual installed checkout financial records (the manifest still
  says checkout ledger not installed). It is not a complete subject-access export.
  The new notice expressly states possible omissions. Export completeness needs
  separate implementation and legal review; it was not silently changed here.
- Private export artifacts are marked retention_until +30 days. No disposal job
  or proof that the marker physically purges files was found. Download links
  expire independently; these facts must not be presented as backup/file deletion.
- Subscription lifecycle cancellation restricts access and records an eligibility
  window using billing.export_days_after_termination (currently 30). It neither
  erases business records nor establishes a complete automatic business export.
- Account login history has a configured 180-day pruning command on the daily
  schedule, clamped by that command to 30–3650 days. The notice displays the same
  configured/clamped value. Scheduler execution in production was not verified.
- Jetstream's legacy DeleteUser action deletes a User/profile/tokens but is not
  a tenant-aware complete privacy executor. SocialAccount foreign keys do not
  cascade in the original migration. It is not safe evidence that Google account,
  business, financial or backup data can be completely erased.
- No approved complete retention schedule, secure manual erasure procedure,
  backup rotation/hold process or verified deletion replay after restoration was
  available. Do not mark OPEN-10 closed or promise immediate/permanent erasure.

## International legal review checklist

The notice identifies controller/service-provider roles, purpose-linked EU/UK
legal bases, rights requests and verification, business-controlled records,
children/guardian responsibilities and jurisdiction-dependent complaints.
It does not claim universal compliance, a fabricated DPO/representative,
statutory deadline, transfer contract or adequacy certification.

Before the relevant market is served, reviewers must approve:

- **Canada:** operating entity/registration facts, accountable privacy contact,
  PIPEDA applicability, provincial requirements (including Quebec if targeted),
  overseas processing, consent, sensitive information and complaints.
  [OPC PIPEDA guidance](https://www.priv.gc.ca/en/privacy-topics/privacy-laws-in-canada/the-personal-information-protection-and-electronic-documents-act-pipeda/).
- **EU/UK:** territorial applicability, detailed controller/processor allocation,
  processing agreement, Article 13/14 notices, purpose/ground mapping, special
  category conditions, representative/DPO necessity, supervisory authority,
  rights handling and actual transfer safeguards/assessments. A statement that a
  lawful mechanism is needed is not proof that an SCC/IDTA exists.
  [ICO roles guidance](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/controllers-and-processors/controllers-and-processors/),
  [ICO transfer guidance](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/international-transfers/a-brief-guide-to-international-transfers/).
- **US:** state-by-state applicability and thresholds, sensitive information,
  agent/appeal workflows, opt-outs and universal signals if practices change,
  state disclosures for the preceding twelve months where required, and
  COPPA/child-data analysis. No sale/sharing functionality was added.
  [California AG privacy rights](https://oag.ca.gov/privacy/ccpa).
- **India:** assess actual territorial reach and staged commencement of the Act
  and 2025 Rules, consent/notices, children, grievance and nomination obligations
  where operative. Do not rely on older India-first project notes to assert every
  provision is already operative for this Canadian operator.
  [MeitY rules source](https://www.meity.gov.in/documents/act-and-policies/digital-personal-data-protection-rules-2025).

## Outstanding approvals and operational work

| Required evidence | Accountable reviewer | Status |
| --- | --- | --- |
| Operator/physical contact/email | Business owner | Supplied during this task; incorporation and mailbox operation not independently certified |
| Final text/version and effective date | Business owner and appropriate legal/privacy counsel | Not supplied |
| Production provider names, regions, countries, DPAs and transfers | Operations and privacy/legal | Not supplied; configure only verified facts |
| Monitored privacy intake, identity verification, request register, responses, appeals and complaints | Operations and privacy/legal | Contact supplied; handling rehearsal not performed |
| Safe Google unlink/account closure and legally required deletion capability | Engineering and privacy/legal | Incomplete; do not use ad hoc destructive database edits |
| Record-specific retention, backups/legal holds and tested disposal/restore | Engineering, Operations and privacy/legal | OPEN-10 retained |
| Complete subject-access/business export | Engineering and privacy/legal | Existing exports are partial; manual assessment needed |
| Terms and Refund final legal/financial approval | Legal, Finance and Operations | Narrow identity/provider/branding corrections made; no invented refund commitment or effective contract |
| First-country launch and children/sensitive-data rules | Product and privacy/legal | OPEN-15 retained |

## Changes and technical decisions

- `resources/markdown/policy.md`: substantive notice grounded in the inspected
  flows; Google permissions, retention, disclosure and deletion limits.
- `PrivacyNotice` and `config/privacy.php`: source-derived anchors, numeric
  configured periods, escaped contact values and explicit publication readiness.
- `LegalDocumentController`, `PrivacyPolicy.vue`, `legal/*.blade.php`: existing
  interactive public layout plus full Blade fallback inside the Inertia root;
  no dependence on running an SSR daemon for readable initial policy HTML. Incoming
  Inertia links use a standard full visit so server-owned metadata is refreshed.
  The fallback mirrors header/footer design and supports a native mobile menu.
- `privacy.css`/`app.css`: scoped legal typography and compact native contents
  disclosure; no authenticated theme changes.
- `PublicIndexation`, `SitemapController` and SEO partial: one approval-aware
  indexation rule; query-free canonical, policy-owned title/site/locale and
  www-alias redirect. Other route families retain existing behavior.
- Shared Header/Footer use the existing configured identity; only the privacy
  page uses Clipperdesk casing and omits the footer's legacy preview line.
  `AuthLayout` adds real policy/terms links to sign-in as well as registration.
- Terms/refund contact placeholders use the same approved facts with escaped
  substitution. Their review versions/dates are updated; contract approval is
  separate. Company/Security contact/provider/authentication claims corrected.
- No authentication controller behavior, credentials, scopes, schema, provider
  configuration, deletion behavior or unrelated operational module changed.

## Verification

The evidence file is `verification.md`. Screenshots are desktop.jpg,
mobile.jpg, contents-desktop.jpg and google-disclosure.jpg. Responsive measurements
are in responsive-checks.json. Local browser verification is not an independent
WCAG audit, multi-browser certification, provider certification or production
release approval.

## Google resubmission checklist

1. Approve the exact notice version, effective date, provider inventory, countries,
   transfer arrangements and operational rights/retention review. Complete the
   remaining applicable legal items above; record a review reference.
2. Set LEGAL_PRIVACY_APPROVED and LEGAL_PRIVACY_OPERATIONS_VERIFIED only when
   justified; fill the effective date, approval reference and provider/location
   fields. Ensure BRAND_WEBSITE_URL is https://clipperdesk.com. Run
   `php artisan legal:check-privacy` and require a successful result.
3. After explicit release authorization, deploy source and client/SSR assets;
   refresh the deployed config/view caches. No deployment occurred in this task.
4. Request both policy hostnames anonymously. Require www -> apex 301 and apex
   200, exactly one canonical https://clipperdesk.com/privacy-policy, meaningful
   article/headings in HTML with JavaScript disabled, and no noindex/nofollow or
   blocking robots/cookie/login behavior. Confirm footer/login/signup links.
5. Confirm the production homepage describes Clipperdesk and links the same
   policy. In Google Auth Platform, verify clipperdesk.com ownership through the
   associated Search Console account; align app name, homepage, privacy URL,
   support email and authorized domains. Keep the current redirect credentials.
6. Compare the actual authorization request with declared Data Access scopes:
   openid/profile/email only for this integration. Test sign-in, signup, account
   conflict, denied consent and invalid/expired OAuth state without logging
   provider credentials or personal records.
7. Resubmit branding with the corrected canonical privacy URL and a brief
   explanation of the added Google disclosures and server-readable HTML.
   Address any independent Google feedback. Do not represent acceptance as
   guaranteed or remove retained platform launch gates.
