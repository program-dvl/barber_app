# Prompt 13: Launch hardening and chargeability validation

Work in `/Applications/AMPPS/www/barber_app`.

Do not add broad new features. Harden the complete Phase 1 product and produce
the evidence required for a limited paid launch and eventual general
availability.

Read root `AGENTS.md`, every document linked from `docs/README.md`, all module
specifications, decisions, current project status, and the complete PRD. Treat
PRD Sections 7, 12, 14, 15, and 17 as binding release gates. Confirm which P1
items are included in this release train and document any valid deferral.

Complete:

- end-to-end production-like testing of online, phone, reception, walk-in,
  service, checkout, rebooking, subscription, export, and support journeys;
- every critical concurrency, privacy, operations, payment, billing,
  permission, and security scenario from PRD Section 7;
- tenant-isolation review across records, route binding, search, cache, jobs,
  notifications, provider events, files, admin, logs, and exports;
- threat model, dependency/security review, rate limits, session/device review,
  upload controls, webhook controls, and remediation of high-severity findings;
- WCAG 2.2 AA evidence for public booking and core staff workflows;
- responsive and supported-browser verification;
- load tests for availability search, booking commit, calendar, checkout, and
  webhook bursts against the production database/queue topology;
- structured observability, alerts, dashboards, correlation, queue/webhook
  health, reconciliation exceptions, and safe diagnostics;
- backup, restore, RPO/RTO, disaster recovery, and rollback exercise;
- launch-market tax, receipt, privacy, consent, retention, messaging, and
  payment review with named accountable reviewers;
- import/migration rehearsal, data export inspection, support playbooks,
  incident communication, status page, demo tenant, and onboarding help; and
- product metric validation and dashboards using the versioned definitions.

Run the final chargeability test with a representative new shop in
production-like conditions. Record evidence for each PRD Section 17 checkbox.
Do not mark any item complete based only on code inspection.

Update all affected docs, resolve or retain every open decision explicitly, and
make `docs/project-status.md` an honest release-readiness record. Create
versioned launch evidence under `docs/release/` with commands, environments,
dates, owners, results, known limitations, recovery paths, and sign-offs.

End with a go/no-go recommendation, blockers by severity, waived risks with
owners and expiry dates, metric baselines, rollback criteria, and exact
verification evidence.

