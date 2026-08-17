# Prompt 06: Operational calendar, lifecycle, and walk-ins

Work in `/Applications/AMPPS/www/barber_app`.

Build the authenticated front-desk and service-floor operation experience on
top of the verified availability engine: FR-06 calendar/lifecycle and FR-08
walk-in queue.

Read root `AGENTS.md`, the documentation index and status, the scheduling
module, quality strategy, accepted decisions, all of FR-06/FR-08, and the
appointment-operation scenarios in PRD Section 7. Inspect the design shell and
reuse established components and domain commands.

Implement:

- today, day, week, and staff-column calendar views with location, staff,
  service, and status filters;
- walk-ins, unassigned visits, blocked time, and accessible status cues;
- create, edit, reschedule/drag, resize, reassign, add/remove service,
  duplicate, cancel, rebook, note, block, and printable daily schedule actions;
- controlled statuses and transition validation with actor/source/reason and
  complete history;
- explicit confirmation for destructive or financially material actions;
- manager conflict override only where the domain permits, with warning, reason,
  and audit;
- walk-in capture, evidence-based wait estimate, queue position, controlled
  reorder, staff assignment, notification event, conversion to client/
  appointment, service start, abandonment, and actual wait tracking.

All calendar mutations must call the shared availability and lifecycle commands
and revalidate at commit. Handle staff becoming unavailable, late arrival,
overrun, reassignment, service removal, and unexpected closure through explicit
recoverable workflows.

Verify desktop, tablet, and mobile usability; keyboard and touch operation;
permission matrix; stale concurrent edits; audit history; notification events;
and a production-like front-desk day simulation. Test that walk-ins never
silently collide with future appointments.

Update the scheduling module, project status, glossary or decisions where
needed. End with state-transition evidence, calendar performance, day-simulation
results, accessibility evidence, and remaining operational exceptions.

