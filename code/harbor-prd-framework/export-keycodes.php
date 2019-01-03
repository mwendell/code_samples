<?php

// THIS FILE IS DEPRECATED

// LOAD WORDPRESS
// --------------------------------------------------------------------
	$root = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($root . '/wp-load.php');
	global $wpdb;

// FORMAT EXPORT ARRAY
// --------------------------------------------------------------------
	$columns = array('keycode','pub_id','channel','frequency','period');
	$output = implode(',', $columns)."\n";

// GET DATA
// --------------------------------------------------------------------
	$prd_options = get_option('hb-prd');
	$keycodes = $prd_options['keycodes'];

	foreach ($keycodes as $k) {
		$output .= implode(',', $k)."\n";
	}

// SAVE FILE
// --------------------------------------------------------------------
	$filename = "keycode-export-".date('Ymd').".csv";
	$file = fopen($filename,"w");
	fwrite ($file, $output);
	fclose($file);
	header("Location: $filename");

die();