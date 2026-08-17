# Status page readiness

Status: **not operationally ready**. No external status service, component
checks, subscriber channel, or named status owner is configured.

Minimum launch components are Public booking, Shop application, Appointment
payments (Stripe), Good Hours subscriptions (Paddle), Email (Resend), WhatsApp
(Twilio), Exports, and Support. Each needs an automated signal, owner,
degradation threshold, incident link, and last verified timestamp.

Publish only observed customer impact. Use `Operational`, `Degraded
performance`, `Partial outage`, `Major outage`, or `Maintenance`. A green
application health endpoint must never override known provider, queue,
reconciliation, or customer-impact evidence.

Before launch, run a notification exercise from detection through initial
post, update, resolution, subscriber delivery, and retrospective link. Record
times and prove that tenant/client data is absent from public messages.
