# Platform operations and support access

Status: Implemented locally for FR-20; production backup/provider monitoring
certification remains a launch gate.

## Open a support session

1. Find the Business through the safe platform summary. Confirm the public ID,
   owner, plan, and ticket—never infer tenant identity from a child record.
2. A platform administrator other than the intended operator approves the
   Business, ticket, concise reason, minimum scopes, and an expiry within four
   hours.
3. The operator enters the grant. Confirm the response still names the operator
   and the tenant sees the Good Hours Support banner.
4. Work only under `/platform/support/businesses/{business}`. A platform role
   or grant never opens ordinary shop URLs and never becomes a Membership.
5. Exit immediately after resolution. Administrators revoke stale or suspect
   grants; revocation ends all open sessions.

Scopes are `account_summary`, `billing`, `communications`,
`webhook_failures`, `invitations`, and `exports`. Requesting one never implies
another.

## Safe failure recovery

- Inspect the content-minimized failure record and health summary.
- Resolve provider configuration or outage first.
- Replay only a reviewed idempotent type, with ticket/reason and a unique
  operation key. Duplicate keys return the existing result.
- Do not retry generic failed jobs, terminal destinations, unverified
  webhooks, or unknown providers.
- Confirm normalized subscription/payment/message state and audit evidence;
  do not edit a database row to make the incident look resolved.

## Alerts and exports

A high alert is raised when one operator enters at least three Businesses in
fifteen minutes. Security reviews ticket linkage, role assignment, grant scope,
IP hash, and entry/exit evidence, then revokes access if unexplained.

Platform exports are administrator-only and always initiate one Business
request with a lineage snapshot. Any `business_ids`/bulk input is rejected and
raises a critical alert. No support grant authorizes a hidden cross-tenant
download.

## Data minimisation

Never copy message bodies, full destinations, Client notes, authentication
material, payment credentials, provider payloads/exceptions, or secrets into
platform summaries, tickets, audit reasons, or internal account notes. Notes
are `platform_internal`, append-only, and carry a retention date no later than
two years.
