# Fresh checkout creation follow-up — 2026-10-25

The owner supplied new deployed screenshots: Start again succeeds; confirming a
fresh 5-location / 10-staff monthly subscription at USD 171 returns 503 with a
saved request and cooldown. Later supplied deployed inspection confirmed a
readable, undeleted, correctly owned test customer, matching saved/current secret
and **six missing Price IDs (resource_missing / HTTP 404)**. Catalog mappings are
unavailable in the current test account. The original account/mode owning those
IDs was not identified; a matching test key prefix never proved catalog access.

The safe provider wrapper discarded its original API exception. Local changes
retain its cause, save only HTTP status / validated error code / allowlisted
parameter on the pending attempt, expose that classification only through the
operator inspector, and privately log the Stripe request reference. No raw API
body/message, credentials, customer contact details or provider object identifiers
are stored in the classification or sent to the billing page. Retry success clears
stale details. The existing pending request/idempotency and financial guards stay.

Four regression cases cover HTTP 400/403/404/503, allowed/rejected parameter roots,
operator JSON, customer response/page privacy and original-attempt retry. Focused
billing suite passes 224 tests / 1,296 assertions; scoped Pint and whitespace pass.

A read-only deployed `billing:inspect-checkout BUSINESS_ID --provider` result was
requested to check the current customer and six monthly/annual Price mappings.
If these pass, inspect the original Stripe request and account settings before
choosing a fix. No new Stripe customer, checkout or charge, deployment, secret
change, tax disablement or production record reset was performed here. These
changes improve diagnosis and do not claim production checkout is fixed.

## Catalog repair and additional hardening

The owner was given the existing `billing:verify-capacity-rates US --provision`
command for the current test account, without --force. It creates/reuses catalog
objects and installs verified local mappings, preserving paid subscriptions and
old cards. The pending trial must restart and review fresh terms after setup;
its old quote must not be silently rewritten. Deployed command outcome and
checkout redirect are still pending; no production repair is claimed.

The provisioner now validates all explicit environment Price ID overrides before
catalog writes. Missing overrides produce variable-specific correction/clearing
instructions. The inspector reports configuration/database mapping source and
catalog-specific setup advice for confirmed missing/mismatched prices, while
provider outage/access failures receive different advice.

Monthly and annual SDK transport regressions reproduce all six missing prices,
confirm failure, run setup twice without duplicate catalog creation, retain paid
subscription attributes and all historical cards, restart the pending test attempt,
review current terms and open exactly one fresh checkout. A separate test asserts
stale explicit overrides cause no catalog writes or local card changes; another
asserts incomplete price reads do not recommend catalog provisioning.
