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

