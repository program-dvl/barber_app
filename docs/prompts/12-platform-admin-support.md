# Prompt 12: Platform administration and support operations

Work in `/Applications/AMPPS/www/barber_app`.

Complete FR-20 as a secure platform-operator surface for tenants,
subscriptions, failures, feature flags, health, and support access. This is not
a tenant owner admin screen.

Read root `AGENTS.md`, the documentation index and status,
`docs/modules/platform-and-access.md`, `docs/architecture.md`,
`docs/quality-and-testing.md`, decisions, FR-20, and the support/security
scenarios in PRD Sections 7 and 14. Audit existing Filament resources and
platform roles before reuse.

Implement:

- tenant search and safe summaries of owner, onboarding, plan, usage, trial/
  subscription status, invoices, failures, and activity;
- role-controlled activation, suspension, closure, trial extension, coupon,
  plan change, and export initiation;
- verification/invitation resend and inspection of notification, job, payment,
  and webhook failures;
- application-owned feature flags, notices, health summaries, and safe replay
  of idempotent failed work;
- internal account notes with clear visibility and retention;
- stronger platform authentication/session controls and separated platform
  roles;
- support access requiring ticket/reason, explicit scope, expiry, tenant-visible
  banner, revocation, and immutable audit; and
- alerts for unusual cross-tenant access and restrictions on bulk export.

Do not provide generic impersonation that hides the operator identity. Do not
expose unnecessary message content, payment secrets, authentication data, or
sensitive client notes. Ordinary platform browsing must not bypass tenant
lineage checks.

Test platform role separation, MFA/session behavior, expired grants, grant
scope, tenant visibility, identifier manipulation, bulk export restrictions,
audit immutability, duplicate replay, and support resolution of representative
provider failures without database edits.

Update the platform module, architecture, decisions, support playbooks, and
project status. End with the platform permission matrix, support-access
lifecycle, failure-recovery evidence, alerts, and exact tests.

