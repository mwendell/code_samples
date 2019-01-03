<?php

/**
 * Script to update from older Usermeta entitlements to new Entitlement table
 * Version: 0.2
 */

// RUN THIS
// [site-url]/wp-content/plugins/harbor-entitlement-manager/cli-update-entitlements.php

$em = "mwndll@gmail.com";
$ts = true; // troubleshooting in the html

date_default_timezone_set('America/New_York');
$now = time();

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');
global $wpdb;

$wp_ = ($wpdb->prefix) ? $wpdb->prefix : 'wp_';

// CREATE TABLES IF NECESSARY

	$wpdb->query("CREATE TABLE IF NOT EXISTS {$wp_}harbor_entitlements (
		id INT(11) NOT NULL AUTO_INCREMENT,
		user_id INT(11) NOT NULL,
		pub_id varchar(16) NOT NULL,
		channel varchar(8) NOT NULL DEFAULT 'web',
		issue_id INT(11) NOT NULL DEFAULT '0',
		expires INT(11) NOT NULL DEFAULT '0',
		parent_id INT(11) NOT NULL DEFAULT '0',
		PRIMARY KEY  (id),
		KEY user_id (user_id)
		);");

	$wpdb->query("CREATE TABLE IF NOT EXISTS {$wp_}harbor_entitlements_refreshed (
		user_id int(11) NOT NULL,
		refreshed int(11) NOT NULL DEFAULT '0',
		PRIMARY KEY  (user_id),
		UNIQUE KEY user_id (user_id)
		);");



// GET UNCONVERTED USERS

	$sql = "SELECT m.user_id, m.meta_value, e.user_id AS id
	FROM {$wp_}usermeta m LEFT OUTER JOIN {$wp_}harbor_entitlements_refreshed e ON m.user_id = e.user_id
	WHERE (m.meta_key = 'entitlements') AND (e.user_id IS NULL) ORDER BY m.user_id LIMIT 5000;";
	$users = $wpdb->get_results($sql, ARRAY_A);

	if (empty($users)) { echo 'Done.'; }

// PROCESS ENTITLEMENT DATA

	foreach ($users as $u) {
		$user_id = $u['user_id'];
		$entitlements = unserialize($u['meta_value']);

		$refreshed = ($entitlements['refreshed']) ? $entitlements['refreshed'] : 0;
		if (($key = array_search('refreshed', $entitlements)) !== false) { unset($entitlements[$key]); }
		//if ($refreshed > 0) {
			$sql = "INSERT INTO {$wp_}harbor_entitlements_refreshed (user_id, refreshed) VALUES ({$user_id}, {$refreshed})
			ON DUPLICATE KEY UPDATE refreshed = {$refreshed};";
			$wpdb->query($sql);
		//}

		$values = array();
		$sql = "INSERT INTO {$wp_}harbor_entitlements (user_id, pub_id, channel, expires) VALUES ";

		foreach ($entitlements as $pub_id => $channels) {
			if (!empty($pub_id)) {
				foreach ($channels as $channel => $expires) {
					$values[] = "({$user_id}, '{$pub_id}', '{$channel}', {$expires})";
				}
			}
		}

		if (!empty($values)) {
			$sql .= implode(',', $values).";";
			$wpdb->query($sql);
		}
		echo "(".$user_id.") ";
	}
