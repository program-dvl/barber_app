# Internal administrator login

ClipperDesk internal administrators sign in at `/admin/login`. Salon users use
`/login`. The separately implemented `/platform` support workspace does not
replace the internal administration login.

Requirements: [FR-19 and FR-20](../product-requirements.md#fr-20-platform-super-admin-and-support-operations).
Authentication follows ADR-059 in [the decision log](../decisions.md).

`php artisan make:admin` creates a User and a separate active administrator role.
It does not create a Business or membership or verify an email address.
Use its interactive password prompt for new accounts.

## First sign-in

1. Open `/admin/login` and enter the account's existing email and password.
2. If the email is unverified, the panel opens `/admin/email-verification/prompt`.
   Select **Send verification email**, then open the signed link in the same browser.
3. After verification, the panel opens `/admin` directly. Later sign-ins require
   the account's email and password; there is no 2FA setup or challenge.

Existing Fortify authenticator secrets and recovery codes are preserved for other
login interfaces. The separate `/platform` support workspace retains its confirmed
MFA requirement. Old `/admin/multi-factor-authentication/set-up` links redirect to
`/admin`. No database migration or administrator-account recreation is needed.

## Access and recovery

Only an active, unexpired, unrevoked platform administrator role permits panel
identity admission. Unverified identities can reach only email verification and
logout; operational pages and Livewire updates remain gated on verified email.
The admin login clears stale intended salon redirects after successful sign-in.
A salon session can view `/admin/login` without receiving administrator access.

Email links are signed, expiring, user-bound, and return to the admin panel.
Sending uses the existing security mail stream and must be configured in the
running environment. Confirm `APP_URL` matches that environment's public origin.
The tests use fake mail; they do not certify real SES delivery.

The panel retains 15-minute idle and eight-hour absolute platform-session limits;
expiry signs the operator out and returns to `/admin/login`. Ordinary tenant data
still requires Membership or approved support access. Legacy identity, role and
billing resources remain quarantined under ADR-023.

A generic credentials error now means invalid credentials or missing/inactive
administrator role, rather than unfinished email verification. Confirm that the
account exists in the database of the exact environment being accessed. Do not
recreate an existing production account or reset its password to install this fix.

## Why the menu is limited

The panel currently exposes Dashboard and four Content resources. Users, Roles,
Permissions and legacy provider billing resources are explicitly disabled for
every administrator under ADR-023/029; assigning a global boilerplate role does
not enable them. Dashboard widgets are not registered. Business-aware support
tools are implemented separately under `/platform`, with its own MFA requirement.
See the [menu investigation](../audits/2026-10-11-admin-menu/review.md) for the
documentation comparison, resource inventory and remaining interface gaps.

## Deploying the repair

Deploy the changed PHP files with the application's existing release procedure.
Clear/rebuild its route and configuration caches as appropriate, and restart
long-lived PHP/queue workers. No migration, dependency update or frontend build
is required. The source repair and local test results do not imply live deployment.
