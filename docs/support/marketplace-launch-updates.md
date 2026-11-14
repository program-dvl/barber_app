# Marketplace area-launch requests

Scope: MP-05, owner-approved 2026-11-14. One-time requested updates, separate
from accounts, booking messages and ongoing marketing.

Deploy the new migration with the application. Keep the existing scheduler running;
`marketplace:prune-launch-updates` removes pending legacy requests after 7 days from the
latest consent, cancelled/delivered requests after 30 days, and remaining requests
after one year. Production mail delivery, privacy notice coverage and applicable
release checks remain separate from local QA. No legal approval or SEO rollout flag
is changed by this implementation.

When a genuine business joins:

1. Review the requested city, region and country against the business's public
   location. A matching city name alone is insufficient. Check the real business
   serves the requested area.
2. Preview: `php artisan marketplace:launch-update 'Bristol, England, UK' booking-slug`.
   It prints the business, address and count, without recipients or email sends.
3. After reviewing that exact preview, send the same command with
   `--send --location-reviewed`. It rechecks live supply, active subscribed consent,
   cancellation and a one-time delivery claim for every recipient.
4. Uncertain delivery is claimed before SMTP and never automatically resent.
   Investigate the mail provider using internal timestamps before operator recovery.
   Never clear attempted_at blindly; doing so can duplicate a message accepted
   before a transport error.

Requests use the exact normalized area string (case and whitespace ignored).
Different spellings are not automatically merged. There is no unattended sender or
promise of a launch date, business count or available appointment. Unsubscribe in
either email cancels this request, leaving booking/account messages unchanged.

Local fixtures use an isolated SQLite database, `.test` addresses, logged mail and
an intentionally unavailable transport. Never point QA fixtures at production.


2026-11-14 refinement: new newsletter-style signups activate immediately on explicit
consent. The welcome is informational, with no confirmation action. `confirmed_at`
remains verification evidence; `subscribed_at` is the sender eligibility timestamp.
Migration 000003 preserves verified legacy subscribers and does not activate pending
ones. Apply all three 2026-11-14 launch migrations in order. Welcome failure is recorded
as `welcome_failure_reference`, leaves the signup active and is not automatically retried.
Do not tell subscribers to repeatedly click Subscribe to repair email delivery.

During local review, the normal MySQL database had no signup table. The three scoped
migrations were applied locally. One authorized test confirmation was accepted by SES
and the owner confirmed it arrived in the sales inbox. That test preceded the owner's
switch to immediate signup; the final welcome content was rendered and tested locally.
Do not infer a production deployment or a second live welcome send from that evidence.
