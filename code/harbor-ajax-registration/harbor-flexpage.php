<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Max-Age: 1000');

define('DONOTCACHEDB', true);
define('DONOTCACHEOBJECT', true);

// DEBUG
	$internal_debug_email = 'mwndll@gmail.com';
	mail( $internal_debug_email, 'FLEXPAGE POST - flexpage.php', print_r( $_POST, 1 ) );

// SKIP IF NO POST DATA
	if ( ! $_POST ) {
		exit( '{"finished":true,"user_id":0,"message":"Pre-registration failed, but this will not affect your purchase."}' );
	}

	// if hbsc is false set it to the default of W
	// issue with "boolean" sent as text via ajax post
	if ( 'false' == $_POST['hbsc'] ) {
		$_POST['hbsc']		= 'W';
		$_REQUEST['hbsc']	= 'W';
	}

// LOAD WORDPRESS
	$root = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
	require_once( $root . '/wp-load.php' );

// CALL MAIN FUNCTION
	$reponse = process_ajax_reg( $_POST );

// RESPOND AND EXIT
	exit( $response );

