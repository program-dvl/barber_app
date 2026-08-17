# Security, privacy, and tenant-isolation review

Review date: 2026-08-16. Review owner roles: Engineering and Security; named
human reviewers are unassigned. This is an internal threat-model/repository
review, not an external penetration test.

## Threat model

| Asset/boundary | Primary threats | Controls exercised | Residual release risk |
| --- | --- | --- | --- |
| Business records and routes | IDOR, missing tenant scope, confused-deputy support | Explicit Business context before child binding; scoped bindings; policies; cross-tenant tests | External penetration test missing |
| Booking capacity | race, replay, stale result, resource over-allocation | MySQL locks, commit revalidation, idempotency, five-attempt deadlock retry | Peak topology not load-tested; search query count high |
| Money/subscriptions | forged/replayed/out-of-order webhook, duplicate charge, destructive correction | Signature verification, event hash/ID, provider time ordering, append-only ledger, reconciliation tasks | Live Paddle/Stripe/settlement not certified |
| Client identity/consent | contact takeover, stale secure link, excessive collection | Hashed purpose links, revocation on contact change, encrypted sensitive fields, minimised instrumentation | OPEN-10 and India privacy sign-off missing |
| Files/exports | cross-tenant object key, public URL, malicious upload, data leak | Tenant private namespace, MIME/content verification, 10 MB cap, short hashed access link, authorization, scoped export | No malware-scanning provider; scanner status is not independently produced |
| Sessions/admin/support | stolen session, privilege escalation, invisible impersonation | database sessions, session revocation, TOTP for platform roles, idle/absolute support constraints, dual-approved visible support grant | Deployed cookie/TLS/secret values and device/session UX not certified |
| Logs/diagnostics | PII/secrets in logs, untraceable incident | UUID correlation header/log context, audit redaction, fingerprints/content-minimised support summaries | No production log sink/retention/access review |
| Availability endpoints | enumeration, abuse, denial of service | public route throttles: search/start 30/min; commit/hold/payment/waitlist 10/min; secure links 20/min; invitations 6/min | Distributed/Redis enforcement and WAF not tested |

## Dependency review

Initial fresh audits found 45 Composer advisories affecting 19 packages and 20
npm advisories (2 critical, 15 high, 3 moderate). Locks were updated, including
Laravel, Filament, Guzzle, Symfony, CommonMark, Inertia, Ziggy, Axios, Vite,
Puppeteer, and related transitive packages. Fresh unrestricted registry checks report:

- `composer audit --no-interaction --format=summary`: no advisories;
- Node 24 `npm audit --audit-level=moderate`: zero vulnerabilities; and
- complete tests/build/formatting pass after the updates.

This is point-in-time advisory evidence, not an SBOM review or penetration test.

## Tenant isolation by surface

| Surface | Review/result | Evidence/recovery |
| --- | --- | --- |
| Records | PASS-LOCAL | Business foreign keys, TenantContext, policies and cross-business model tests; deny and investigate any mismatch |
| Route binding | PASS-LOCAL | tenant middleware precedes scoped child binding/authorization; altered identifiers denied |
| Search | PASS-LOCAL | tenant search envelope and Business-filtered client/platform queries; platform summaries minimize content |
| Cache | PASS-LOCAL contract | tenant cache-key convention tested; production cache is not configured/tested |
| Jobs | PASS-LOCAL | tenant/correlation payload middleware and membership reauthorization before exports |
| Notifications | PASS-LOCAL | tenant intent/message/provider event lineage and destination fingerprinting; live providers blocked |
| Provider events | PASS-LOCAL | provider event resolves Business from owned intent/subscription and rejects unsigned/unknown evidence |
| Files | PASS-LOCAL for isolation | private tenant key, verified type, tokenized access, cross-tenant denial; malware scan blocked |
| Admin/support | PASS-LOCAL | global platform role does not grant tenant access; scoped, visible, expiring, audited grant required |
| Logs/audit | PARTIAL | correlation and sensitive-key redaction exist; no production sink/access/retention proof |
| Exports | PASS-LOCAL | single-Business lineage, request membership reauthorization, content hash, cross/bulk denial |

## HTTP/session/upload/webhook controls

- Every response now carries `X-Correlation-ID`, `nosniff`, frame denial,
  referrer policy, and a restrictive camera/microphone/geolocation/payment
  permissions policy. Secure production requests receive one-year HSTS.
- Authenticated and secure-token paths receive `private, no-store`; invalid
  caller correlation values are replaced with a UUID.
- Session cookies are HttpOnly and SameSite Lax; the secure flag is environment
  controlled. Platform operations require verified email, TOTP, 15-minute idle
  and eight-hour absolute sessions. Production cookie/domain/TLS behavior was
  not deployed and observed.
- Uploads reject empty, oversized, and non-JPEG/PNG/PDF content using server-side
  MIME detection, but no real scanner/quarantine adapter exists. This is high
  severity for client-supplied PDFs and blocks launch.
- Stripe, Paddle, Resend/Svix, and Twilio webhook signatures and replay windows
  are automated; duplicate/out-of-order processing is idempotent. No public
  provider callback burst or live secret rotation was exercised.

## Remediation disposition

Resolved: known dependency advisories, missing HTTP request correlation and
baseline response protections, demo billing 404, and checkout shell/title.
Open high-severity items: external penetration test, malware scanning,
production secret/cookie/TLS review, live provider certification, and
production-topology load/abuse testing. None is waived.
