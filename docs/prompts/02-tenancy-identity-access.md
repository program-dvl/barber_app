# Prompt 02: Tenancy, identity, access, and audit foundation

Work in `/Applications/AMPPS/www/barber_app`.

Implement the security and ownership foundation for the multi-tenant salon SaaS:
tenant context, memberships, staff identities, role permissions, session
revocation, and the audit skeleton. Do not implement subscription billing or
operational salon modules yet.

Read root `AGENTS.md`, all source-of-truth guidance in `docs/README.md`,
`docs/project-status.md`, `docs/architecture.md`, `docs/domain-model.md`,
`docs/quality-and-testing.md`, `docs/decisions.md`,
`docs/modules/platform-and-access.md`, and FR-05/FR-19/FR-20 in the canonical
PRD. Review the accepted result of Prompt 00. Use current Laravel, Jetstream,
Sanctum, Spatie Permission, Filament, and testing documentation appropriate to
the installed versions.

OPEN-03, OPEN-07, and OPEN-08 must be decided or explicitly bounded before
irreversible schema work.

Implement:

- explicit tenant context and tenant/location lineage conventions;
- membership and Staff Profile separation;
- owner, manager, receptionist, barber/stylist, and accountant starter roles;
- custom permission support and policies for own/all calendar, client contact,
  sensitive notes, discounts, refunds, revenue, commissions, inventory,
  settings, and billing;
- expiring, revocable staff invitations bound to the correct tenant;
- prompt access removal and session/token revocation;
- audit-event infrastructure for significant actions; and
- safe platform-role separation without invisible tenant impersonation.

Apply tenant enforcement to HTTP routes, model lookup, policies, background job
payload conventions, cache keys, private files, exports, search, and admin
resources as far as those surfaces exist. Do not rely only on global scopes or
hidden navigation; use layered authorization and explicit tests.

Create a tenant-isolation matrix and automated tests for same-tenant access,
unauthorized roles, cross-tenant identifiers, attachments, jobs, exports, and
admin tools. Test former-employee active sessions. Use factories that make
tenant ownership unambiguous.

Keep migrations safe for existing boilerplate data and document any migration
assumption. Update the platform module, domain model, decisions, architecture,
and verified project status. End with the authorization matrix, schema
decisions, test evidence, and unresolved risks.

