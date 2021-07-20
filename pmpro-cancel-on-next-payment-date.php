<?php
/*
 Plugin Name: Paid Memberships Pro - Cancel on Next Payment Date
 Plugin URI: https://www.paidmembershipspro.com/add-ons/cancel-on-next-payment-date
 Description: Change membership cancellation to set expiration date for next payment instead of cancelling immediately.
 Version: 0.3
 Author: Paid Memberships Pro
 Author URI: https://www.paidmembershipspro.com
 Text Domain: pmpro-cancel-on-next-payment-date
 Domain Path: /languages
*/

/**
 * Load plugin textdomain.
 */
function pmproconpd_load_text_domain() {
  load_plugin_textdomain( 'pmpro-cancel-on-next-payment-date', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'pmproconpd_load_text_domain' );

/**
 * Before cancelling, save the next_payment_timestamp to a global for later use. Useful to preserve the timestamp
 * for the next scheduled payment (before it's removed/cancelled as part of the cancellation action)
 *
 * @param int   $level_id     The ID of the membership level we're changing to for the user
 * @param int   $user_id      The User ID we're changing membership information for
 * @param array $old_levels   The previous level(s)
 * @param int   $cancel_level The level being cancelled (if applicable)
 *
 * @global int  $pmpro_next_payment_timestamp - The UNIX epoch value for the next payment
 */
function pmproconpd_pmpro_before_change_membership_level( $level_id, $user_id, $old_levels, $cancel_level ) {
	global $pmpro_pages, $wpdb, $pmpro_next_payment_timestamp;

	// Are we on the cancel page and cancelling a level?
	if ( $level_id == 0 && ( is_page( $pmpro_pages['cancel'] ) || ( is_admin() && ( empty($_REQUEST['from'] ) || $_REQUEST['from'] != 'profile' ) ) ) ) {
		// Default to false. In case we're changing membership levels multiple times during this page load.
		$pmpro_next_payment_timestamp = false;

		// Get the last order.
		$order = new MemberOrder();
		$order->getLastMemberOrder( $user_id, 'success', $cancel_level );

		// Get level to check, if it already has an end date.
		if ( ! empty( $order ) && ! empty ( $order->membership_id ) ) {
			$level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1");
		}

		// Figure out the next payment timestamp.
		if ( empty( $level ) || ( ! empty( $level->enddate ) && $level->enddate != '0000-00-00 00:00:00' ) ) {
			// Level already has an end date. Set to false so we really cancel.
			$pmpro_next_payment_timestamp = false;
		} elseif ( ! empty( $order ) && $order->gateway == 'stripe' ) {
			$pmpro_next_payment_timestamp = PMProGateway_stripe::pmpro_next_payment( '', $user_id, 'success' );
		} elseif ( ! empty( $order ) && $order->gateway == 'paypalexpress' ) {
			// Check the transaction type.
            if ( ! empty( $_POST['txn_type'] ) && $_POST['txn_type'] == 'recurring_payment_failed' ) {
                // Payment failed, so we're past due. No extension.
                $pmpro_next_payment_timestamp = false;
            } else {
                // Check the next payment date passed in or via API.
    			if (  ! empty( $_POST['next_payment_date'] ) && $_POST['next_payment_date'] != 'N/A' ) {
    				// Cancellation is being initiated from the IPN.
    				$pmpro_next_payment_timestamp = strtotime( $_POST['next_payment_date'], current_time('timestamp' ) );
    			} elseif ( ! empty( $_POST['next_payment_date'] ) && $_POST['next_payment_date'] == 'N/A' ) {
    				// Use the built in PMPro function to guess next payment date.
    				$pmpro_next_payment_timestamp = pmpro_next_payment( $user_id );
    			} else {
    				// Cancel is being initiated from PMPro.
    				$pmpro_next_payment_timestamp = PMProGateway_paypalexpress::pmpro_next_payment( '', $user_id, 'success' );
    			}
            }
		} else {
			// Use the built in PMPro function to guess next payment date.
			$pmpro_next_payment_timestamp = pmpro_next_payment( $user_id );
		}
	}
}
add_action( 'pmpro_before_change_membership_level', 'pmproconpd_pmpro_before_change_membership_level', 10, 4 );

/**
 * Give users their level back with an expiration (set to the last day of the subscription period).
 *
 * @param int $level_id     The ID of the membership/subscription level they're currently at
 * @param int $user_id      The ID of the user on this system
 */
function pmproconpd_pmpro_after_change_membership_level( $level_id, $user_id ) {
	global $pmpro_pages, $wpdb, $pmpro_next_payment_timestamp;

	if ( $pmpro_next_payment_timestamp != false && 		//this is false if the level already has an enddate
	   $level_id == 0 && 								//make sure we're cancelling
	   ( is_page($pmpro_pages['cancel'] ) || (is_admin() && ( empty($_REQUEST['from'] ) || $_REQUEST['from'] != 'profile' ) ) ) ) { //on the cancel page or in admin/adminajax/webhook and not the edit user page
		/**
		 * Okay, let's give the user their old level back with an expiration
		 * based on their subscription date.
		 *
		 */
		// Get the last order.
		$order = new MemberOrder();
		$order->getLastMemberOrder( $user_id, 'cancelled' );

		// We can't do this if we can't find the order.
		if ( empty( $order->id ) ) {
			return false;
		}

		// Get the last level the user had.
		$level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1");

		// We can't do this if we can't find an old level.
		if ( empty( $level ) ) {
			return false;
		}

		// Get the last payment date.
		$lastdate = date( 'Y-m-d', $order->timestamp );

		// Get the next payment date.
		if ( ! empty( $pmpro_next_payment_timestamp ) ) {
			$nextdate = $pmpro_next_payment_timestamp;
		} else {
			// TODO Update to use WP time.
			$nextdate = $wpdb->get_var("SELECT UNIX_TIMESTAMP('" . $lastdate . "' + INTERVAL " . $level->cycle_number . " " . $level->cycle_period . ")");
		}

		// Run the process to add level back if the date is in the future.
		if ( $nextdate - current_time( 'timestamp' ) > 0 ) {
			// Give them their level back with the expiration date set.
			$old_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1", ARRAY_A);
			$old_level['enddate'] = date( 'Y-m-d H:i:s', $nextdate );

			// Disable this hook so we don't loop.
			remove_action( 'pmpro_before_change_membership_level', 'pmproconpd_pmpro_before_change_membership_level', 10, 4 );
			remove_action( 'pmpro_after_change_membership_level', 'pmproconpd_pmpro_after_change_membership_level', 10, 2 );

			/**
			 * Disable the action to set the default level on cancels
			 * to make compatible with this previous gist https://gist.github.com/strangerstudios/5703500
			 *
			 */
			remove_action( 'pmpro_after_change_membership_level', 'pmpro_after_change_membership_level_default_level', 10, 2 );

			// Change the level.
			pmpro_changeMembershipLevel( $old_level, $user_id );

			// Add the action back just in case.
			add_action( 'pmpro_before_change_membership_level', 'pmproconpd_pmpro_before_change_membership_level', 10, 4 );
			add_action( 'pmpro_after_change_membership_level', 'pmproconpd_pmpro_after_change_membership_level', 10, 2 );

			// Add the action back to set the default level on cancels.
			if ( function_exists( 'pmpro_after_change_membership_level_default_level' ) ) {
				add_action( 'pmpro_after_change_membership_level', 'pmpro_after_change_membership_level_default_level', 10, 2 );
			}

			// Change the message shown on Cancel page.
			add_filter( 'gettext', 'pmproconpd_gettext_cancel_text', 10, 3 );
		}
	}
}
add_action( 'pmpro_after_change_membership_level', 'pmproconpd_pmpro_after_change_membership_level', 10, 2 );

/**
 * Replace the cancellation text so people know they'll still have access for a certain amount of time.
 *
 */
function pmproconpd_gettext_cancel_text( $translated_text, $text, $domain ) {
	global $pmpro_next_payment_timestamp;

	// Double check that we have reinstated their membership through this Add On.
	if ( empty( $pmpro_next_payment_timestamp ) ) {
		return $translated_text;
	}

	if ( ( $domain == 'pmpro' || $domain == 'paid-memberships-pro' ) && $text == 'Your membership has been cancelled.' ) {
		global $current_user;
		$translated_text = sprintf( __( 'Your recurring subscription has been cancelled. Your active membership will expire on %s.', 'pmpro-cancel-on-next-payment-date' ), date( get_option( 'date_format' ), $pmpro_next_payment_timestamp ) );
	}

	return $translated_text;
}

/**
 * Update the cancellation email text so people know they'll still have access for a certain amount of time.
 *
 */
function pmproconpd_pmpro_email_body( $body, $email ) {
	global $pmpro_next_payment_timestamp;

	/**
	 * Only filter the 'cancel' template and
	 * double check that we have reinstated their membership through this Add On.
	 *
	 */
	if ( $email->template == 'cancel' && ! empty( $pmpro_next_payment_timestamp ) ) {
		global $wpdb;
		$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . esc_sql($email->email) . "' LIMIT 1");
		if ( ! empty( $user_id ) ) {
			// Is the date in the future?
			if ( $pmpro_next_payment_timestamp - current_time( 'timestamp' ) > 0 ) {
				$expiry_date = date( get_option( 'date_format' ), $pmpro_next_payment_timestamp );
				$body .= '<p>' . sprintf( __( 'Your access will expire on %s.', 'pmpro-cancel-on-next-payment-date' ), $expiry_date ) . '</p>';
			}
		}
	}

	return $body;
}
add_filter( 'pmpro_email_body', 'pmproconpd_pmpro_email_body', 10, 2 );

/**
 * Function to add links to the plugin row meta.
 *
 */
function pmproconpd_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-cancel-on-next-payment-date.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/cancel-on-next-payment-date/' )  . '" title="' . esc_attr__( 'View Documentation', 'pmpro-cancel-on-next-payment-date' ) . '">' . esc_html__( 'Docs', 'pmpro-cancel-on-next-payment-date' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-cancel-on-next-payment-date' ) . '">' . esc_html__( 'Support', 'pmpro-cancel-on-next-payment-date' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmproconpd_plugin_row_meta', 10, 2 );
