# Good Hours editorial system

Status: Accepted Prompt 22 implementation record (2026-08-16)

Resources owns durable discovery, Guides owns the two reviewed static operating
references, and Blog owns chronological editorial records. Categories/search,
comments, newsletter, gated assets and content-at-scale remain deferred.

An article is public only when it is active, `status=published`, its real
publication time has arrived, and it has an author, owner, review date, excerpt,
thumbnail, SEO title and description. Existing rows receive the migration's
`draft` default and are therefore quarantined even if legacy `active=true`.
Draft and incomplete article routes return 404 and never enter listings.

Stored content is interpreted as Markdown and rendered server-side with raw
HTML stripped and unsafe URL protocols disabled. Only the sanitized HTML is
sent to the public page. Material updates use the explicit
`materially_updated_at` value; ordinary database timestamps are not presented
as editorial freshness.

Approved topics should remain limited to distinct clusters with enough useful
work: salon operations/setup, booking policy/no-show prevention, mixed
walk-ins, staff/resources, client history, explainable metrics, deposits and
careful software switching. Every brief needs audience, intent, unique answer,
product evidence, sources/dates, outline, CTA, owner, reviewer and review date.
External facts require an authoritative citation; retirement or correction
must remove or update index/sitemap eligibility rather than silently rewriting
the published date.
