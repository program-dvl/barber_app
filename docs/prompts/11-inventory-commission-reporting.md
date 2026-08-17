# Prompt 11: Inventory, commissions, reporting, and metrics

Work in `/Applications/AMPPS/www/barber_app`.

Implement FR-16 through FR-18: lightweight inventory, basic commissions and
tips, trusted dashboard/reporting, exports, and Phase 1 instrumentation.

Read root `AGENTS.md`, the documentation index and status, commerce and
reporting module specs, `docs/domain-model.md`,
`docs/quality-and-testing.md`, decisions, FR-16 through FR-18, and PRD Section
15. Use completed sale/payment events as financial truth.

Implement:

- product category, SKU/barcode, price, cost, tax, status, stock, and low-stock
  threshold;
- CSV product import/export, stock receipt, manual adjustment with reason, and
  append-only movement history;
- stock deduction on completed sale and explicit refund/void disposition;
- effective-dated service percentage, product percentage, and fixed-service
  commission rules;
- commission and tip entries, refund reversal/offset, manager adjustment,
  staff statement, and payroll export;
- today dashboard and the complete Phase 1 report catalog with required filters,
  prior-period comparison where stable, drill-down, CSV, and printable summary;
- centralized, versioned definitions for gross/net revenue, collected/expected
  revenue, taxes, discounts, refunds, deposits, tips, utilisation, and client
  classification; and
- privacy-safe acquisition, activation, booking, reliability, revenue
  protection, operations, retention, usage, and support events from PRD Section
  15.

Every count and total must drill to source records and state its freshness and
location time zone. Enforce location/staff/finance permissions in queries and
exports. Use queued exports for volume and preserve tenant context.

Test stock changes exactly once, refund dispositions, commission rule changes,
discount/refund effects, location-day boundaries, role-limited reports,
cross-tenant exports, filter parity, dashboard-to-detail drill-down, CSV/
printable totals, and reconciliation from report to sale to payment. Include
query/performance evidence for realistic data volume.

Update both module specs, metric definitions, decisions, and project status.
End with metric catalog, reconciliation matrix, export evidence, performance
results, and exact tests.

