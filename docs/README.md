# Product documentation

This directory is the durable product and engineering memory for the salon SaaS.
It converts the approved Word PRD into searchable Markdown and provides the
smaller, implementation-facing documents that future Codex threads and human
developers should use.

Last structured review: 2026-08-16.

## Start here

For every new task:

1. Read `project-status.md` to distinguish implemented behavior from planned
   behavior.
2. Read the relevant file in `modules/`.
3. Follow its links into `product-requirements.md`.
4. Check `decisions.md` for resolved and unresolved choices.
5. Use `quality-and-testing.md` for the required evidence.
6. Update the affected documents when the task changes behavior, architecture,
   scope, terminology, or verified status.

## Authority order

When documents disagree, use this order:

1. Approved requirement changes recorded in `product-requirements.md`
2. Accepted decisions in `decisions.md`
3. Module specifications in `modules/`
4. Architecture and quality guidance
5. Delivery prompts and historical notes

`project-status.md` is authoritative only for current implementation state.
Code and automated tests remain the final evidence that a claimed behavior
exists.

## Document map

| Document | Purpose | Update trigger |
| --- | --- | --- |
| `product-requirements.md` | Canonical transcription of the complete Phase 1 PRD | Approved scope or acceptance change |
| `product-brief.md` | Fast product orientation, users, outcomes, scope, and commercial posture | Material product direction change |
| `project-status.md` | Verified current state, risks, and next recommended stage | Every completed implementation prompt |
| `architecture.md` | Current stack, Larafast reuse posture, target boundaries, and cross-cutting rules | Architecture or dependency decision |
| `domain-model.md` | Shared entities, relationships, state ownership, and invariants | Domain model change |
| `glossary.md` | Canonical business vocabulary | New or changed domain term |
| `quality-and-testing.md` | Quality attributes, testing strategy, and release evidence | Quality gate or test strategy change |
| `public-booking-journey.md` | Implemented public flow, secure-link, waitlist, failure, and placeholder contract | Public booking, communications, or payment coordination change |
| `roadmap.md` | Dependency-aware delivery sequence and prompt map | Stage ordering or scope change |
| `decisions.md` | Accepted decisions and unresolved choices | Any durable decision |
| `audits/*.md` | Dated evidence-backed repository, architecture, security, or adoption audits | Each completed audit or material re-audit |
| `frontsite/*.md` | Public acquisition IA, indexation, claims, entity, content, CTA, and crawl contracts | Every Phase 1.5 public-site prompt |
| `modules/*.md` | Implementation-facing module boundaries and rules | Module behavior or interface change |
| `prompts/*.md` | Copy-ready prompts for separate implementation threads | Delivery strategy change |
| `support/*.md` | Tenant-safe diagnostic and recovery playbooks | New support-visible failure or recovery path |
| `release/*/` | Versioned launch evidence, gate decisions, owners, limitations, recovery, and sign-off state | Every release candidate or launch rehearsal |

## Documentation maintenance rules

- Use requirement IDs such as `FR-06` in plans, tests, commits, and module docs.
- State whether a claim is planned, implemented, or verified.
- Avoid copying the same detailed rule into many files. Link to the canonical
  requirement and document only the module interpretation or decision.
- Never delete historical financial, inventory, consent, or audit behavior from
  the docs without recording the replacement decision.
- Use ISO dates (`YYYY-MM-DD`) and explicit time zones.
- Keep examples tenant-safe and free of real client data.
- Do not mark a checkbox complete without reproducible evidence.

## Original source review

The supplied `salon_mvp_prd.docx` was reviewed as a 32-page document with 17
top-level sections, 20 functional requirements, and 14 tables. The canonical
Markdown transcription preserves all sections, tables, priorities, acceptance
outcomes, deferred scope, quality attributes, scenarios, metrics, and launch
checks.
