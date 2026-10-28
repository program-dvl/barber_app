# Project instructions for coding agents

This repository is the Larafast-based foundation for a new barber shop and
salon management SaaS. The existing boilerplate is not the finished product.

Before planning or changing product behavior:

1. Read [docs/README.md](docs/README.md).
2. Read [docs/project-status.md](docs/project-status.md).
3. Read the relevant module document under `docs/modules/`.
4. Read the linked requirements in
   [docs/product-requirements.md](docs/product-requirements.md).
5. Check [docs/decisions.md](docs/decisions.md) for accepted and pending
   decisions.

## Source-of-truth rules

- `docs/product-requirements.md` is authoritative for approved product scope.
- Accepted decisions in `docs/decisions.md` may clarify the PRD but may not
  silently contradict it.
- Module documents are the implementation-facing specifications and must link
  back to requirement IDs.
- `docs/project-status.md` records what is actually implemented and verified.
- Repository manifests and lock files are authoritative for installed package
  versions. Do not rely on stale version notes elsewhere.
- Phase 2 features remain out of scope unless a decision record explicitly
  promotes them.

## Change discipline

- Keep product code, tests, and relevant documentation synchronized in the
  same change.
- Update `docs/project-status.md` only with verified facts.
- Record durable architectural or product decisions in `docs/decisions.md`.
- Add newly discovered ambiguity to the open-decisions section instead of
  making an invisible assumption.
- Preserve tenant isolation, authorization, auditability, time-zone
  correctness, idempotency, and append-only financial history in every module.
- Treat all Larafast features as reuse candidates that require an explicit
  audit, not as production-ready salon behavior.
- Prefer a modular monolith and clear domain services over premature
  microservices.
- Do not implement a later prompt until its prerequisite exit evidence is
  satisfied or the dependency is explicitly waived in a decision record.

## Customer experience comes first — every task

- Every visible word must help a non-technical customer understand an outcome,
  make a decision or take the next action. Remove redundant reassurance and
  internal terms such as capacity, provider verification, reconciliation or
  request state. Say what changed: Your subscription is active, Your payment
  is pending, or Your updated subscription starts on [date]. Review real labels,
  notices, empty/loading/error states and help copy together on every UI task.
- Evaluate every change from the customer's goal, confidence and effort, not
  only whether the feature or API exists. This applies to every prompt.
- A recovery action must actually advance the customer toward completion.
  Do not label a button Start again, Retry or Continue when a known condition
  makes it repeat the same failure with no possible progress.
- Complete the customer journey automatically after an external payment return.
  Verify the saved payment, show its confirmed outcome and refresh account details
  without requiring a status click. Distinguish checking, processing and unpaid
  checkout; never announce success from a redirect flag or retry creating charges.
- Own infrastructure/configuration problems within the platform. Customer copy
  must explain what happened, what remains saved and a useful next action;
  never ask customers to repair credentials, environments or server settings.
- Preserve entered work through failures and return navigation. Prevent duplicate
  requests, stop polling on persistent errors, respect Retry-After and show an
  understandable wait before enabling retry. Do not turn normal recovery into
  repeated clicks followed by a Too many attempts dead end.
- Include realistic clock precision and out-of-order provider events when testing
  billing; a frozen whole-second clock must not be the only success evidence.
- Verify the reported failing journey, including abandonment, retry, provider
  mismatch/outage and mobile behavior. Happy-path mocks, passing tests and a
  successful build alone do not establish that the customer's problem is fixed.
- Keep financial safety and tenant isolation intact while removing friction.
  Where customer self-recovery is genuinely impossible, provide a specific,
  reachable support path with a safe reference and actionable operator recovery.
- Report precisely what was verified locally versus on the deployed system;
  never describe a deployed customer issue as resolved without that evidence.

## Shared notifications and action layout — every UI task

- Read `docs/design-system.md` before changing UI; its Notifications contract
  applies across every module. Use `AppNotice` for persistent inline feedback and
  `ToastRegion` only for brief confirmations. Reuse shared tokens/components.
- Notifications use a restrained surface, a small semantic icon, a concise
  heading and useful body copy. Avoid large yellow/red blocks, stacked messages
  for the same problem, raw server errors and decorative emphasis.
- Group related actions in an explicit wrapping row with at least 10px gaps and
  14px separation from copy. Actions must never touch; preserve a clear primary,
  secondary and quiet hierarchy, visible focus and 44px mobile touch targets.
- Update shared experience documentation in the same change. Verify the actual
  failing/loading/success notice with real button labels at desktop and mobile,
  including long text, support links and retry cooldowns.
