# Public booking deployment and verification

Requirements: FR-01/03/09/19/20; ADR-055.
This is an operator runbook. DNS, Forge and production certificates have **not**
been changed or verified by this repository task.

## Application configuration

```dotenv
APP_URL=https://clipperdesk.com
BOOKING_HOST=book.clipperdesk.com
BOOKING_SCHEME=https
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
# Leave SESSION_DOMAIN unset for host-only cookies.
```

`BOOKING_HOST` is a host (optionally with a development port), not a URL or path.
All promoted links use `BookingUrl`. Both public entry points use the same
controller/component/domain services. APIs and public media are requested on the
page's own origin. Laravel web middleware protects mutations; do not exclude
booking endpoints from CSRF or add permissive CORS to solve routing issues.
Host-only cookies avoid sharing owner sessions unnecessarily between origins.
The default database session driver is supported, as is the configured existing
driver; storage/cache/queue infrastructure must be shared by deployed workers.

Development example: `APP_URL=http://127.0.0.1:8016`,
`BOOKING_HOST=book.localhost:8016`, `BOOKING_SCHEME=http`,
`SESSION_SECURE_COOKIE=false`. With Laravel's server on port 8016, open
`http://book.localhost:8016/urban-studio` or
`http://127.0.0.1:8016/book/urban-studio`. For Valet/custom test domains, add a
DNS/hosts mapping for the configured booking host. Staging follows the same
pattern with its own host and certificate.

## DNS, Forge and TLS

1. At the DNS provider, point `book` to the same application origin: use A/AAAA
   records matching the origin IPs, or a CNAME to the application's supported
   DNS hostname. Do not publish a stale IPv6 destination. Verify resolution from
   outside the server. Only enable a CDN/proxy after origin HTTPS works.
2. In the existing Forge site, add `book.clipperdesk.com` as an additional domain
   served by the same release and `public` directory. Depending on Forge's current
   interface, use its Domains controls. A separate vhost may point to the same
   release if aliases are unsuitable. Do not create a second app/database or
   configure a domain/path redirect to the main domain.
3. Issue/activate a valid certificate covering both main and booking domains,
   either per-domain certificates or a SAN certificate. Check automatic renewal
   for each domain and the actual served chain. HTTP-to-HTTPS redirects are
   appropriate; `/book/{current-slug}` on the main HTTPS domain must remain 200.
4. Check the effective Nginx `server_name`, shared document root, Laravel front
   controller fallback and any proxy redirect rules. Configure trusted proxies
   with the deployment's real proxy addresses and forwarded HTTPS information;
   never blindly trust untrusted host/forwarded headers. Restrict accepted hosts
   at the edge to the configured application and booking hosts.
5. Exclude booking/API/secure-link paths from shared HTML or response caching.
   These responses use private no-store headers. Static build assets may use the
   existing fingerprinted asset caching. Invalidate previous cached booking pages.

Official references: [Laravel routing](https://laravel.com/framework/docs/13.x/routing),
[Laravel request-forgery protection](https://laravel.com/framework/docs/13.x/csrf),
[Forge domain/certificate architecture](https://laravel.com/blog/everything-you-need-to-know-about-the-new-forge-laravel-vps).

## Migration and release

Back up the production database, deploy the reviewed release, install locked
production dependencies and build client/SSR assets with supported Node
(`package.json` requires >=22.12.0; validation used Node 24). Run:

```sh
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Run the existing worker/SSR/PHP-FPM restart commands for the site. Deploy new
booking configuration with route/config caches together. Verify cache generation
in staging first.

The additive slug reservation migration backfills current links and aliases in
one namespace. It fails on existing cross-business collisions rather than
silently moving a link. Resolve ownership with the affected businesses and a
reviewed database repair, then rerun; table creation is resumable on MySQL.
Inspect legacy slugs that conflict with reserved system paths before promoting
subdomain links, and assign an available readable slug through the normal setup
workflow. The migration also removes accidentally retained raw flow secrets
from historical flow state; the existing digest remains valid. Rollback drops
only the reservation table and does not restore secrets. Do not revert code
without considering the already-published primary URLs.

### Last onboarding step returns 500 after a source update

If the completion request logs `booking_slug_reservations doesn't exist`, check
`php artisan migrate:status`. Readable starter links require
`2026_10_10_000001_reserve_public_booking_slugs`; a successful build does not apply
database migrations. Back up the affected database before applying the pending
migration. For a local checkout with only this migration pending, run:

```sh
php artisan migrate --path=database/migrations/2026_10_10_000001_reserve_public_booking_slugs.php --force
```

Confirm it is recorded as applied, return to Business setup, and retry Prepare
my workspace. Saved answers survive the failed starter transaction; completion
reuses them without duplicate starter records and keeps publication private.
Do not skip slug reservation or recreate the account to work around this error.
Existing ownership collisions still require the reviewed recovery above.

## Release acceptance checks

Use a published active staging fixture and synthetic contacts; do not create
appointments in a real business merely to smoke-test deployment.

- Open and refresh both `https://book.<environment>/{slug}` and main-domain
  `/book/{slug}`. Both must render without authentication; current fallback
  remains 200 and canonical metadata equals the primary URL.
- On each origin, select service/staff/date, see skeleton loading and available
  location-local times, hold, review details and confirm a no-deposit appointment.
  Check same-origin requests, cookies, no CSRF/CORS errors, secure links and assets.
- Refresh the hold/confirmation; go back/change time; verify one appointment on
  retries and the correct staff, services, price, duration and time zone.
- Confirm inactive/unpublished/invalid slugs expose no catalog. Change a fixture
  slug; old links redirect to its primary current link. Another business cannot
  claim current or historical names, including simultaneous writes.
- Verify setup preview/copy, services shortcut, publication notification and
  rebook destinations promote only the primary URL. The fallback is unpromoted.
- Exercise conflicting slots, cutoff, staff leave/breaks, required preferences,
  first-visit restrictions and client-specific deposits. Required deposits must
  not confirm unpaid. OPEN-16 still blocks production paid self-service.
- Inspect HTTP/TLS from outside the network, certificate renewal, worker delivery,
  no-store responses and shared-cache bypass. Confirm Laravel route/config cache
  output uses the environment's booking host.

Local automated MySQL races are evidence for transaction behavior, not a
certificate of production database topology, proxy behavior or provider delivery.

Maps keys must authorize the dashboard, main-domain fallback and booking-host origins. Follow [Google Maps deployment](google-maps.md), including the additive location/snapshot migration. Successful booking pages permit same-origin geolocation only after the customer explicitly requests it.
