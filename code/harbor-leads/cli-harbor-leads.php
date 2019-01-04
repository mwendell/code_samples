<?php

/**
 * Cron Program for Emailing Leads to Free Download Sponsors
 * Version: 0.9.4
 */

// TROUBLESHOOTING
// https://[URL]/wp-content/plugins/harbor-lead-manager/cli-harbor-lead-manager.php

// LOAD WORDPRESS
$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');

// DISABLE CACHING
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHEOBJECT', true);

$tm = 'mwndll@gmail.com';
$ts = (defined('TEST_CRONS')) ? TEST_CRONS : false; // troubleshooting in the html
$rp = true; // send email report

// ONLY RUN IN CRON (OR IN TESTING IF $ts TRUE)

if ((php_sapi_name() == 'cli')||($ts)) {

	$start = microtime();

	// BEGIN REPORT
	$site = get_bloginfo('name');
	$report = $site."\r\nHarbor Lead Manager\r\n\r\n";

	// MESSAGE PIECES
	$default_msg = "Attached you will find the most recent leads generated by your free report sponsorship:\n";
	$default_msg .= "%%REPORT%%\n\n";
	$default_msg .= "Thank you, and please let us know if you have any questions.\n\n";
	$default_from_email = "info@kwyjibo.com";
	$default_from_name = "Harbor";
	$default_subject = "Leads from Harbor";

	$settings = get_option('harbor_leadmgr');

	$message = ($settings['message']) ? $settings['message'] : $default_msg;
	$from_email = ($settings['from_email']) ? $settings['from_email'] : $default_from_email;
	$from_name = ($settings['from_name']) ? $settings['from_name'] : $default_from_name;
	$subject = ($settings['subject']) ? $settings['subject'] : $default_subject;
	$exclude_countries = ($settings['exclude_countries']) ? $settings['exclude_countries'] : array();

	$multipartSep = '-----'.md5(time()).'-----';

	$headers = "From: ".$from_name." <".$from_email.">\n";
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Content-Type: multipart/mixed; boundary=\"".$multipartSep."\"";

	$type_query = "SELECT type, id FROM wp_harbor_transaction_types;";
	$types = $wpdb->get_results($type_query, OBJECT_K);

	$now = time();
	$recent = $now-(7*24*60*60);
	$recent = date('Y-m-d', $recent);

	$active_reports = $counts_sql = array();

	$post_types = array('harbor_downloads','harbor_fr_online');
	if (array_key_exists('request', $types)) {
		$post_types[] = 'cprofiles';
	}

	$args = array(
		'posts_per_page'	=> -1,
		'post_type'			=> $post_types,
		'post_status'		=> 'publish',
	);
	$pre_fetch = get_posts( $args );

	// We could shortcut the pre_fetch by quite a bit if we assume that the itemId for views is
	// always the same as the post_title for the download post. It seems to be, but if it's off
	// by a single letter then the followup query that gathers leads (or counts) will fail.
	// let's not make that assumption, unless we see performance issues.

	foreach ( $pre_fetch as $pf ) {
		$active_reports[$pf->ID]['post_title'] = $pf->post_title;
		switch ( $pf->post_type ) {
			case 'harbor_downloads':
				$active_reports[$pf->ID]['download'] = get_post_meta($pf->ID, 'file_name', true);
				break;
			case 'harbor_fr_online':
				$active_reports[$pf->ID]['view'] = get_post_meta($pf->ID, 'rclp_linkback', true);
				break;
			case 'cprofiles':
				$active_reports[$pf->ID]['request'] = $pf->ID;
				break;
		}
	}

	$counts_base_sql = "SELECT %s AS type, %d AS ID, user_id as ct, %s AS filename, %s AS post_title
		FROM wp_harbor_transactions
		WHERE (";

	$report .= "Active Reports with View, Download, or Request Transactions\r\n\r\n";

	foreach ( $active_reports as $post_id => $ar ) {

		if ( $ar['post_title'] || $ar['download'] ) {
			$report .= "    &bull; ".$ar['post_title'];
			if ($ar['download']) { $report .= " (".$ar['download'].")"; }
			if ($ar['request']) { $report .= " (".$ar['request'].")"; }
			$report .= "\r\n";
		}

		$x = array();
		$t = 'view';
		$filename = ( $ar['download'] ) ? $ar['download'] : '';
		if ( $ar['view'] ) { $x[] = "((typeId = ".$types[view]->id.") AND (itemId = '".$ar['view']."'))"; }
		if ( $ar['download'] ) { $x[] = "((dt > '".$recent."') AND (typeId = ".$types[download]->id.") AND (itemId = '".$ar['download']."'))"; }
		if ( $ar['request'] ) { $x[] = "((typeId = ".$types['request']->id.") AND (itemId = '".$ar['request']."'))"; $t = 'request'; }
		if ( !empty($x) ) {
			$x = implode(' OR ', $x);
			$cs = $wpdb->prepare($counts_base_sql, $t, $post_id, $filename, $ar['post_title']);
			if ( $x != ' OR ' ) {
				$counts_sql[] = $cs . $x . ") LIMIT 1";
			}
		}
	}

	if ( !empty($counts_sql) ) {
		$counts_sql_final = "(".implode(') UNION (', $counts_sql).")";
	}

	$count_query = $counts_sql_final . " ORDER BY type, ct DESC";

	$results = $wpdb->get_results($count_query);

	$report .= "\r\n\r\nReports queried for Leads:\r\n";

	if (!empty($results)) {
		foreach ($results as $r) {
			
			$sendleads = true;
			$reason = '';

			$postid = $r->ID;
			$title = ($r->post_title) ? $r->post_title : 'missing';
			$filename = ($r->filename) ? $r->filename : 'missing';
			$item_id = array($title, $filename, $postid);
			$dl_count = intval($r->ct);
			$dateformat = "%Y-%c-%e";

			$query = "SELECT meta_key, meta_value AS val FROM wp_postmeta WHERE (meta_key LIKE 'hlm_%') AND (post_id = ".$postid.");";
			$meta = $wpdb->get_results($query, OBJECT_K);

			$em = array(
				trim($meta[hlm_sponsor_email_1]->val),
				trim($meta[hlm_sponsor_email_2]->val),
				trim($meta[hlm_sponsor_email_3]->val),
				trim($meta[hlm_sponsor_email_4]->val)
				);

			$em = array_filter($em);

			$addl_data = $meta[hlm_sponsor_addl_data]->val ? true : false ;

			foreach ($em as $key => $e) {
				if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
					unset($em[$key]);
				}
			}

			// no item id
			if (empty($item_id)) { $sendleads = false; $reason .= 'no item_id (filename or post_title), '; }

			// no downloads
			if (empty($dl_count)) { $sendleads = false; $reason .= 'no view/downloads, '; }

			// no email addresses
			if (!$em) { $sendleads = false; $reason .= 'no sponsors, '; }

			$report .= "\r\n    ".$title."\r\n";

			if ($sendleads) {

				if ($addl_data) {
					$leadquery = $wpdb->prepare("SELECT DISTINCT
						u.user_email AS email,
						a.meta_value AS first,
						b.meta_value AS last,
						c.meta_value AS phone,
						d.meta_value AS company,
						e.meta_value AS title,
						f.meta_value AS state,
						g.meta_value AS country,
						i.meta_value AS ip_address,
						DATE_FORMAT(t.dt, %s) AS date
					FROM
						{$wpdb->prefix}harbor_transactions t
						JOIN {$wpdb->prefix}users u ON t.user_id = u.ID
						LEFT JOIN {$wpdb->prefix}usermeta a ON u.ID = a.user_id AND a.meta_key = 'first_name'
						LEFT JOIN {$wpdb->prefix}usermeta b ON u.ID = b.user_id AND b.meta_key = 'last_name'
						LEFT JOIN {$wpdb->prefix}usermeta c ON u.ID = c.user_id AND c.meta_key = 'phone'
						LEFT JOIN {$wpdb->prefix}usermeta d ON u.ID = d.user_id AND d.meta_key = 'company'
						LEFT JOIN {$wpdb->prefix}usermeta e ON u.ID = e.user_id AND e.meta_key = 'title'
						LEFT JOIN {$wpdb->prefix}usermeta f ON u.ID = f.user_id AND f.meta_key = 'state'
						LEFT JOIN {$wpdb->prefix}usermeta g ON u.ID = g.user_id AND g.meta_key = 'country'
						LEFT JOIN {$wpdb->prefix}usermeta i ON u.ID = i.user_id AND i.meta_key = 'ip_address'
					WHERE
						(t.itemId IN (%s, %s, %s))
						AND (t.dt > %s);", $dateformat, $item_id[0], $item_id[1], $item_id[2], $recent);
				} else {
					$leadquery = $wpdb->prepare("SELECT DISTINCT
						u.user_email AS email,
						f.meta_value AS first,
						l.meta_value AS last,
						p.meta_value AS phone,
						s.meta_value AS state,
						c.meta_value AS country,
						i.meta_value AS ip_address,
						DATE_FORMAT(t.dt, %s) AS date
					FROM
						{$wpdb->prefix}harbor_transactions t
						JOIN {$wpdb->prefix}users u ON t.user_id = u.ID
						LEFT JOIN {$wpdb->prefix}usermeta f ON u.ID = f.user_id AND f.meta_key = 'first_name'
						LEFT JOIN {$wpdb->prefix}usermeta l ON u.ID = l.user_id AND l.meta_key = 'last_name'
						LEFT JOIN {$wpdb->prefix}usermeta p ON u.ID = p.user_id AND p.meta_key = 'phone'
						LEFT JOIN {$wpdb->prefix}usermeta s ON u.ID = s.user_id AND s.meta_key = 'state'
						LEFT JOIN {$wpdb->prefix}usermeta c ON u.ID = c.user_id AND c.meta_key = 'country'
						LEFT JOIN {$wpdb->prefix}usermeta i ON u.ID = i.user_id AND i.meta_key = 'ip_address'
					WHERE
						(t.itemId IN (%s, %s, %s))
						AND (t.dt > %s);", $dateformat, $item_id[0], $item_id[1], $item_id[2], $recent);
				}

				$leads = $wpdb->get_results($leadquery);

				$lead_count = count($leads);

				if ($lead_count > 0) {

					$data = array();
					$csv = '';
					$handle = false;

					if ($addl_data) {
						$data[] = array('Email','First','Last','Phone','Company','Title','State','Country','IP','Date');
					} else {
						$data[] = array('Email','First','Last','Phone','State','Country','IP','Date');
					}
					$count = 0;
					foreach ($leads as $l) {
						if (strpos($l->email, 'kwyjibo.com') === false){
							if ($addl_data) {
								$data[] = array($l->email,$l->first,$l->last,$l->phone,$l->company,$l->title,$l->state,$l->country, $l->ip_address, $l->date);
							} else {
								$data[] = array($l->email,$l->first,$l->last,$l->phone,$l->state,$l->country,$l->ip_address, $l->date);
							}
							$count++;
						}
					}

					// no more manual concatenation of CSV!
					// http://tlok.eu/fputcsv-in-php-and-writing-content-to-variable.html
					$handle = fopen('php://memory', 'w');
					foreach ($data as $d) { fputcsv($handle, $d, ','); }
					fseek($handle, 0);
					$csv = stream_get_contents($handle);
					$csv = mb_convert_encoding($csv, 'iso-8859-2', 'utf-8');

					$filedate = date('Y-m-d');

					foreach($em as $key => $e) {
						$subject = $subject;
						$new_message = str_replace('%%REPORT%%', $title, $message);
						$body = "-This is a multipart message in MIME format.\n";
						$body .= "--".$multipartSep."\n";
						$body .= "Content-Type: text/plain; charset=ISO-8859-1; format=flowed\n";
						$body .= "Content-Transfer-Encoding: 7bit\n";
						$body .= "\n";
						$body .= $new_message;
						$body .= "--".$multipartSep."\n";
						$body .= "Content-Type: application/vnd.ms-excel; name=\"leads-".$filedate.".csv\"\n";
						$body .= "Content-Transfer-Encoding: 7bit\n";
						$body .= "Content-Disposition: attachment; filename=\"leads-".$filedate.".csv\"\n";
						$body .= "\n";

						$body .= $csv;
						$report .= "     &bull; ".$e." received ".$count." lead(s)\n";

						$body .= "--".$multipartSep."\n";

						mail($e,$subject,$body,$headers);

					}

				} else {

					$report .= "     &bull; No leads found since ".$recent."\n";

				}

			} else {

				$report .= "     &bull; Leads not sent (".substr($reason,0,-2).")\n";

			}
		}

	}

	$end = microtime();
	$execution_time = round(($end - $start), 2);

	if ($ts) { $report .= "\r\n\r\nSCRIPT TIME: ".$execution_time." SECONDS\r\n\r\n"; }

	$report = "<html><body><pre>\r\n".$report."\r\n</pre></body></html>";

	if ($ts) { echo $report; }

	$html_email = "-This is a multipart message in MIME format.\n";
	$html_email .= "--".$multipartSep."\n";
	$html_email .= "Content-Type: text/html; charset='UTF-8'\n";
	$html_email .= "Content-Transfer-Encoding: 8bit\n";
	$html_email .= "\n";

	if ($rp) { mail($tm, "Harbor Leads Report", $html_email.$report, $headers); }

} // if CRON or $ts

?>