# Business header and customer account redesign — 2026-11-08

Scope: owner's screenshot feedback and request to rebuild customer bookings,
inbox/messages, profile and related page alignment under MP-02/04.

## Evidence and limits

[The supplied header screenshot](header-before-user.png) is the owner's current
reference, copied unchanged. It shows a detached Business qualifier and a large
appointment-prompt strip competing with the Home hero. It is a before image, not
new verification evidence. No account-page screenshot was supplied in this turn.

Product Design preflight found no saved context; repository design-system, actual
components, owner screenshots and established Marketplace palette supplied context.
Browser inventory was attempted through CUA and returned an empty browser inventory
with “Local browser use is disabled in Work settings”. No account journey was
captured, no screenshot audit completed, and no after screenshot is claimed. This
blocked the skill's capture stage; implementation continued within the owner's
explicitly authorized repository task. Source/mounted checks do not establish visual
polish, responsive appearance or accessibility compliance in a real browser.

## Implemented

- Real brand mark with compact vertically aligned Business lockup; existing trial
  CTA; Find a salon navigation with subtle Marketplace colour. Full navigation
  changes to the existing drawer below1280px. Large Home prompt strip removed.
- Shared account rail/header and mobile account navigation across bookings, inbox,
  profile and conversation. Shared form/card/heading/action alignment, including
  phone/name field baseline. Notices align with the customer shell's outer width.
- Booking cards separate date/status/services/zoned time/address/reference/actions;
  favourites remain real saved salon links. Honest empty/history states.
- Compact inbox rows preserve real booking destinations and explicit refresh.
- Profile preserves validated name/phone save, verified email, entered work and
  logout; processing disables edits and repeated saves.
- Chat history/composer with booking sidebar, pause/report, own/other message
  treatment and explicit timestamps. Refresh remains manual; icon-only mobile
  control retains a label. The same thread adapts its wording in the Business shell.

All routes, customer/tenant access, manual payment/deposit rules, conversation
UUID replay and current-contact ownership remain unchanged. No migration, new
provider integration, real customer mutation, external message or deployment.

## Local verification

- `/tmp/marketplace-account-design-regression.log`: **316 passed / 4,935 assertions**
  across Marketplace accounts/refinements and Marketing.
- `/tmp/marketplace-account-design-frontend.log`: **230 passed**, including six new
  mounted account tests covering real visit destinations, stable profile save and
  retained validation input, message draft/replay preservation, confirmed-send reset,
  paused/reopen behavior, explicit refresh/loading and customer-only logout.
- `/tmp/marketplace-account-design-build.log`: client/SSR build; existing CSS/font
  warnings remain. Frontsite budgets: `/tmp/marketplace-account-design-budgets.log`.
- Whitespace check passes. The existing public-design source fixture now expects
  the real compact mark/name lockup rather than the removed tagline image layout.

## Required browser check

On the exact configured local/deployed origin, check Home/header at desktop/tablet/
phone (including trial badge, drawer, long brand labels and focus restoration).
Then inspect bookings/history/favourites, empty/populated inbox, profile validation/
save/discard, and customer/Business message threads. Verify long identity/address/
message content, sender timestamps, paused/reopen/report and failed-send recovery
at desktop and390px phone, including keyboard focus and44px controls. Restore browser
control before claiming those outcomes; prior iteration screenshots are not after
screenshots for this redesign. No deployed customer issue is declared resolved.
