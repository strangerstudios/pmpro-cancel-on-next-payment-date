=== Paid Memberships Pro - Cancel on Next Payment Date Add On ===
Contributors: strangerstudios
Tags: pmpro, membership, cancellation
Requires at least: 4.0
Tested up to: 6.0.1
Stable tag: 0.5.1

Change membership cancellation to set expiration date for next payment instead of cancelling immediately.

== Description ==

Change membership cancellation in Paid Memberships Pro to set expiration date for next payment instead of cancelling immediately.

This plugin currently requires Paid Memberships Pro. 

= Official Paid Memberships Pro Add On =

This is an official Add On for [Paid Memberships Pro](https://www.paidmembershipspro.com), the most complete member management and membership subscriptions plugin for WordPress.

== Installation ==

1. Upload the `pmpro-cancel-on-next-payment-date` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. That's it. This plugin has no additional settings.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-cancel-on-next-payment-date/issues

== Changelog ==
= 0.5.1 - 2022-08-22 =
* ENHANCEMENT: Internationalization for the date in the cancellation text string. (This was accidentally left out of the last update.)

= 0.5 - 2022-08-17 =
* ENHANCEMENT: Internationalization for the date in the cancellation text string.
* BUG FIX/ENHANCEMENT: Now correctly populating the !!startdate!! and !!enddate!! variables for use in the cancel or cancel_admin email templates.
* BUG FIX/ENHANCEMENT: Fixed warnings related to non-int values being passed into the date() function.
* BUG FIX/ENHANCEMENT: Now bailing if we can't figure out which level is being cancelled (happens sometimes with MMPU or when cancellations happen via webhook or custom code).
* BUG FIX/ENHANCEMENT: No longer extending if the cancellation comes from a PayPal Express IPN notification RE failed/skipped payments.
* BUG FIX/ENHANCEMENT: Added support for PayPal Standard. Better PayPal Express support.
* BUG FIX/ENHANCEMENT: No longer extending if the cancellation comes from a Stripe webhook charge.failed event.
* BUG FIX: Fixed code that determines if the user is on the cancel page or edit user/profile page in the admin, which is used in the cancellation logic.
* REFACTOR: Updated doc blocks.
* REFACTOR: Removed some unused code.



= 0.4 - 2021-07-28 =
* NOTE: This version requires PMPro version 2.5.8 or higher.
* BUG FIX/ENHANCEMENT: Uses the new pmpro_change_level filter to keep the user's level from changing ever. Their order and subsciptions are cancelled and their expiration date is set up, but the pmpro_before_change_membership_level and pmpro_after_change_membership_level will not fire. This prevents issues with other code hooked into membership level changes.
* BUG FIX/ENHANCEMENT: Now localizing the end date that is included in emails and confirmation messages.
* BUG FIX: No longer extending a membership if the cancellation status is "error".
* BUG FIX: Better handling cases where multiple memberships expire during one page load, e.g. during the expiration cron.

= 0.3 - 2020-10-13 =
* Initial release.
