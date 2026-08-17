# Prompt 21 — Trust, Company and Legal Pages

Execute after Prompt 20. Improve trust through verifiable transparency, useful contact/support paths and clearly governed legal documents—not badges or invented corporate history.

## 1. Mission

Audit and implement/refine the appropriate About, Contact, Security, Privacy, Terms, Cookies, Data Protection, Accessibility, Support and Status surfaces so visitors can understand who operates Good Hours, how to get help and what is genuinely verified.

## 2. Why This Phase Exists

Existing Jetstream policy pages and boilerplate organization schema are not sufficient business/legal evidence. Prompt 13 records unresolved India legal/privacy/retention, domain/trademark, provider and production-operations controls. Honest boundaries improve trust and prevent regulatory or contractual misrepresentation.

## 3. Prerequisites

Prompts 14–20 are complete. Identify named Product/Operations/legal/security content owners and approved facts. Lack of counsel approval does not authorize Codex to draft definitive legal promises; publish placeholders only as clearly non-production drafts or keep them out of launch scope.

## 4. Read Before Changing Anything

Read mandatory docs, Prompt 13 report, OPEN-10/OPEN-11, ADR-011/018/019/020/021, architecture/security/privacy requirements, current policy/terms Markdown and Jetstream rendering, company/schema translations, contact/sender configuration, support/admin behavior, deployment/monitoring evidence and account deletion/export flows.

## 5. Scope

- Decide which trust/company routes are required now, which need owner-supplied facts and which should be deferred.
- Implement/refine approved About, Contact, Security, Privacy, Terms, Cookies, Data Protection, Accessibility, Support and Status pages.
- Add document metadata/effective dates/version ownership, contact paths, availability/error states, internal links, indexation and tests.
- Create a factual trust-control matrix and legal-review checklist.

## 6. Out of Scope

Giving legal advice, inventing company registration/address/team history, certifying compliance, purchasing support/status tooling, completing pen tests/provider certification, deciding retention law or changing product privacy behavior without a separate approved scope.

## 7. Product Truth

State only controls evidenced in code/reports and qualify environment/operational dependencies. Never claim SOC 2, ISO, HIPAA, PCI certification, GDPR certification, penetration-test completion, guaranteed security, uptime history or global legal compliance unless authoritative current evidence exists. Product brand/domain direction is not proof of ownership.

## 8. Information Architecture

Use clear Company, Trust/Legal and Help groupings in the approved shell. Privacy and Terms routes used during signup must remain stable. Cookie/Data Protection pages exist only when distinct approved content warrants them. Status must link to a real maintained system; otherwise publish no fake green dashboard and document the blocker.

## 9. UX Requirements

Visitors can find operator identity, contact/support method, legal terms, privacy rights/request path, security-reporting path and accessibility feedback route where approved. Forms provide response expectations only if operationally owned. Legal pages are readable, navigable, dated and printable; avoid modal-only policies.

## 10. UI / Design Requirements

Use public shell/editorial components with narrow readable measure, document table of contents, clear effective/updated dates and calm callouts. Avoid security badge walls, fake trust seals or stock team imagery. Form states are complete and accessible.

## 11. Content Requirements

Every factual statement needs source, owner and review date. About describes product purpose and verified business identity without invented founding story/team/location. Security distinguishes product safeguards, customer responsibilities and unverified operational controls. Legal copy must be owner/counsel-approved and preserve required Markdown/versioning. No generic boilerplate left as if final.

## 12. SEO Requirements

Company/trust pages may be indexable when substantive and approved. Transactional privacy-request/account paths are noindex. Provide unique titles/descriptions/canonicals, semantic headings and stable URLs. Legal versions should avoid duplicate indexable URLs through canonical/redirect policy.

## 13. GEO / AEO Requirements

Publish consistent, factual answers about the Good Hours operator, product category, contact methods, data/security approach and legal scope. Maintain qualifiers so generated answers cannot turn an engineering control into a certification.

## 14. Structured Data Requirements

Organization/WebSite/contact data may include only verified legal/display name, canonical owned URL, logo and contact details. Remove placeholder founders, address, awards, `sameAs`, reviews and services. Do not use LocalBusiness for the SaaS operator or certification markup without proof.

## 15. Internal Linking

Footer and relevant signup/pricing/security contexts link to stable legal/trust pages. Privacy pages link to actual request mechanisms; Security to valid reporting/contact path; Accessibility to feedback support. Avoid dead emails, placeholder social links and links from private flows that expose tokens.

## 16. Conversion Requirements

Trust pages support evaluation without aggressive CTA repetition. A restrained verified trial/signup link is acceptable on About/Security/Support; legal text remains free of marketing inserts. Contact/demo forms are separate and must have owned fulfillment, consent and abuse controls.

## 17. Responsive Requirements

Long legal headings, tables, code/contact strings, in-page navigation and forms must work at 360px and zoom without horizontal scrolling. Validate tablet/desktop readable measure and sticky TOC behavior if used.

## 18. Accessibility

Meet WCAG 2.2 AA: semantic document outline, skip/in-page links, focus, form labels/errors/status, accessible tables, contrast, zoom/reflow and print readability. Accessibility statement must not claim conformance beyond verified audit evidence.

## 19. Performance Requirements

Keep primarily textual pages server-rendered and lightweight. Do not load product screenshots, trackers or status SDKs unnecessarily. Cache approved versioned content safely while preserving prompt updates and avoid layout shift.

## 20. Analytics

Legal/privacy/security pages should receive minimal measurement. Never track legal text selection, privacy request details, security-report contents or sensitive form fields. Respect consent and document whether page-view analytics are disabled or minimized.

## 21. Security / Privacy Considerations

Contact/report forms require validation, CSRF, rate limits, spam defense, secure storage/routing, retention and no sensitive details in logs/analytics. Avoid publishing personal addresses or unowned emails. Privacy/deletion exports retain tenant authorization and existing bounded destructive-processing rules.

## 22. Implementation Instructions

1. Build a route/content/control matrix with owner, source, approval, review date and indexability.
2. Preserve stable Jetstream acceptance links while modernizing presentation/content delivery safely.
3. Implement only approved pages and complete form backends; defer unowned pages/forms/status.
4. Replace fabricated Organization schema fields and visible boilerplate in scope.
5. Add tests for routes, auth boundaries, form abuse controls, legal acceptance links, metadata and versions.
6. Record legal/security decisions and unresolved owner actions; status reflects verified facts only.

## 23. Do Not

- Do not invent certifications, uptime, audits, customers, company people/address/history, support SLAs or legal conclusions.
- Do not mark OPEN-10/OPEN-11 or Prompt 13 blockers resolved.
- Do not expose a fake status page/contact form, copy another company's policies or silently change accepted terms.
- Do not log or measure sensitive requests.

## 24. Acceptance Criteria

- Every published trust/company/legal statement has an identified source/owner/review state.
- Signup legal links remain valid and approved documents are readable, dated and version-aware.
- Contact/support/security/privacy paths are real, secured and operationally owned—or honestly deferred.
- Placeholder/fabricated organization schema and visible boilerplate in scope are removed.
- Indexation, accessibility, responsive behavior, tests and build pass; blockers remain explicit.

## 25. Validation / Testing

Run route/status/link tests, signup legal-link regression, form validation/CSRF/rate-limit/spam tests where applicable, access-control/privacy tests, metadata/canonical/schema parsing, SSR/build, mobile/zoom/print/keyboard/accessibility checks, dead-contact verification, console review and `git diff --check`. Record human legal/security approvals as unavailable unless supplied.

## 26. Completion Report

Return these exact fields:

- Files changed
- Routes created
- Routes modified
- Components created
- Components reused
- SEO changes
- Schema changes
- Analytics changes
- Tests run
- Build result
- Warnings
- Assumptions
- Deferred items

Include content owner/approval matrix, unresolved legal/security blockers and whether Prompt 22 is unblocked.
