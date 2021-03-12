<?php
/*
 Plugin Name: Paid Memberships Pro - Cancel on Next Payment Date
 Plugin URI: https://www.paidmembershipspro.com/add-ons/cancel-on-next-payment-date
 Description: Change membership cancellation to set expiration date for next payment instead of cancelling immediately.
 Version: 0.4
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
 * If the user has a payment coming up, don't cancel.
 * Instead update their expiration date and keep their level.
 */
function pmproconpd_pmpro_change_level( $level, $user_id, $old_level_status, $cancel_level ) {
    global $pmpro_pages, $wpdb, $pmpro_next_payment_timestamp;
    
    // Are we on the cancel page and cancelling a level?
	if ( $level == 0 && ( is_page( $pmpro_pages['cancel'] ) || ( is_admin() && ( empty($_REQUEST['from'] ) || $_REQUEST['from'] != 'profile' ) ) ) ) {
		// Default to false. In case we're changing membership levels multiple times during this page load.
		$pmpro_next_payment_timestamp = false;

		// Get the last order.
		$order = new MemberOrder();
		$order->getLastMemberOrder( $user_id, 'success', $cancel_level );

		// Get level to check if it already has an end date.
		if ( ! empty( $order ) && ! empty ( $order->membership_id ) ) {
			$check_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1");
		}

		// Figure out the next payment timestamp.
		if ( empty( $check_level ) || ( ! empty( $check_level->enddate ) && $check_level->enddate != '0000-00-00 00:00:00' ) ) {
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

    // Are we extending?
    if ( ! empty( $pmpro_next_payment_timestamp ) ) {        
        // Make sure they keep their level.
        $level = $cancel_level;
        
        // Cancel their last order.
        if ( ! empty( $order ) ) {
            $order->cancel();
        }
        
        // Update the expiration date.
        $expiration_date = date('Y-m-d H:i:s', $pmpro_next_payment_timestamp );
        $sqlQuery = "UPDATE $wpdb->pmpro_memberships_users SET enddate = '" . esc_sql( $expiration_date ) . "' WHERE status = 'active' AND membership_id = '" . esc_sql( $level ) . "' AND user_id = '" . esc_sql( $user_id ) . "' LIMIT 1";
        $wpdb->query( $sqlQuery );
        
        // Change the message shown on Cancel page.
	    add_filter( 'gettext', 'pmproconpd_gettext_cancel_text', 10, 3 );
    }
    
    return $level;
}
add_filter( 'pmpro_change_level', 'pmproconpd_pmpro_change_level', 10, 4 );

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
 */
function pmproconpd_pmpro_email_body( $body, $email ) {
	global $pmpro_next_payment_timestamp;

	/**
	 * Only filter the 'cancel' template and
	 * double check that we have reinstated their membership through this Add On.
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
