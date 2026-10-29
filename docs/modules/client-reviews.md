# Client reviews and private post-visit feedback

Scope: FR-09, FR-19; narrow owner-approved promotion in ADR-074.
The rest of Phase 2 retention/marketplace scope remains deferred.

## Customer outcome

After a service is marked complete, the existing secure appointment page offers
an optional 1–5 rating and up to 5,000 characters of feedback. The client can send
private feedback or explicitly choose public display. Public consent starts off;
it never gates booking or changes the appointment or payment.

Public reviews show initials, rating, plain escaped text, date and “Verified visit”.
They omit client contacts, full name, appointment reference, service/treatment and
staff details. All consented ratings contribute equally; there is no owner
approval, rating threshold, fake placeholder review or selective suppression.
No reviews means no rating or review section on the public page.
The public section loads recent reviews first and offers More reviews through a
published-business gated, 12-record keyset endpoint. Failed loads retain the
reviews already shown, stop automatic retry, and honor Retry-After before another
request. A private review never appears in a page, count, average or cursor.

The client can use the review-purpose link to withdraw public display consent.
The feedback stays available privately to the authorized team and withdrawal
is audited. Review content is one submission per business/appointment; changed
replays do not overwrite it. Editing, owner replies, reporting, moderation and
appeals remain future scope needing a separate contract.
Original public-display wording/version/choice/source/time are retained as
immutable consent evidence; withdrawal changes visibility and records its time.
Governed client merges transfer the client relationship without changing the
review. Client privacy exports include review content and consent/withdrawal
evidence, excluding hashes and secure links.

## Owner workflow

Open a completed appointment in Calendar to read feedback and its public/private
choice. The appointment manager can create/copy a seven-day review link for that
visit, including a manual-copy fallback. The UI explains that it also opens the
private appointment and should be shared only with that client. Link issuance
uses the appointment update policy, preserving business, branch and own-staff
scope. It does not send a message, create marketing consent or charge SMS credits.
Existing feedback/rebooking message suppression remains unchanged.
Client submissions preserve the entered text through validation/network failures,
prevent duplicate clicks, and show a retry cooldown after rate limiting. Expired
links stop submission and explain how to request a fresh link.

## Safety and implementation

`ClientReviewService` locks the appointment and review-purpose link, rechecks
completion, expiry and revocation, and atomically saves feedback/used-token/audit.
A database unique key and composite tenant foreign keys provide a second guard
against duplicates and cross-business relationships. Identical retries return
the same review, including when a first success response was lost. New links
cannot create a second review. Audit events exclude review text and raw links.

`publicSummary` projects only explicit-consent records for one business; private
feedback never affects public counts or averages. No public contact lookup exists.
Secure pages retain no-store/noindex protection and raw tokens are not persisted.

## Verification

`tests/Feature/PublicBooking/ClientReviewsTest.php` exercises private/public
submissions, all-rating projection and tenant separation, duplicate replay and
database uniqueness, consent withdrawal, pending/cancelled/no-show eligibility,
expired/revoked/wrong-purpose links, secure-page integration and operator policy.
Local automated evidence and browser evidence are recorded in project status by
the coordinating change; no deployed review journey or client send is implied.
