<?php
/**
 * Template Name: Page Offsite Order Process
 */

// UPDATE 2017-10-25 for XYZ

session_start();

define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTCACHEOBJECT', true);

$options = get_option('harbor-prd');

$internal_debug_email = $options['debug_email'];
$debug = ($options['debug']) ? true : false;

global $wpdb;

$now = time();

$prd_products = array();
foreach ( $options['products'] as $p ) {
	$sec = (empty($p['secondary_product_id'])) ? 0 : $p['secondary_product_id'];
	if ( $p['service'] == 'cn' || strpos($sec, ',') !== false ) {
		$spids = explode(',', $sec);
		foreach ( $spids as $spid ) {
			if ( $p['service'] == 'cn' ) {
				$prd_products[$p['service']][$spid] = $p['pub_id'];
			} else {
				$prd_products[$p['service']][$p['product_id']][$spid] = $p['pub_id'];
			}
		}
	} else {
		$prd_products[$p['service']][$p['product_id']][$sec] = $p['pub_id'];
	}
}

$default_channels = array(
	'COMBO'			=> 'combo',
	'ALL-ACCESS'	=> 'combo',
	'CB'			=> 'combo',
	'C'				=> 'combo',
	'DIGITAL'		=> 'digital',
	'DIG-COMBO'		=> 'digital',
	'D'				=> 'digital',
	'PRINT'			=> 'print',
	'P'				=> 'print',
	'TABLET'		=> 'tablet',
	'TABLE'			=> 'tablet',
	'T'				=> 'tablet',
	'WEB'			=> 'web',
	'W'				=> 'web',
	'ALL'			=> 'web',
);

$channel_descriptions = array(
	'combo'			=> 'All-Access Subscription',
	'digital'		=> 'Digital Access Subscription',
	'print'			=> 'Print Subscription',
	'tablet'		=> 'Tablet Subscription',
	'web'			=> 'Website Subscription',
);

$order = array();

// GET ORDER DATA POST'D FROM PRD

	$order['rqst'] = json_decode(urldecode($_POST['json_request']));
	$order['resp'] = json_decode(urldecode($_POST['json_response']));
	$order['get'] = $_GET;

	$user = (array) $order['rqst']->customer;

	$cart = array();
	foreach ($order['rqst']->sp_orders as $sp) { $cart[] = $sp; }
	foreach ($order['rqst']->su_orders as $su) { $cart[] = $su; }
	foreach ($order['rqst']->cn_orders as $cn) { $cart[] = $cn; }
	foreach ($order['rqst']->ca_orders as $ca) { $cart[] = $ca; }

	$resp = (array) $order['resp']->response;

	if ($debug) {
		mail($internal_debug_email, 'FLEXPAGE POST', 'ORDER: '.print_r($order, 1)."\r\n\r\nCART: ".print_r($cart,1)."\r\n\r\nOPTIONS: ".print_r($options,1)."\r\n\r\nSent by page-offsite-order-process.php");
	}

// CREATE USER IF NECESSARY

	if (empty($user['email'])) {
		$msg = "Order did not contain email address.\r\nwp_insert_user() returned ".$user_id."\r\n\r\n".print_r($order,1)."\r\n\r\nSent by page-offsite-order-process.php";
		mail($internal_debug_email, 'PRD ORDER POST Error', $msg);
		//exit('{"error":"Could not create user."}');
	}

	if (!email_exists($user['email'])) {

		$user_login = strtolower(sanitize_user($user['first'].$user['last']));
		if (empty($user_login)) { $user_login = sanitize_user($user['email']); }
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
			'first_name'	=> $user['first'],
			'last_name'		=> $user['last'],
			'user_email'	=> $user['email'],
			'user_pass'		=> $user_pass,
		);

		$user_id = wp_insert_user($userdata);

	// check for errors
	// --------------------------------------------------------------------

		if (is_wp_error($user_id) || !is_int($user_id)) {
			$msg = "Did not successfully create user.\r\nwp_insert_user() returned ".print_r($user_id,1)."\r\n\r\n".print_r($order,1)."\r\n\r\nSent by page-offsite-order-process.php";
			mail($internal_debug_email, 'PRD ORDER POST Error', $msg);
			//exit('{"error":"Could not create user."}');
		}

	// insert metadata
	// --------------------------------------------------------------------

		$harborRegistration = harborRegistration::getInstance();

		$harborRegistration->save_fields($user_id); // confirm and opt_out tokens

		update_user_meta($user_id, 'first_name', $user['first']);
		update_user_meta($user_id, 'last_name', $user['last']);
		update_user_meta($user_id, 'user_email', $user['email']);

		add_user_meta($user_id, 'confirm_token', 'confirmed');

	// SEND WELCOME EMAIL
	// --------------------------------------------------------------------

		$harborRegistration->wp_new_user_notification($user_id, $user_pass, false);

	}

// LOAD USER OBJECT

	if ($user_id) {
		$new_user = get_userdata($user_id);
	} else {
		$current_user = get_user_by('email', $user['email']);
		$user_id = $current_user->ID;
	}

// UPDATE ADDRESS INFO
	update_user_meta($user_id, 'address', $user['add1']);
	update_user_meta($user_id, 'address2', $user['add2']);
	update_user_meta($user_id, 'city', $user['city']);
	update_user_meta($user_id, 'state', $user['st']);
	update_user_meta($user_id, 'zip_code', $user['zip']);
	update_user_meta($user_id, 'country', $user['country']);

// SAVE ORDER DATA

	update_user_meta($user_id, 'prd_customer_id', $resp['CUSTOMER_NUMBER']);
	update_user_meta($user_id, 'order_'.$now, serialize($order));
	update_user_meta($user_id, 'order_'.$now.'_transid', $resp['TRANSACTION_ID']);
	update_user_meta($user_id, 'order_'.$now.'_keycode', $cart['key_code']);

// SEND CONFIRMATION EMAIL

	$key = 0;
	$c = $cart[$key];

	$pub_id = $program = $parent = $channel = $keycode = false;

	$keycode = $c->key_code;

	$program = strtolower($c->program_type_id);

	$product = ($program == 'ca') ? $c->product : array($c->product);

	$sec_sp = ($program == 'sp') ? $c->secondary_sp_code : false;

	if (array_key_exists($sec_sp, $default_channels)) {
		$channel = $default_channels[$sec_sp];
		$sec_sp = 0;
	} else {
		if ($program == 'su') {
			$channel = ($c->access_level) ? $default_channels[$c->access_level] : 'print';
		} else {
			$channel = ($c->access_level) ? $default_channels[$c->access_level] : 'web';
		}
	}

	$pk = 0;
	$p = $product[$pk];

	if ( $program == 'cn' ) {
		if (array_key_exists($p, $prd_products[$program])) {
			$pub_id = $prd_products[$program][$p];
		}

		// XYZ makes heavy use of continuities, but continuities don't have access levels.
		// However, PRD has a pattern in their OFFER_PRODUCT codes that indicates channel.
		if ( strpos(get_site_url(), 'harbor') !== false ) {
			$ch = substr($p, 0, 2);
			switch ( $ch ) {
				case 'SR':
				case 'M9': $channel = 'print'; break;
				case 'CR':
				case 'C9': $channel = 'combo'; break;
				case 'ER':
				case 'D9':
				default:   $channel = 'web';
			}
		}

	} else {
		if (array_key_exists($sec_sp, $prd_products[$program][$p])) {
			$pub_id = $prd_products[$program][$p][$sec_sp];
		} else {
			$pub_id = $prd_products[$program][$p][0];
		}
	}

	if (!$pub_id) {
		$pub_id = 'XX';
		mail($internal_debug_email, 'COULD NOT IDENTIFY PUB_ID FOR NEW ORDER', 'ORDER: '.print_r($order, 1)."\r\n\r\nCART: ".print_r($cart,1)."\r\n\r\nPRD_PRODUCTS: ".print_r($prd_products,1)."\r\n\r\nSent by page-offsite-order-process.php");
	}

	if (array_key_exists($s->SP_CODE, $products['sp'])) {
		$pub_id = $products['sp'][$s->SP_CODE][$secondary_sp_code];
	}

	// to entitle single issues of a publication, SECONDARY_SP_CODE must follow
	// format XX###, where XX is valid pub_id and ### is three digit integer
	if ($sec_sp) {
		if (preg_match( '/^'.$pub_id.'\d\d\d$/', $sec_sp)) {
			$issue_number = intval(preg_replace('/^\D*/', '', $sec_sp));
			if (is_int($issue_number)) {
				$sql = $wpdb->prepare("SELECT p.post_title FROM wp_posts p JOIN wp_postmeta m ON p.ID = m.post_id AND m.meta_key = 'clayflicks_prd_video_id' WHERE (m.meta_value = %d)", $issue_number);
				$issue_title = $wpdb->get_var($sql);
			}
		} else {
			$issue_number = $issue_title = false;
		}
	}

	$pub_name = get_pub_title($pub_id);

	$price = 0;

	switch ($program){
		case 'sp':
			$price += floatval($c->offer_pmt);
			$qty = 1;
			break;
		case 'ca':
			$price += floatval($c->price[0]) * floatval($c->qty[0]);
			$qty = $c->qty[0];
			break;
		default:
			$price += floatval($c->price) * floatval($c->qty);
			$qty = $c->qty;
			break;
	}

	$total = $price;
	$shipping = (empty($c->shipping)) ? false : floatval($c->shipping);
	$tax = (empty($c->tax)) ? false : floatval($c->tax);
	$net_discount = (empty($c->net_discount)) ? false : floatval($c->net_discount);

	$description = ($issue_title) ? $issue_title : $channel_descriptions[$channel];

	$ordersummary = "<table style='width: 100%; margin: 0;' cellspacing=0>";
	$ordersummary .= "<tr style='background-color: #aaaaaa; border-bottom: 1px solid #666666'><th style='width: 10%;'><small><b>QTY</b></small></th><th align='left'><small><b>Product</b><small></th><th align='right' style='padding-right: 10px; width: 10%;'><small><b>Amount</b></small></th></tr>";
	$ordersummary .= "<tr><td align='center' style='width: 10%;'>".$qty."</td><td align='left'>".$pub_name."<br><small>".$description."</small></td><td align='right'' style='padding-right: 10px; width: 10%;'>$".number_format($price,2)."</td></tr>";
	if ($shipping) {
		$total += $shipping;
		$ordersummary .= "<tr><td colspan=2 align='right'>Shipping: </td><td align='right'' style='padding-right: 10px; width: 10%;'>$".number_format($shipping,2)."</td></tr>";
	}
	if ($tax) {
		$total += $tax;
		$ordersummary .= "<tr><td colspan=2 align='right'>Tax: </td><td align='right'' style='padding-right: 10px; width: 10%;'>$".number_format($tax,2)."</td></tr>";
	}
	if ($net_discount) {
		$total -= $net_discount;
		$ordersummary .= "<tr><td colspan=2 align='right'>Discount: </td><td align='right'' style='padding-right: 10px; width: 10%;'>($".number_format($net_discount,2).")</td></tr>";
	}
	$ordersummary .= "<tr style='border-top: 1px solid #666666'><td colspan=2 align='right'>Total: </td><td align='right'' style='padding-right: 10px; width: 10%;'>$".number_format($total,2)."</td></tr>";
	$ordersummary .= "</table>";

	$email_slug = "purchase-confirmation-".strtolower($keycode);
	$body = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$email_slug."') AND (post_type = 'harbor_confirm_email')");

	if (!$body) {

		$parent = get_pub_parent_slug($pub_id);
		if (!$parent) { $parent = $pub_id; }

		$email_slug = "purchase-confirmation-".strtolower($parent)."-".$channel;
		$body = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$email_slug."') AND (post_type = 'harbor_confirm_email')");
	}

	$search_array = array('%%firstname%%', '%%productname%%', '%%siteurl%%', '%%ordersummary%%');
	$replace_array = array($user['first'], $pub_name, site_url(), $ordersummary);

	$body = str_replace($search_array, $replace_array, wpautop($body));
	$title = 'Confirming your '.$pub_name.' purchase';
	$header = array('Content-Type: text/html; charset=UTF-8');

	$success = wp_mail($user['email'], $title, $body, $header);

// REFRESH ENTITLEMENT INFORMATION USING HARBOR ENTITLEMENTS / GATEKEEPER

	$entitlements = load_entitlements($user_id, true); // true forces new entitlements to be inserted into database

// RECORD EXPIRE DATE IN WHATCOUNTS

	if ($entitlements) {

		$expire_wc = 0;

		foreach ( $entitlements as $this_pub_id => $these_channels ) {
			if ( $this_pub_id == $pub_id ) {
				foreach ( $these_channels as $expire ) {
					if ( $expire > $expire_wc ) {
						$expire_wc = $expire;
						break;
					}
				}
			}
		}

		if ( $expire_wc > 0 ) {

			$expire_date_wc = date('Y-m-d', $expire_wc);

			$wc = array();

			$wc_pub = strtolower($pub_id);

			switch ($channel) {
				case 'combo':
					$wc[$wc_pub.'_print'] = array($expire_date_wc, 'string');
					$wc[$wc_pub.'_web'] = array($expire_date_wc, 'string');
					//$wc[$wc_pub.'_tablet'] = array($expire_date_wc, 'string');
					break;
				case 'digital':
					$wc[$wc_pub.'_web'] = array($expire_date_wc, 'string');
					$wc[$wc_pub.'_tablet'] = array($expire_date_wc, 'string');
					break;
				case 'print':
					$wc[$wc_pub.'_print'] = array($expire_date_wc, 'string');
					break;
				case 'tablet':
					$wc[$wc_pub.'_tablet'] = array($expire_date_wc, 'string');
					break;
				case 'web':
				default:
					$wc[$wc_pub.'_web'] = array($expire_date_wc, 'string');
					break;
			}

			if (!empty($wc)) {
				$harborWhatCountsFramework = harborWhatCountsFramework::getInstance();
				$harborWhatCountsFramework->add_new_user($user_id);
				$harborWhatCountsFramework->set_user_customs($user_id, $wc);
			}

		}

	}

// RECORD PURCHASE TRANSACTION

	if (!empty($order['get']['hbsc'])) {
		$source = $order['get']['hbsc'];
	} else {
		$harborSourceTracking = harborSourceTracking::getInstance();
		$source = $harborSourceTracking->getCurrentSourceCode();
	}

	$itemId = ($issue_number) ? $keycode.'-'.$issue_number : $keycode;

	$args = array('itemId'=>$itemId,'user_id'=>$user_id, 'type'=>'pub', 'type_desc'=>'Publication', 'asid' => $source);

	do_action('harbor-transaction', $args);

// REDIRECT TO CONFIRMATION PAGE

	foreach($_SESSION as $session_key => $s) {
		if (strpos($session_key, 'order-') !== false) {
			unset($_SESSION[$session_key]);
		}
	}

	$redirect_url = '/subscription-confirmation/';

	$query_args = array(
		'oid'	=> $now,
	);

	$order_array = array(
		"pub_id"	=> $pub_id,
		"channel"	=> $channel,
		"keycode"	=> $keycode,
		"summary"	=> $ordersummary,
		"issue"		=> $issue_number,
	);

	$_SESSION["order-".$now] = $order_array;

	$redirect_url = add_query_arg($query_args, $redirect_url);

	wp_safe_redirect($redirect_url);
	exit;

// BELOW IS ERROR CONDITION ONLY

	get_header();

	echo "<div class='row'>";
	echo "<div class='large-8 medium-8 columns' role='main' id='maincol'>";

	while ( have_posts() ) {
		the_post();

		if ($user_id && $pub_id && $pub_name) {

			if (preg_match('/^.+\d\d\d$/', $pub_id)) { $post_slug = 'order-confirmation-cf-single-issue'; }

			$post_slug = "order-confirmation-".$keycode;
			$post_content = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$post_slug."') AND (post_type = 'uc');");

			if (!$post_content) {
				$post_slug = "order-confirmation-".strtolower($pub_id)."-".$channel;
				$post_content = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$post_slug."') AND (post_type = 'uc');");
			}

			if ($post_content) {

				$search_array = array('%%productname%%', '%%siteurl%%', '%%ordersummary%%');
				$replace_array = array($pub_name, site_url(), $ordersummary);

				$post_content = str_replace($search_array, $replace_array, $post_content);

				if ($dev) { $dev = '<b>DISPLAYING: ' . $post_slug . '</b><br/><br/>'; }

			} else {

				$post_content = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = 'order-confirmation-error') AND (post_type = 'uc');");

				if ($dev) { $dev = '<b>COULD NOT FIND: ' . $post_slug . '</b><br/>KEYCODE WAS '.$keycode.'<br/><br/>'; }

			}

			echo "<article ".get_post_class()." id='post-".get_the_ID()."'>";
			echo "<div class='entry'>";
			echo wpautop($post_content);
			echo "</div>";
			echo "</article>";

		}

	}

	echo '<!--';
	echo '<hr/>';
	echo '<h3>raw order information</h3>';
	echo '<pre>';
	print_r($order);
	echo '</pre>';
	echo '-->';

	echo "</div><!-- end div.large-8 medium-8 columns -->";

	get_sidebar();

	echo "</div>";

	get_footer();
