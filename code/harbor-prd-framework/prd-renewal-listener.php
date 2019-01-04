<?php
/**
 * PRD Listener
 * Version: 0.1
 * September 2, 2016
 */


$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');

function dmail($subj, $msg, $array = false) {

	$harbor_prd = get_option('harbor-prd');
	$debug = $debug_email = false;
	if (!empty($harbor_prd['debug'])) {
		$debug = true;
		$debug_email = $harbor_prd['debug_email'];
	}

	$domain = $_SERVER['HTTP_HOST'];
	$filename = __FILE__;

	if ($debug && $debug_email) {
		if ($array) { $msg = print_r($msg, true); }
		$msg = $msg."\r\n".$filename;
		$hdr = 'From: debug@'.$domain."\r\n";
		@mail($debug_email, $subj, $msg, $hdr);
	}
}

$proceed = true;

$ip = $_SERVER['REMOTE_ADDR'];

$ip_array = explode('.', $ip);
$short_ip = $ip_array[0].'.'.$ip_array[1].'.'.$ip_array[2];

$proceed = ($short_ip == '127.0.0' || $ip == '127.0.0.1') ? true : false;

// record every transaction
global $wpdb;
$sql = $wpdb->prepare("INSERT INTO wp_prd_listener (insert_time, ip_address, post_array) VALUES (%d, %s, %s);", time(), $ip, json_encode($_POST));
$wpdb->query($sql);

$user = $_POST['USER'];
$pass = $_POST['PASS'];

$attempt_code = $_POST['ATTEMPT_CODE'];
$original_sp_ref_id = $_POST['ORIGINAL_SP_REF_ID'];
$prd_sp_ref_id = $_POST['PRD_SP_REF_ID'];
$prd_customer_id = $_POST['PRD_CUSTOMER_ID'];
$prd_product = $_POST['PRD_PRODUCT'];
$prd_transaction_amount = $_POST['PRD_TRANSACTION_AMOUNT'];
$prd_cc_last = $_POST['PRD_CC_LAST'];
$prd_next_charge_date = $_POST['PRD_NEXT_CHARGE_DATE'];

$test_mode = ($_POST['TEST_MODE'] == 'true') ? true : false;

$error = array();

if (($user == 'prd-user' && $pass == 'replace-password') || ($user == 'test-user' && $pass == 'replace-password')) {
	$proceed = true;
} else {
	$error['credentials'] = 'error';
	$proceed = false;
}

if ($proceed) {

	$attempt_code_translation = array(
		'A' => 'SUCCESS',
		'D' => 'FAIL',
		'H' => 'RETRY',
		'I' => 'FAIL', 
		'S' => 'RETRY',
	);

	if (!$attempt_code) {
		$error['attempt_code'] = 'missing';
	} else {
		switch ($attempt_code) {
			case 'SUCCESS': break;
			case 'A': break;
			case 'RETRY': $error['attempt code'] = $attempt_code; $proceed = false; break;
			case 'FAIL': $error['attempt code'] = $attempt_code; $proceed = false; break;
			case 'D': $error['attempt code'] = $attempt_code; $proceed = false; break;
			case 'H': $error['attempt code'] = $attempt_code; $proceed = false; break;
			case 'I': $error['attempt code'] = $attempt_code; $proceed = false; break;
			case 'S': $error['attempt code'] = $attempt_code; $proceed = false; break;
			default:
				$error['attempt code'] = 'invalid';
				$proceed = false;
				break;
		}
	}

	$recur = true;

	if (!$prd_product) {
		$error['prd_product'] = 'missing';
	} else {
		switch ($prd_product) {
			case 'CMPRT': break;
			case 'CMWEB': break;
			case 'CMTAB': break;
			case 'CMALL': break;
			case 'SHOPP': $recur = false; $proceed = false; break;
			case 'TRMNL': $recur = false; $proceed = false; break;
			default:
				$error['prd_product'] = 'invalid';
				break;
		}
	}

	if (!$original_sp_ref_id) { $error['original_sp_ref_id'] = 'missing'; $proceed = false; }
	if (!$prd_sp_ref_id) { $error['prd_sp_ref_id'] = 'missing'; $proceed = false; }
	if (!$prd_customer_id) { $error['prd_customer_id'] = 'missing'; $proceed = false; }
	if (!$prd_transaction_amount) { $error['prd_transaction_amount'] = 'missing'; $proceed = false; }
	if (!$prd_cc_last) { $error['prd_cc_last'] = 'missing'; }
	if ($recur) {
		if (!$prd_next_charge_date) { $error['prd_next_charge_date'] = 'missing'; }
	}
}

if ($proceed && !$test_mode) {

	$args = array(
		'attempt_code'				=> $attempt_code,
		'original_sp_ref_id'		=> strtolower($original_sp_ref_id),
		'prd_sp_ref_id'				=> strtolower($prd_sp_ref_id),
		'prd_customer_id'			=> $prd_customer_id,
		'prd_product'				=> $prd_product,
		'prd_transaction_amount'	=> $prd_transaction_amount,
		'prd_cc_last'				=> $prd_cc_last,
		'prd_next_charge_date'		=> $prd_next_charge_date,
	);

	$harborPRD = harborPRD::getInstance();
	$result = $harborPRD->record_auto_renewal($args);

}

$error_notify = (empty($error)) ? false : true;

$error_notify = true;

$response = (empty($error)) ? array('result'=>'success') : array('result'=>'fail','error'=>$error);

if ($test_mode) {
	$response['test'] = 'Test Mode - No Changes Saved';
}

echo json_encode($response);

if ($test_mode || $error_notify) {

	$message = date('l jS \of F Y h:i:s A')."\r\n";
	$message .= "IP:   ".$ip."\r\n";
	$message .= "USER: ".$user."\r\n";
	$message .= "PASS: ".$pass."\r\n";
	$message .= "ATTP: ".$attempt_code."\r\n";
	$message .= "ORIG: ".$original_sp_ref_id."\r\n";
	$message .= "SPRF: ".$prd_sp_ref_id."\r\n";
	$message .= "CUST: ".$prd_customer_id."\r\n";
	$message .= "PROD: ".$prd_product."\r\n";
	$message .= "TEST: ".$test_mode."\r\n";
	$message .= "AMNT: ".$prd_transaction_amount."\r\n";
	$message .= "LST4: ".$prd_cc_last."\r\n";
	$message .= "NEXT: ".$prd_next_charge_date."\r\n";

	$message .= "\r\n";
	$message .= "RESPONSE:"."\r\n";
	$message .= print_r($response, true);

	dmail('CSN PRD Listener', $message);

}