<?php
/*
 Plugin Name: Paid Memberships Pro - Cancel on Next Payment Date
 Plugin URI: https://www.paidmembershipspro.com/add-ons/cancel-on-next-payment-date
 Description: Change PMPro membership cancellation to set expiration date for next payment instead of cancelling immediately.
 Version: .2
 Author: Paid Memberships Pro
 Author URI: https://www.paidmembershipspro.com
 Text Domain: pmpro-cancel-on-next-payment-date
*/

//before cancelling, save the next_payment_timestamp to a global for later use.
function pmproconpd_pmpro_before_change_membership_level($level_id, $user_id, $old_levels, $cancel_level ) {
	global $pmpro_pages, $wpdb, $pmpro_stripe_event, $pmpro_next_payment_timestamp;

  //are we on the cancel page and cancelling a level?
	if($level_id == 0 && (is_page($pmpro_pages['cancel']) || (is_admin() && (empty($_REQUEST['from']) || $_REQUEST['from'] != 'profile')))) {
    // Default to false. In case we're changing membership levels multiple times during this page load.
    $pmpro_next_payment_timestamp = false;

    //get last order
		$order = new MemberOrder();
		$order->getLastMemberOrder( $user_id, "success", $cancel_level );

		//get level to check if it already has an end date
		if(!empty($order) && !empty($order->membership_id))
			$level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1");

		//figure out the next payment timestamp
    if(empty($level) || (!empty($level->enddate) && $level->enddate != '0000-00-00 00:00:00')) {
			//level already has an end date. set to false so we really cancel.      
      $pmpro_next_payment_timestamp = false;
		} elseif(!empty($order) && $order->gateway == "stripe") {
			//if stripe, try to use the API
			if(!empty($pmpro_stripe_event)) {
				//cancel initiated from Stripe webhook
				if(!empty($pmpro_stripe_event->data->object->current_period_end)) {
					$pmpro_next_payment_timestamp = $pmpro_stripe_event->data->object->current_period_end;
				}
			} else {
				//cancel initiated from PMPro
				$pmpro_next_payment_timestamp = PMProGateway_stripe::pmpro_next_payment("", $user_id, "success");
			}
		} elseif(!empty($order) && $order->gateway == "paypalexpress") {
			//if PayPal, try to use the API
			if(!empty($_POST['next_payment_date']) && $_POST['next_payment_date'] != 'N/A') {
				//cancel initiated from IPN
				$pmpro_next_payment_timestamp = strtotime($_POST['next_payment_date'], current_time('timestamp'));
			} else {
				//cancel initiated from PMPro
				$pmpro_next_payment_timestamp = PMProGateway_paypalexpress::pmpro_next_payment("", $user_id, "success");
			}
		} else {
			//use built in PMPro function to guess next payment date
			$pmpro_next_payment_timestamp = pmpro_next_payment($user_id);
		}
	}
}
add_action('pmpro_before_change_membership_level', 'pmproconpd_pmpro_before_change_membership_level', 10, 4);

//give users their level back with an expiration
function pmproconpd_pmpro_after_change_membership_level($level_id, $user_id) {
	global $pmpro_pages, $wpdb, $pmpro_next_payment_timestamp;

	if($pmpro_next_payment_timestamp != false && 		//this is false if the level already has an enddate
	   $level_id == 0 && 								//make sure we're cancelling
	   (is_page($pmpro_pages['cancel']) || (is_admin() && (empty($_REQUEST['from']) || $_REQUEST['from'] != 'profile')))) {	//on the cancel page or in admin/adminajax/webhook and not the edit user page
		/*
			okay, let's give the user his old level back with an expiration based on his subscription date
		*/
		//get last order
		$order = new MemberOrder();
		$order->getLastMemberOrder($user_id, "cancelled");

		//can't do this if we can't find the order
		if(empty($order->id))
			return false;

		//get the last level they had
		$level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1");

		//can't do if we can't find an old level
		if(empty($level))
			return false;

		//last payment date
		$lastdate = date("Y-m-d", $order->timestamp);

		/*
			next payment date
		*/
		//if stripe or PayPal, try to use the API
		if(!empty($pmpro_next_payment_timestamp)) {
			$nextdate = $pmpro_next_payment_timestamp;
		} else {
      // TODO Update to use WP time.
      $nextdate = $wpdb->get_var("SELECT UNIX_TIMESTAMP('" . $lastdate . "' + INTERVAL " . $level->cycle_number . " " . $level->cycle_period . ")");
		}

		//if the date in the future?
		if($nextdate - current_time( 'timestamp' ) > 0) {
			//give them their level back with the expiration date set
			$old_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $order->membership_id . "' AND user_id = '" . $user_id . "' ORDER BY id DESC LIMIT 1", ARRAY_A);
			$old_level['enddate'] = date("Y-m-d H:i:s", $nextdate);

			//disable this hook so we don't loop
      remove_action("pmpro_before_change_membership_level", "pmproconpd_pmpro_before_change_membership_level", 10, 4);
      remove_action("pmpro_after_change_membership_level", "pmproconpd_pmpro_after_change_membership_level", 10, 2);

			// Disable the action to set the default level on cancels
      // to make compatable with this gist https://gist.github.com/strangerstudios/5703500
			remove_action('pmpro_after_change_membership_level', 'pmpro_after_change_membership_level_default_level', 10, 2);

      //change level
      pmpro_changeMembershipLevel( $old_level, $user_id );

			//add the action back just in case
      add_action("pmpro_before_change_membership_level", "pmproconpd_pmpro_before_change_membership_level", 10, 4);
      add_action("pmpro_after_change_membership_level", "pmproconpd_pmpro_after_change_membership_level", 10, 2);

			//add the action back to set the default level on cancels
			add_action('pmpro_after_change_membership_level', 'pmpro_after_change_membership_level_default_level', 10, 2);

			//change message shown on cancel page
			add_filter("gettext", "pmproconpd_gettext_cancel_text", 10, 3);
		}
	}
}
add_action("pmpro_after_change_membership_level", "pmproconpd_pmpro_after_change_membership_level", 10, 2);

//this replaces the cancellation text so people know they'll still have access for a certain amount of time
function pmproconpd_gettext_cancel_text($translated_text, $text, $domain) {
  global $pmpro_next_payment_timestamp;

  // Double checking.
  if ( empty( $pmpro_next_payment_timestamp ) ) {
    return $translated_text;
  }

  if(($domain == "pmpro" || $domain == "paid-memberships-pro") && $text == "Your membership has been cancelled.") {
		global $current_user;
		$translated_text = "Your recurring subscription has been cancelled. Your active membership will expire on " . date(get_option("date_format"), $pmpro_next_payment_timestamp) . ".";
	}

	return $translated_text;
}

//want to update the cancellation email as well
function pmproconpd_pmpro_email_body($body, $email) {
  global $pmpro_next_payment_timestamp;

  if($email->template == "cancel" && ! empty( $pmpro_next_payment_timestamp ) ) {
		global $wpdb;
		$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . esc_sql($email->email) . "' LIMIT 1");
		if(!empty($user_id)) {
			//if the date in the future?
			if($pmpro_next_payment_timestamp - current_time( 'timestamp' ) > 0) {
				$body .= "<p>Your access will expire on " . date(get_option("date_format"), $pmpro_next_payment_timestamp) . ".</p>";
			}
		}
	}

	return $body;
}
add_filter("pmpro_email_body", "pmproconpd_pmpro_email_body", 10, 2);
