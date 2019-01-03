<?php

/**
 * Cron Program for Harbor Republish
 * Version: 0.6
 */

// TROUBLESHOOTING
//[site-url]/wp-content/plugins/harbor-republish/cli-harbor-republish.php

$em = "mwndll@gmail.com";
$ts = false; // troubleshooting in the html
$te = false; // troubleshooting via email
$rp = false; // send email report

// ONLY RUN IN CRON (or HTML mode)

if ((php_sapi_name() == 'cli')||($ts)) {

	if ($ts) { $report = "<html><body><pre>[REPUB]<br/>STARTED<br/>"; }

	$root = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($root . '/wp-load.php');

	$timezone_string = get_option('timezone_string');

	$timezone_string = (empty($timezone_string)) ? 'America/New_York' : $timezone_string;

	date_default_timezone_set($timezone_string);

	$now = time();

	$sql = "SELECT m.post_id, m.meta_value, p.post_date FROM wp_postmeta m INNER JOIN wp_posts p ON m.post_id = p.ID WHERE (m.meta_key = 'republish_date') AND (m.meta_value < '".$now."') ORDER BY m.meta_value ASC;";

	$results = $wpdb->get_results($sql, ARRAY_A);

	$report .= "MAIN SQL: ".$sql."<br/><br/>";
	$report .= print_r($results, true)."<br/><br/>";

	if ($results) {
		foreach ($results as $r) {
			$id = intval($r[post_id]);
			$time = intval($r[meta_value]);
			if ($time < $now) {
				$original_time = strtotime($r[post_date]);
				$history = get_post_meta($id, 'republish_history', true);
				$history = ($history) ? $history .= ','.$time : $original_time.','.$time ;
				update_post_meta($id, 'republish_history', $history);
				delete_post_meta($id, 'republish_date');
				$post_date = date('Y-m-d H:i:s', $time);
				$post_date_gmt = get_gmt_from_date($post_date, 'Y-m-d H:i:s');
				$sql = "UPDATE wp_posts SET post_date = '".$post_date."', post_date_gmt = '".$post_date_gmt."' WHERE (ID = ".$id.");";
				$success = $wpdb->query($sql);
				if ($success === false) {
					$report .= $sql.' RETURNED AN ERROR<br/>';
				} else {
					$d_sql = "DELETE a,b,c FROM wp_posts a LEFT JOIN wp_term_relationships b ON (a.ID = b.object_id) LEFT JOIN wp_postmeta c ON (a.ID = c.post_id) WHERE (a.post_type = 'revision') AND (post_parent = ".$id.");";
					$d_success = $wpdb->query($d_sql);
					if ($d_success === false) {
						$report .= 'POST '.$id.' UPDATED. REVISIONS NOT DELETED.<br/>';
					} else {
						$report .= 'POST '.$id.' UPDATED, REVISIONS DELETED.<br/>';
					}
				}
			}
		}
		$cache_flushed = wp_cache_flush();
		if ($cache_flushed) {
			$report .= '<br/>CACHE SUCCESSFULLY FLUSHED.<br/>';
		} else {
			$report .= '<br/>CACHE FLUSH FAILED.<br/>';
		}
	} else {
		$report .= 'RESULTS: none found';
	}

	if ($ts) { echo $report; }
	if ($te) { mail($em, 'REPUB Report', $report); }

}
?>
