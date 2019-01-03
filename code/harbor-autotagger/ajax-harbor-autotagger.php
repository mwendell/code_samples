<?php

/**
 * Name: AJAX Program for Harbor Autotagger
 * Version: 1.4
 */

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');

$go = ($_GET['go'] == 'true') ? true : false;

if ($go) {

	$harborat_force = $_GET['mf'];
	$harborat_ping = urldecode($_GET['emo']);
	$harborat_posts = urldecode($_GET['pst']);
	$harborat_tags = urldecode($_GET['tgs']);

	$default = "The Harbor Autotagger has been started.";

	if ($harborat_force != 'daily') {
		$default .= " Depending on your settings, this process could take quite a while.";
	}

	if ($harborat_ping) {
		$default .= "<br><br>You will receive an email when the process is complete.";
		update_option('harborat_ping', $harborat_ping);
	}

	if ($harborat_tags) {
		$harborat_tags = preg_replace('/[^0-9,]/','',$harborat_tags);
		$harborat_tags = explode(',',$harborat_tags);
		foreach ($harborat_tags as $key => $m) { $harborat_tags[$key] = (ctype_digit($m)) ? $m : false; }
		$harborat_tags = array_filter($harborat_tags);
		$harborat_tags = array_values($harborat_tags);
		$harborat_tags = implode(',', $harborat_tags);
		update_option('harborat_tags', $harborat_tags);
	} else {
		delete_option('harborat_tags');
	}

	if ($harborat_force == 'posts' || $harborat_posts) {
		if ($harborat_posts) {
			$harborat_force = 'posts';
			$harborat_posts = preg_replace('/[^0-9,]/','',$harborat_posts);
			$harborat_posts = explode(',',$harborat_posts);
			foreach ($harborat_posts as $key => $m) { $harborat_posts[$key] = (ctype_digit($m)) ? $m : false; }
			$harborat_posts = array_filter($harborat_posts);
			$harborat_posts = array_values($harborat_posts);
			$harborat_posts = implode(',', $harborat_posts);
			if (empty($harborat_posts)) {
				$default = "To run the Harbor Autotagger on specific posts you must enter a comma delimited list of post IDs in the box provided. The list must consist of only numbers and commas.";
			} else {
				update_option('harborat_posts', $harborat_posts);
				update_option('harborat_force', $harborat_force);
			}
		} else {
			$default = "To run the Harbor Autotagger on specific posts you must enter a comma delimited list of post IDs in the box provided. The list must consist of only numbers and commas.";
			delete_option('harborat_posts');
		}
	} else {
		update_option('harborat_force', $harborat_force);
	}

	update_option('harborat_notice', $default);
	echo $default;

} else {

	global $wpdb;

	$harborat_unique = get_option('harborat_unique');
	$harborat_force = get_option('harborat_force');
	$harborat_notice = get_option('harborat_notice');

	if (empty($harborat_unique) && empty($harborat_force)) {
		echo 'Autotagging complete.';
	} else {
		echo $harborat_notice;
	}

}

