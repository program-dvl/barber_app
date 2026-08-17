# Module: Clients and consent

Status: Implemented and verified on 2026-08-12

Requirements: FR-11 and FR-12.

## Purpose

Maintain a reliable client identity and service history while protecting
sensitive context and preserving the exact consent evidence relied upon for a
treatment or communication choice.

## Implemented responsibilities

- Booking and reviewed CSV import project into tenant-scoped Client profiles.
  Names, email addresses, and mobile numbers retain display values plus
  normalized matching values. Exact contact plus exact normalized name may
  match; contact with a spelling variation creates a separate profile and a
  human review candidate.
- Profiles store birthday, encrypted free-form and communication preferences,
  tenant-scoped preferred Staff and Service relationships, referral source,
  tags, append-only marketing consent history, merge/anonymisation state, and
  an optimistic version. Authorized users can maintain these fields from the
  Client profile; encrypted casts are preserved by the versioned write path.
- Authored Client Notes classify allergy, sensitivity, formula, hair, skin,
  treatment, patch-test, preference, warning, and general context. Content is
  encrypted; sensitive visibility requires a narrower permission. Viewing
  sensitive context writes a content-free access audit with only the count.
- Client history links Appointment and performer snapshots, notes, consent,
  forms, and private attachment metadata now. The query/export contracts carry
  an explicit empty financial section until the later checkout ledger exists;
  no booked price is mislabeled as lifetime spend.
- Duplicate detection writes candidate evidence only. A permissioned reviewer
  selects the survivor, inspects relationship counts and field-fill effects,
  confirms with a reason and current versions, and commits under deterministic
  row locks. The loser remains as a merged historical identity.
- Six starter templates are published for consultation, allergy declaration,
  patch test, treatment consent, hair-colour history, and photography consent.
  An authorized staff-facing builder creates or revises templates and Service
  associations using text, number, date, yes/no, multiple-choice, required,
  and signature fields. Publishing always creates an immutable version.
- A form request binds Client, exact template version, optional Appointment,
  due time, and a hashed expiring one-use public link. Submission snapshots the
  exact title, introduction, field wording, answers, typed signature digest,
  identity evidence, Appointment, and UTC time. Template changes affect only
  later requests. Client history and Calendar cards show completion state.
- JPEG, PNG, and PDF files up to 10 MB use verified MIME allow-listing,
  tenant-private storage, SHA-256 evidence, visibility and retention classes,
  and revocable 1–60 minute bearer links. Raw tokens are never persisted or
  audited. Production malware-provider certification remains a Prompt 13
  launch gate.
- Privacy cases cover export, correction, consent withdrawal, and
  deletion/anonymisation review with request, deadline, reviewer, result, and
  audit evidence. Exports are encrypted-at-rest private JSON artifacts with a
  content hash, 30-day availability, explicit section manifest, and documented
  omissions.

## Authorization

The Business boundary is enforced by composite foreign keys, tenant context,
scoped child lookup, policies, and service checks. Starter roles receive:

| Capability | Owner | Manager | Reception | Barber/Stylist | Accountant |
| --- | --- | --- | --- | --- | --- |
| View permitted clients/contact | Yes | Yes | Yes | Own-appointment clients | No |
| Edit identity/preferences | Yes | Yes | Yes | No | No |
| Add standard notes/forms | Yes | Yes | Yes | Own-appointment clients | No |
| View/add sensitive notes | Yes | Yes | No | No | No |
| View private attachments | Yes | Yes | Yes | No | No |
| Preview/commit merge | Yes | Yes | No | No | No |
| Process privacy cases | Yes | Yes | No | No | No |

Server authorization is authoritative; navigation visibility is not a
security control. Historical Staff authorship uses restrictive foreign keys so
deactivation cannot remove attribution.

## Data classification and retention

| Data | Classification | Storage / exposure | Current retention behavior |
| --- | --- | --- | --- |
| Name/contact/birthday | Personal | Tenant rows; role-filtered responses | Retained while active or merged |
| Preferences and note content | Sensitive personal | Application encryption; never general audit metadata | Retained pending ADR-018 executor policy |
| Form answers/signature/identity | Sensitive consent evidence | Encrypted immutable submission; exact wording snapshot | No mutation or automatic deletion |
| Attachment bytes | Sensitive private file | Tenant-private key; MIME/size/hash; expiring bearer access | Class and optional date recorded; no auto-delete |
| Consent events | Compliance evidence | Append-only status/wording/version/source/time | Preserved through withdrawal and merge |
| Duplicate/merge preview | Internal operational | Counts, identifiers, reasons; no note content | Retained as merge evidence |
| Privacy export | Sensitive portable artifact | Private JSON, hashed, 15-minute access link | Artifact marked for 30-day availability |
| Audit event | Security/compliance | Safe summaries only; no answers, notes, files, or raw tokens | Append-only |

ADR-018 bounds OPEN-02 and OPEN-10: no destructive automatic deletion or
anonymisation runs before jurisdiction-specific retention is approved. The
implemented workflow produces a retained-data preview and enters
`blocked_policy`; it does not silently change the Client or financial/audit
lineage.

## Merge contract

The current relationship registry moves Appointments, Consent events, Notes,
Form Requests, Form Submissions, Attachments, Privacy Requests, tag
assignments, and preferred Service relationships. Immutable submission identity
snapshots remain unchanged. Future
communications, Sales, Payments, Refunds, Tips, Discounts, and Product history
must register their Client foreign keys with this command in the same change
that introduces those tables. A merge cannot execute without `clients.merge`,
a selected survivor, current versions for both profiles, a preview, explicit
confirmation, and a reason.

## Verification evidence

- `tests/Feature/ClientRecords/ClientCrmConsentTest.php` covers conservative
  spelling/contact matching, candidate creation, stale profile edits, secure
  link revocation after contact changes, encrypted preference writes,
  tenant-scoped tags/preferred Staff/Services, merge preview and relationship
  preservation, exact immutable form evidence, sensitive access logging, role
  filtering, cross-tenant and expired file links, minimized export inspection,
  correction, withdrawal, and policy-blocked anonymisation.
- `tests/Feature/BusinessConfiguration/ConfigurationFoundationTest.php`
  proves reviewed client imports project into real Client records.
- `tests/Feature/PublicBooking/PublicBookingWaitlistTest.php` proves booking
  creates a linked Client and secure contact changes update the same identity.
- The dedicated MySQL concurrency suite runs two independent writers from
  Client version 1 and proves exactly one advances to version 2.
- In-app browser QA completed authenticated directory/profile navigation, a
  protected synthetic allergy note, secure pre-appointment request and one-use
  public submission. The final pass also loaded a starter into the staff-facing
  builder and confirmed all six answer types and preference/privacy controls
  expose accessible names. At 390×844 the profile had 390px content width, no
  page overflow, a 44px back action, named form controls, and responsive mobile
  navigation; the final 1280px pass also had no horizontal overflow.

## Deferred integrations, not missing CRM behavior

Actual lifetime spend and linked Sale, Payment, Refund, Tip, Discount, and
Product rows require their authoritative ledgers from later prompts.
Communication history is now linked through FR-13 and participates in profile
history, privacy export, and merge. Until checkout lands the UI shows spend as
unavailable and the export declares an empty
`checkout_ledger_not_yet_installed` section rather than fabricating financial
history. Later commerce modules must extend the Client history/export/merge
registries without changing the identity, consent, or retention invariants
above.
