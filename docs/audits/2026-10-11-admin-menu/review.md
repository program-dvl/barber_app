# Internal admin menu investigation

Date: 2026-10-11. Scope: FR-19/20; review of LaraFast documentation, local
administrator assignment, panel navigation, disabled resources and platform UI.
This investigation changes documentation only; it does not enable legacy editors
or change role assignments.

## Finding

The limited `/admin` menu is caused by explicit resource quarantine and absent
widget registration, not an incorrectly assigned account role. A read-only check
of the supplied local account confirmed verified email, an active
`platform_administrator` assignment with no expiry/revocation, and successful
`canAccessPanel()` admission. No password/hash or authenticator secret was read
into the investigation output.

Browser inspection of the existing account confirmed Dashboard and Content:
Articles, Changelogs, Launch subscribers and Roadmaps. This matches the supplied
screenshot and the registered resources.

## Documentation reviewed

Read and scrolled the complete content of these linked admin-related chapters:

- [Admin](https://docs.larafast.com/features/admin): Filament panel and `make:admin`.
- [Roles & Permissions](https://docs.larafast.com/features/roles-and-permissions):
  global Spatie roles/permissions, seeders and administration editors.
- [Users](https://docs.larafast.com/features/users): Jetstream/Fortify identities.
- [Teams](https://docs.larafast.com/features/teams): Jetstream team membership.
- [Blog](https://docs.larafast.com/features/blog): articles through the admin panel.
- [Changelog](https://docs.larafast.com/features/changelog): content administration.
- [Stripe](https://docs.larafast.com/features/payments/stripe): catalog, billing,
  subscriptions, orders and provider webhooks.
- [LemonSqueezy](https://docs.larafast.com/features/payments/lemonsqueezy): alternate
  provider subscriptions/orders and webhooks.
- [Paddle](https://docs.larafast.com/features/payments/paddle): alternate provider setup.
- [Emails](https://docs.larafast.com/features/emails): transactional mail configuration.

These describe the upstream boilerplate. They do not override this project's
Business-owned tenancy, platform roles, Stripe-only billing or accepted decisions.

## Resource inventory

| Area | Current `/admin` behavior | Source and reason |
| --- | --- | --- |
| Articles, Changelogs, Launch subscribers, Roadmaps | Visible | Four retained content resources |
| Users, Roles, Permissions | Hidden; direct access disabled | `DisablesUnapprovedPlatformResource` returns false for both access and navigation; ADR-023 quarantines generic identity/global authorization editors |
| Stripe subscriptions/orders/products/prices, Invoices, LemonSqueezy subscriptions/orders | Hidden; direct access disabled | `DisablesLegacyBillingResource` returns false for both checks; legacy User/provider tables are not the Business billing authority under ADR-023/029 |
| Dashboard metrics | No widgets | `AdminPanelProvider` registers `widgets([])` and does not discover widgets; retained widget files do not become visible just because an administrator signs in |
| Businesses, support access, failed operations, health, feature flags, audit events | Separate `/platform` implementation | Inertia workspace/controllers, not Filament resources or navigation items |

`make:admin` grants a `PlatformRoleAssignment`; `User::canAccessPanel()` checks that
assignment. The model does not use Spatie's global `HasRoles` trait, and the current
`RoleSeeder` intentionally does not seed global User roles. Upstream advice to
seed an `admin` role does not repair this panel's explicit false checks.

## Actual product gap

The approved platform capabilities and internal admin entry point are split
between two interfaces. `/admin` has no links or native screens for the existing
platform operations. `/platform` still requires confirmed MFA under ADR-059's
scoped exception; removing MFA from `/admin` did not remove that policy. The local
account's correct administrator role alone does not satisfy that separate gate.

Some `/platform` sidebar entries (standalone Subscriptions, Plans & entitlements,
Payments & invoices and Coupons) still lead to `ModulePlaceholder`, even though
some business-specific backend operations exist. The investigation does not
classify every advertised module as a complete operational interface.

Recommended follow-up: make `/admin` the coherent internal-staff workspace with
the existing Business-aware platform services, audited role/support controls and
accurate readiness states. Resolve the intended authentication boundary before
sharing these surfaces. Restore useful metrics against canonical billing data;
do not simply enable retained User-owned billing widgets or generic role editors.
This recommendation is not an accepted architecture change or implementation.

## Verification

- Read-only local role/email/panel check and browser sidebar inspection.
- `PlatformOperationsTest.php` and `MakeAdminTest.php`: **23 passed / 177 assertions**.
- No account recreation, credential reset, role mutation or production deployment.
