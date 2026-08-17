# Prompt 08: Client CRM, forms, consent, and privacy

Work in `/Applications/AMPPS/www/barber_app`.

Implement FR-11 and FR-12 as the durable client context for safe personalized
service. This includes identity, history, duplicate control, forms, consent,
attachments, and privacy-request workflows.

Read root `AGENTS.md`, the documentation index and status,
`docs/domain-model.md`, `docs/modules/clients-and-consent.md`,
`docs/quality-and-testing.md`, accepted decisions, FR-11/FR-12, and client/
privacy scenarios in PRD Section 7. Resolve or explicitly bound OPEN-02 and
OPEN-10 before destructive retention behavior.

Implement:

- tenant-scoped client profiles, normalized contact data, preferences, tags,
  referral, communication preference, and marketing consent history;
- protected allergies, sensitivities, formulas, treatment notes, patch-test
  status, important warnings, and authorship;
- linked appointment, performer, service, product, sale, payment, refund, tip,
  discount, message, form, note, and attachment history;
- candidate duplicate detection and a permissioned preview/merge workflow with
  a chosen survivor and immutable audit event;
- versioned form templates with required fields, common input types, signature,
  service association, pre-appointment request, and completion status;
- immutable submission snapshots with exact wording, answers, signature,
  identity, appointment, and time;
- private tenant-scoped attachments and before/after media; and
- tracked export, correction, consent withdrawal, and deletion/anonymisation
  requests that preserve legally retained financial/audit history.

Do not automatically merge on fuzzy matches. Do not store sensitive content in
general audit summaries, logs, public URLs, or unencrypted provider metadata.
Historical context remains readable after staff or service deactivation.

Test spelling/contact duplicates, merge relationship preservation, concurrent
profile edits, permission boundaries for sensitive notes, cross-tenant files,
expired access URLs, template changes after submission, contact changes and
secure links, and every privacy workflow. Include export inspection for data
completeness and minimisation.

Update the client module, domain model, glossary, decisions, retention notes,
and project status. End with data classification, merge evidence, immutable
consent proof, privacy workflow status, and exact tests.

