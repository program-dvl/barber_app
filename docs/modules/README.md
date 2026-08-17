# Module specifications

These documents translate the PRD into implementation-facing boundaries. They
do not replace the canonical requirements.

| Module | Requirements | Primary upstream dependencies |
| --- | --- | --- |
| `platform-and-access.md` | FR-01, FR-05 access, FR-19, FR-20 | None |
| `business-configuration.md` | FR-02 through FR-05 | Platform and access |
| `scheduling-and-operations.md` | FR-06 through FR-10 | Platform, configuration |
| `clients-and-consent.md` | FR-11 and FR-12 | Platform, scheduling identity |
| `communications.md` | FR-13 | Platform, configuration, scheduling, clients |
| `money-and-commerce.md` | FR-14 through FR-17 | Platform, scheduling, clients |
| `reporting-and-insights.md` | FR-18 | All operational and financial modules |

For each implementation change, update the module's status and interfaces,
record durable choices in `../decisions.md`, and update
`../project-status.md` only after verification.

