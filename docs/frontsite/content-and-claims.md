# Front-site content, claim, and entity contract

Status: Accepted for Phase 1.5 implementation (2026-08-16)

## Canonical facts

| Fact | Approved wording | Evidence | Machine-readable status |
| --- | --- | --- | --- |
| Product | Good Hours is a web-based operating system for salons and barbershops. | Product brief; ADR-011 | Organization and SoftwareApplication eligible |
| Promise | Make every hour count. | ADR-011 | Slogan eligible |
| Positioning | Run your salon or barbershop from booking to checkout. | Implemented FR-01–FR-20 status | Description eligible with launch qualifier where needed |
| Audience | Independent professionals and single-location businesses, typically 2–15 staff; architecture is location-aware. | Product brief | Visible copy; do not imply customers |
| Geography | India, INR and `en-IN` are the implemented commerce profile. | ADR-019/ADR-020 | Do not imply worldwide legal/payment readiness |
| Pricing | Starter USD 50 monthly / USD 500 annual; Pro USD 100 monthly / USD 1,000 annual. | ADR-021/catalog | Offer eligible only when the same active catalogue renders visibly |
| Trial | A verified owner receives a clearly dated trial and exactly-once Business setup. | FR-01 status/tests | CTA may say “Start your trial”; never “free forever” |
| Domain | `getgoodhours.com` is preferred, not acquired or cleared. | ADR-011; OPEN-11 | Never emit as production identity unless configured/approved |

## Claim ledger

| Claim/category | Baseline location | Status | Evidence or issue | Safe Phase 1.5 wording |
| --- | --- | --- | --- | --- |
| “Ship Your SaaS In Days, Not Months” | Home hero | Remove | Larafast product, wrong audience | “Run your salon or barbershop from booking to checkout.” |
| “Trusted by 1000+ developers” / “Loved by…” | Home | Remove | No Good Hours evidence | Omit; show verified product workflow instead |
| “99.9% uptime”, “24/7 support”, deployment/user/component counts | Home | Remove | Targets/placeholders are not production evidence | Omit |
| Amazon, Netflix, Spotify, OpenAI, PayPal, Slack and similar logos | Home assets/integration sections | Remove from UI | No partnerships or integrations | Describe only application-owned provider boundaries when relevant |
| “50+ integrations”, one-click sync, connected badges | Home | Remove | Fabricated/unsupported | Omit |
| “Powerful analytics” and generic real-time insight claims | Home/blog | Replace | Reporting exists but claims are vague | “Trace daily totals back to completed sales and payments.” |
| Enterprise scale / unlimited growth | Home | Remove | Target is independent/single-location; no load evidence | “Built for independent professionals and busy shared front desks.” |
| Dashboard Active Users / Sarah signup / 500K deployments | Home proof cards | Remove | Fabricated product data | Use synthetic, labelled product-state captures only |
| Fake ratings, testimonials, avatars, awards | Home/schema candidates | Remove | No evidence | Omit |
| Blog helps “build better products” | `/blog` | Replace | Larafast audience | “Practical guides for running bookings, clients and the day.” |
| Generic roadmap / coming-soon promises | Public routes/navigation | Remove from discovery | Unowned and exposes unapproved Phase 2 | Quarantine/noindex |
| Organization founders, address, awards, `sameAs`, catalog | `SchemaOrg::organization` | Remove | Translation placeholders/fabricated | Emit only name, configured canonical URL, logo, slogan, and verified description |
| Placeholder review schema | `SchemaOrg::reviews` | Remove | Fabricated Organization/reviewer/rating/body | No Review/AggregateRating schema |
| Article author/profile/image | Blog schema/page | Qualified | Needs public author identity, safe image and publication contract | Emit only reviewed visible author/date/image fields |
| “Prevent double-booking” | Feature/use case | Qualified | Atomic conflict prevention exists for the same governed capacity; cannot promise all human mistakes vanish | “Revalidate staff and required resources before a booking is committed.” |
| No-show protection | Product/use case | Qualified | Deposits and policy exist; live provider certification is blocked | “Set deposit and cancellation rules; live card collection depends on provider approval.” |
| Email/WhatsApp reminders | Features | Qualified | Contracts and local-time delivery evidence implemented; production sending blocked | “Prepare consent-aware email and WhatsApp reminders; live sending requires approved provider setup.” |
| Online booking and secure self-service | Features | Verified | FR-09/FR-10 tests and public journey | “Clients can book and manage an appointment from secure, expiring links.” |
| Calendar and walk-in queue | Features/industry/use case | Verified | FR-06/FR-08 | “Coordinate booked appointments, blocks and walk-ins in one operational day.” |
| Client forms/consent/privacy | Features | Qualified | Intake and tracked cases exist; destructive deletion executor blocked by OPEN-10 | “Keep versioned forms and consent evidence; deletion requests remain policy-blocked until retention approval.” |
| Checkout, receipts, refunds, cash close | Features/pricing | Qualified | Local implementation verified; live appointment payment certification blocked | “Record checkout, tenders, receipts and corrections with append-only history.” |
| Inventory, commissions, tips, reporting/export | Features | Verified locally | FR-16–FR-18 tests/local evidence | “Connect completed sales to stock, commission and traceable reports.” |
| WCAG 2.2 AA, secure, compliant, certified | Any page | Decision-needed/qualified | Target and local controls are not independent certification | State specific implemented controls; never use certification language |

## Phase 2 exclusion search terms

Public copy must not promote memberships, packages, gift cards, loyalty,
referrals, marketing campaigns, native apps, marketplace discovery,
e-commerce, advanced payroll, procurement, franchise/SSO, medical workflows,
classes, an AI receptionist/chatbot, dynamic pricing or forecasting.

## Entity relationships

- **Good Hours** is the software product and publisher. It is not a salon,
  barbershop, local business, payment processor, medical provider, or owner of
  the tenant Businesses using booking URLs.
- **Business** is a tenant operating its own branded booking experience.
  Marketing schema never merges a tenant into the Good Hours Organization.
- **Article/Guide** is published by Good Hours only after its visible author,
  dates, ownership, and content have passed the publication contract.
- **Starter/Pro** are effective-dated software plans, not separate Products and
  not permission to describe live checkout as certified.

## Content ownership

| Content | Accountable owner | Required evidence before indexation |
| --- | --- | --- |
| Product/features/use cases/industries/pricing | Product + Engineering | Claim ledger, implemented status/tests, current catalogue |
| Security/trust | Security + Engineering | Specific control evidence and current external gaps |
| Company/entity facts | Product + Legal | Approved identity fields; OPEN-11 remains visible internally |
| Terms/privacy/cookies | Named Indian counsel/DPO + Product | Approved version, effective date, contact/controller identity, retention/consent review |
| Blog/guides | Editorial owner + subject reviewer | Sanitized source, author, date, excerpt, image rights, claim links, related pages |
| Analytics/attribution notice | Privacy + Product + Engineering | Accepted consent/retention/provider decision |

## Prompt 27 revalidation

The final public-copy search and 23-page crawl found no rendered Larafast
positioning, fabricated proof, unsupported integration/customer/review claim,
Phase 2 promotion, or placeholder schema. Current price and trial values remain
server-owned. Provider, legal, identity, global-availability, medical,
certification, support, uptime, and security statements remain qualified.
Terms/privacy are visibly incomplete and noindex rather than presented as
approved. No claim in this ledger is promoted by the final audit.
