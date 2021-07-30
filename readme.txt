=== Paid Memberships Pro - Cancel on Next Payment Date Add On ===
Contributors: strangerstudios
Tags: pmpro, membership, cancellation
Requires at least: 4.0
Tested up to: 5.8
Stable tag: 0.4

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
= 0.4 - 2021-07-28 =
* NOTE: This version requires PMPro version 2.5.8 or higher.
* BUG FIX/ENHANCEMENT: Uses the new pmpro_change_level filter to keep the user's level from changing ever. Their order and subsciptions are cancelled and their expiration date is set up, but the pmpro_before_change_membership_level and pmpro_after_change_membership_level will not fire. This prevents issues with other code hooked into membership level changes.
* BUG FIX/ENHANCEMENT: Now localizing the end date that is included in emails and confirmation messages.
* BUG FIX: No longer extending a membership if the cancellation status is "error".
* BUG FIX: Better handling cases where multiple memberships expire during one page load, e.g. during the expiration cron.

= 0.3 - 2020-10-13 =
* Initial release.
