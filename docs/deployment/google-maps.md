# Google Maps deployment and operation

Requirements: FR-02/03/09/13/19; ADR-064.
Implementation contract: [Locations](../modules/location-intelligence.md).

## Google Cloud setup (manual)

1. Use separate Google Cloud projects/browser keys for production and staging,
   attach billing and enable **Maps JavaScript API** and **Places API (New)**.
   No Routes, Geocoding, Time Zone, Static Maps, Embed API or server-side Places
   service is required by this release.
2. Create a JavaScript **Map ID** for each environment. It is public configuration,
   not a secret. `DEMO_MAP_ID` is the local development fallback; use a project-owned
   Map ID for staging/production. Do not request reviews/photos/hours/contact data.
3. Restrict each key to **Websites (HTTP referrers)** and restrict its API access to
   Maps JavaScript API and Places API (New). Never use an unrestricted key or a
   server credential under `VITE_` names. This integration has no server key.
4. Allow only actually deployed origins, for example production:
   `https://clipperdesk.com/*`, `https://www.clipperdesk.com/*` if used,
   `https://app.clipperdesk.com/*` if used, and `https://book.clipperdesk.com/*`.
   The main-domain fallback `/book/{slug}` needs its host authorized too. Staging
   uses its exact dashboard/main/booking hosts on its own key. Avoid broad `*` or
   unrelated subdomain access. Do not add localhost to the production key.
5. A development key can allow `http://127.0.0.1:8027/*` and the exact other local
   ports actually used. `localhost` and `127.0.0.1` are different referrers. The
   8027 referrer initially failed and worked after the owner changed Cloud setup;
   no credential restriction was weakened by application code.
6. Set budgets and billing alerts (e.g. 50/80/100% of an agreed spend), per-API quota
   alerts and anomaly monitoring. Choose quotas above forecast peak concurrent
   onboarding/booking traffic, rather than a restrictive low cap that breaks
   customers. Start from Cloud Console's available quotas/SKUs and staging load
   evidence; the correct business-specific numbers are not guessed here. A budget
   alert does not itself stop spend. Review SKU usage and rejected requests after
   rollout, especially Places selection failures and Dynamic Maps loads.

## Environment variables and build

```dotenv
# Public, website/API-restricted browser key. Already supplied by the owner.
VITE_GOOGLE_MAPS_API_KEY=
# Your Google Cloud JavaScript Map ID.
VITE_GOOGLE_MAPS_MAP_ID=
```

These are Vite build-time values. Set the target environment's values before
`npm run build`, then deploy its matching client and SSR artifacts. Changing
server environment values alone will not change an already-built browser bundle.
Do not print keys in logs, shell output, screenshots or support exports. Browser
keys are public by design; restrictions enforce their intended use. No Google
Cloud service-account JSON, private key or backend secret belongs in this bundle.
The repository's Node requirement is >=22.12.0; verified locally on 24.19.0.

If your deployment sets a CSP, permit Google's documented Maps scripts, styles,
fonts, images and connections on the actual pages using Maps. Use nonce propagation
(the loader retains the page nonce); do not broadly disable CSP or guess wildcard
policy additions. Follow the [Google CSP guide](https://developers.google.com/maps/documentation/javascript/content-security-policy).
Retain native Google widget/map attribution, terms links and provider notices.

## Deploy and verify

1. Back up locations and appointment data; rehearse the migration in staging.
2. Apply `2026_10_12_000002_add_location_intelligence.php` before deploying source
   that reads the new columns. It adds 12 location columns and one nullable JSON
   appointment column. Legacy address/coordinates/assignments are untouched.
   A 500-row chunk backfill records current assigned addresses, not invented
   historical values. For a large appointment table, allow a maintenance window
   and measure staging duration. Do not run a second writer during a partial
   schema/backfill operation; inspect and recover a failed migration deliberately.
3. Deploy built assets, refresh Laravel configuration and restart long-lived
   workers using the existing deployment procedure. Keep the scheduler/notification
   configuration and subscription rules unchanged.
4. Exercise address and business search with keyboard and touch; manually enter
   an unlisted/incomplete regional address; set/remove an entrance; save/reopen;
   edit another branch; verify hours and existing pins remain unchanged until
   deliberately edited. Timezone remains an explicit owner choice.
5. Verify one-branch automatic selection and multi-branch service/staff filtering,
   optional nearby sorting with both permission grant and denial, booking, reminder,
   reschedule and calendar links. A test booking must not send real messages.
6. Verify DNS/TLS/proxy rules for the existing primary booking host and its
   fallback separately using [public-booking deployment](public-booking.md).
   Real production/staging key restrictions, Cloud quotas, browsers/devices and
   provider delivery still need environment-specific certification.

## Failure and rollback

Script/network/restriction failures keep manual address entry usable. Widget
errors show a calm fallback for Places/quota failures. Map authentication failure
hides the preview; address and navigation remain usable. Google calls occur only
in browser search/map interactions, never notifications or booking commits.
If Google fails broadly, deploy with an empty browser key to leave manual entry
and navigation available, then rebuild to restore it after recovery.

Prefer rolling source/assets back while **keeping the additive columns and
snapshots**. The previous release ignores them. Dropping them destroys saved
entrance/arrival/history information; export them and decide explicitly before
using this migration's `down()`. Never roll back the whole database or an unrelated
migration batch. Previously queued notifications retain their own captured content.
New intents add location details only after deploying this change; provider retries
and consent/quiet-hour controls are unchanged.

## Storage, privacy and future work

Places results and provider geometry stay transient; Place IDs and owner-provided
location records are the durable boundary. Explicit owner address review and pin
positioning are required to treat the saved records as customer operational data,
not a cached Google POI database. The application does not cache provider geometry
for 30 days and therefore needs no expiry/refresh job for this release. Existing
legacy `latitude`/`longitude` columns are left unchanged and are not used for new
navigation, snapshots or nearby ordering because their provenance is unknown.

Review the applicable [Places policies](https://developers.google.com/maps/documentation/places/web-service/policies),
[service-specific terms](https://cloud.google.com/maps-platform/terms/maps-service-terms),
[Maps JavaScript policies](https://developers.google.com/maps/documentation/javascript/policies),
and EEA billing-address differences with the accountable legal owner before
production. The address-autocomplete exception is narrow; it does not authorize
bulk storage of suggestions or provider geometry. Do not assume confirmation
alone grants indefinite rights to arbitrary Google content. Public Terms and
Privacy v1.1 (updated 2026-10-13) now describe Maps and optional geolocation.
Review this revised version before setting the existing legal publication
approval flags; this implementation supplies no legal sign-off.

Browser customer geolocation is not sent to Clipperdesk or persisted. Google
receives search/map requests when used; external directions use the destination
and the chosen provider's privacy rules. No personal customer location is embedded
in a navigation URL. Apple Maps links use owner-provided pins, not Google geometry.

Optional future work: approved relocation/amendment workflow for already-booked
visits, travel-time estimates, branch availability comparison and recommendations.
These require separate scope/evidence. Nearby sorting already uses known branch
pins and needs no paid routing dependency. WhatsApp remains out of the SMS-only
release. No Stripe commercial or subscription behavior changed.

Local preview note: port 8027 runs the existing
`clipperdesk-pricing-preview-v2.sqlite` database, not the normal MySQL `barber_app`
database. Both received the migration after separate private backups. The public
`juniper-house-location-review` tenant in that SQLite preview is synthetic QA data
with fictional addresses and separate branch staff; it is not a published customer
business or a production provider account. No customer was booked or notified in
browser testing. The isolated 8041 review server was stopped after browser access
was declined. Do not deploy or import preview databases as production customer data.
