# Prompt 00: Larafast foundation audit

Work in `/Applications/AMPPS/www/barber_app`.

The repository is Larafast boilerplate that will become a new barber shop and
salon management SaaS. Perform an evidence-backed adoption audit and prepare the
foundation for deliberate implementation. Do not implement salon business
features in this prompt.

Before acting, read root `AGENTS.md`, `docs/README.md`,
`docs/project-status.md`, `docs/product-brief.md`, `docs/architecture.md`,
`docs/domain-model.md`, `docs/decisions.md`, and the full
`docs/product-requirements.md`. Inspect actual manifests, lock files, routes,
models, migrations, policies, providers, admin resources, frontend pages, tests,
and environment examples. Treat lock files as version authority.

Deliver:

1. A complete keep/adapt/replace/remove/defer inventory for existing Larafast
   features, dependencies, tables, routes, pages, and provider integrations.
2. A tenant-model recommendation comparing adaptation of Jetstream Team with an
   explicit Business/Tenant model. Cover billing ownership, staff profiles,
   membership, invitations, multi-location readiness, route binding, jobs,
   files, exports, and migration cost.
3. A current architecture and security risk assessment, including duplicate
   billing schemas/providers, stale documentation, unused surface area, secrets,
   authorization, support access, and test gaps.
4. A proposed target folder/module structure that remains idiomatic Laravel and
   a modular monolith.
5. A small, safe cleanup plan ordered by dependency. Do not remove or rewrite
   existing behavior unless I explicitly approve the audit recommendation.
6. Verification of the current test, formatter, and frontend build baseline.

For significant choices, add or update proposed records in
`docs/decisions.md`; do not label them Accepted without my approval. Update
`docs/architecture.md` and `docs/project-status.md` with verified findings.
Create a concise audit document under `docs/audits/` if the findings do not fit
cleanly in existing documents.

Stop and ask me only for a decision that materially changes the adoption path.
End with recommended decisions, risks by severity, exact verification evidence,
and the smallest approved next slice.

