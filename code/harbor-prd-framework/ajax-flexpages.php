<?php

// UPDATED 4/10/2017

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 1000');

define('DONOTCACHEDB', true);
define('DONOTCACHEOBJECT', true);

$internal_debug_email = 'mwndll@gmail.com';

//mail($internal_debug_email, 'FLEXPAGE POST', print_r($_POST, 1));

// LOAD WORDPRESS
// --------------------------------------------------------------------
$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');

global $wpdb;

$options = get_option('harbor-prd');
$keycodes = $options['keycodes'];
$now = time();

// GET AJAX DATA
	$order = $_POST['order'];

// CREATE USER IF NECESSARY

	if (empty($order['email'])) {
		$msg = "Order did not contain email address.\r\nwp_insert_user() returned ".$user_id."\r\n\r\n".print_r($order);
		mail($internal_debug_email, 'XYZ PRD AJAX Error', $msg);
		exit('{"error":"Could not create user."}');
	}

	if (!email_exists($order['email'])) {

		$user_login = strtolower(sanitize_user($order['firstname'].$order['lastname']));
		if (empty($user_login)) { $user_login = sanitize_user($order['email']); }
		if (empty($user_login)) { $user_login = 'aaa'; }
		if (username_exists($user_login)) {
			$integer_suffix = 2;
			while (username_exists($user_login . $integer_suffix)) { $integer_suffix++; }
			$user_login .= $integer_suffix;
		}
		$user_pass = wp_generate_password(8, false);

	// insert user
	// --------------------------------------------------------------------
		$userdata = array(
			'user_login'	=> $user_login,
			'first_name'	=> $order['firstname'],
			'last_name'		=> $order['lastname'],
			'user_email'	=> $order['email'],
			'user_pass'		=> $user_pass,
		);

		$user_id = wp_insert_user($userdata);

	// check for errors
	// --------------------------------------------------------------------

		if (is_wp_error($user_id) || !is_int($user_id)) {
			$msg = "Did not successfully create user.\r\nwp_insert_user() returned ".$user_id."\r\n\r\n".print_r($order);
			mail($internal_debug_email, 'XYZ PRD AJAX Error', $msg);
			exit('{"error":"Could not create user."}');
		}

	// insert metadata
	// --------------------------------------------------------------------

		$harborRegistration = harborRegistration::getInstance();

		$harborRegistration->save_fields($user_id); // confirm and opt_out tokens

		update_user_meta($user_id, 'first_name', $order['firstname']);
		update_user_meta($user_id, 'last_name', $order['lastname']);
		update_user_meta($user_id, 'user_email', $order['email']);

		add_user_meta($user_id, 'confirm_token', 'confirmed');

	// SEND WELCOME EMAIL
	// --------------------------------------------------------------------

		$harborRegistration->wp_new_user_notification($user_id, $user_pass, false);

	}

// LOAD USER OBJECT

	if ($user_id) {
		$user = get_userdata($user_id);
	} else {
		$user = get_user_by('email', $order['email']);
		$user_id = $user->ID;
	}

// UPDATE ADDRESS INFO
	update_user_meta($user_id, 'address', $order['address1']);
	update_user_meta($user_id, 'address2', $order['address2']);
	update_user_meta($user_id, 'city', $order['city']);
	update_user_meta($user_id, 'state', $order['state']);
	update_user_meta($user_id, 'zip', $order['zip']);
	update_user_meta($user_id, 'country', $order['country']);

// SAVE ORDER DATA

	update_user_meta($user_id, 'prd_customer_id', $order['custno']);
	update_user_meta($user_id, 'order_'.$now, serialize($order));
	update_user_meta($user_id, 'order_'.$now.'_transid', $order['transid']);
	update_user_meta($user_id, 'order_'.$now.'_keycode', $order['keycode']);

// SEND CONFIRMATION EMAIL

	$offer = false;
	$keycode = $order['keycode'];
	$periods = array('D' => 'day', 'W' => 'week', 'S' => 'semi-month', 'M' => 'month', 'Y' => 'year');
	$channels = array('P' => 'print only', 'W' => 'web only', 'T' => 'tablet only', 'C' => 'web and print');

	foreach ($keycodes as $k) {
		if ($k['keycode'] == $keycode) {
			$offer = $k;
		}
	}

	if (!$offer) {
		mail($options['keycode_warning_email'], 'FLEXPAGE ORDER WITH BAD KEYCODE!', "POST:\r\n".print_r($_POST, 1)."\r\n\r\nALLOWED KEYCODES:\r\n".print_r($keycodes, 1));
	}

	$pub_name = get_magazine_title($offer['pub_id']);
	$parent = get_pub_parent_slug($offer['pub_id']);
	if (empty($parent)) { $parent = $offer['pub_id']; }
	$ch_code_for_email = array('P' => 'print', 'W' => 'web', 'T' => 'tablet', 'C' => 'combo');

	$email_slug = "purchase-confirmation-".$parent."-".$ch_code_for_email[$offer['channel']];

	$title = 'Confirming your '.$pub_name.' purchase';
	$body = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$email_slug."') AND (post_type = 'harbor_confirm_email')");

	$ordersummary = $order['suborder'];

	if (empty($ordersummary)) {
		if ($parent == 'health-reports') {
			$ordersummary = $pub_name.' ('.$channels[$offer['channel']].') for $'.$order['ordertotal'];
		} else {
			$ordersummary = $offer['frequency'].' '.$periods[$offer['period']].' subscription to '.$pub_name.' ('.$channels[$offer['channel']].') for $'.$order['ordertotal'];
		}
	}

	$search_array = array('%%firstname%%', '%%productname%%', '%%siteurl%%', '%%ordersummary%%');
	$replace_array = array($order['firstname'], $pub_name, site_url(), $ordersummary);

	$body = str_replace($search_array, $replace_array, $body);

	$success = wp_mail($order['email'], $title, $body);

// DETERMINE EXPIRE DATE

	$freq = (intval($offer['frequency']) > 0) ? intval($offer['frequency']) : 1 ;

	switch ($offer['period']) {
		case 'D': $period = 86400; break;
		case 'W': $period = (86400 * 7); break;
		case 'S': $period = (86400 * 14); break;
		case 'M': $period = (86400 * 30); break;
		case 'Y': $period = (86400 * 365); break;
		default: $period = (86400 * 365); break;
	}

	$length = ($freq * $period);

	$expires = $now + $length;

	$expire_date = date('Ymd', $expires);
	$expire_date_wc = date('Y-m-d', $expires);

// RECORD EXPIRE DATE IN HARBOR and WHATCOUNTS ... NO, JUST IN WC

	$wc = array();

	$wc_pub = strtolower($offer['pub_id']);

	switch ($offer['channel']) {
		case 'P':
			//insert_entitlement($user_id, $offer['pub_id'], 'print', $length);
			$wc[$wc_pub.'_print'] = array($expire_date_wc, 'string');
			break;
		case 'W':
			//insert_entitlement($user_id, $offer['pub_id'], 'web', $length);
			$wc[$wc_pub.'_web'] = array($expire_date_wc, 'string');
			break;
		case 'T':
			//insert_entitlement($user_id, $offer['pub_id'], 'tablet', $length);
			$wc[$wc_pub.'_tablet'] = array($expire_date_wc, 'string');
			break;
		case 'C':
			//insert_entitlement($user_id, $offer['pub_id'], 'print', $length);
			//insert_entitlement($user_id, $offer['pub_id'], 'web', $length);
			//insert_entitlement($user_id, $offer['pub_id'], 'tablet', $length);
			$wc[$wc_pub.'_print'] = array($expire_date_wc, 'string');
			$wc[$wc_pub.'_web'] = array($expire_date_wc, 'string');
			$wc[$wc_pub.'_tablet'] = array($expire_date_wc, 'string');
			break;
	}

	if (!empty($wc)) {
		$harborWhatCountsFramework = harborWhatCountsFramework::getInstance();
		$harborWhatCountsFramework->add_new_user($user_id);
		$harborWhatCountsFramework->set_user_customs($user_id, $wc);
	}

// RECORD PURCHASE TRANSACTION
	if (!empty($order['hbsc'])) {
		$source = $order['hbsc'];
	} else {
		$harborSourceTracking = harborSourceTracking::getInstance();
		$source = $harborSourceTracking->getCurrentSourceCode();
	}

	$args = array('itemId'=>$keycode,'user_id'=>$user_id, 'type'=>'pub', 'type_desc'=>'Publication', 'asid' => $source);

	do_action('harbor-transaction', $args);


exit('{"user":"'.$user_id.'", "order":"'.$now.'"}');

