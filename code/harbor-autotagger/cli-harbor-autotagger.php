<?php

/**
 * Name: Cron Program for Harbor Autotagger
 * Version: 1.4
 */

// RUN CRON MINUTELY

$debug_email = 'mwndll@gmail.com';
$debug = false;

if (php_sapi_name() == 'cli') {

	// LOAD WORDPRESS

	$time_start = microtime(true);
	$total_time = false;

	$relationships = 0;

	$echo_reports = false;
	$report = '';

	$root = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($root . '/wp-load.php');

	$timezone = get_option('timezone_string');
	date_default_timezone_set($timezone);

	global $wpdb;

	$defaults = array(
		'harborat_enable'	=> false,
		'harborat_runat'	=> false,
		'harborat_force'	=> false,
		'harborat_unique'	=> false,
		'harborat_filter'	=> 'daily',
		'harborat_total'	=> 0,
		'harborat_done'		=> 0,
		'harborat_secs'		=> 0,
		'harborat_email'	=> false,
		'harborat_types'	=> array('post', 'page'),
		'harborat_temps'	=> false,
		'harborat_ping'		=> false,
		'harborat_posts'	=> false,
		'harborat_tags'		=> false,
		'harborat_matches'	=> false,
	);

	$args = $meta = array();
	$meta_results = $wpdb->get_results("SELECT option_name, option_value AS val FROM wp_options WHERE (option_name LIKE 'harborat_%')", OBJECT_K);
	foreach ($meta_results as $key => $m) { $args[$key] = $m->val; }

	$meta = wp_parse_args($args, $defaults);

	$harborat_enable = $meta['harborat_enable'];
	$harborat_runat = $meta['harborat_runat'];
	$harborat_force = $meta['harborat_force'];
	$harborat_unique = $meta['harborat_unique'];
	$harborat_filter = $meta['harborat_filter'];
	$harborat_total = $meta['harborat_total'];
	$harborat_done = $meta['harborat_done'];
	$harborat_secs = $meta['harborat_secs'];
	$harborat_email = $meta['harborat_email'];
	$harborat_temps = $meta['harborat_temps'];
	$harborat_ping = $meta['harborat_ping'];
	$harborat_posts = $meta['harborat_posts'];
	$harborat_tags = $meta['harborat_tags'];
	$harborat_matches = $meta['harborat_matches'];
	$harborat_types = maybe_unserialize($meta['harborat_types']);

	$activate = ($harborat_force || $harborat_unique) ? true : false;
	$filter = ($harborat_force) ? $harborat_force : $harborat_filter;

	if ($harborat_enable && $harborat_runat == date('H:i')) {
		$activate = true;
		$filter = $harborat_filter;
		update_option('harborat_disable', true);
	}

	if ($debug) {
		$debug_body = 'timezone: '.$timezone."\r\n";
		$debug_body .= 'harborat_enable: '.$harborat_enable."\r\n";
		$debug_body .= 'harborat_runat: '.$harborat_runat." - (NOW: ".date('H:i').")\r\n";
		$debug_body .= 'harborat_force: '.$harborat_force."\r\n";
		$debug_body .= 'harborat_unique: '.$harborat_unique."\r\n";
		$debug_body .= 'harborat_filter: '.$harborat_filter."\r\n";
		$debug_body .= 'harborat_total: '.$harborat_total."\r\n";
		$debug_body .= 'harborat_done: '.$harborat_done."\r\n";
		$debug_body .= 'harborat_secs: '.$harborat_secs."\r\n";
		$debug_body .= 'harborat_email: '.$harborat_email."\r\n";
		$debug_body .= 'harborat_body: '.$harborat_body."\r\n";
		$debug_body .= 'harborat_temps: '.$harborat_temps."\r\n";
		$debug_body .= 'harborat_ping: '.$harborat_ping."\r\n";
		$debug_body .= 'harborat_posts: '.$harborat_posts."\r\n";
		$debug_body .= 'harborat_tags: '.$harborat_tags."\r\n";
		$debug_body .= 'harborat_matches: '.$harborat_matches."\r\n";
		$debug_body .= 'activate: '.$activate."\r\n";
		$debug_body .= 'harborat_types: '.print_r($harborat_types, 1)."\r\n\r\n";
		$debug_body .= print_r($meta, 1);
		mail($debug_email, 'autotagger debug email', $debug_body);
	}

	if ($activate) {

		if (!$harborat_unique) {

			// ====================================================================================
			// NO HBAT_UNIQUE, NO TEMP TABLES, TIME TO SET UP
			
			$report = 'SETUP - ';

			// ------------------------------------------------------------------------------------
			// CREATE TEMPORARY TABLES

			//update_option('harborat_notice', 'Beginning setup. Creating temporary tables.');

			$harborat_secs = 0;
			$harborat_unique = time();
			update_option('harborat_unique', $harborat_unique);
			delete_option('harborat_force');
			$sql = "CREATE TABLE IF NOT EXISTS temp_autotagger_tags_".$harborat_unique." (
				term_taxonomy_id BIGINT(20) NOT NULL,
				term_id BIGINT(20) NOT NULL,
				name VARCHAR(200) NOT NULL,
				posts VARCHAR(2000) NULL,
				PRIMARY KEY (term_taxonomy_id)
				) COMMENT '".date('F j, Y, g:i a', $harborat_unique)."';";
			$wpdb->query($sql);
			$sql = "CREATE TABLE IF NOT EXISTS temp_autotagger_posts_".$harborat_unique." (
				post_id BIGINT(20) NOT NULL,
				post_content LONGTEXT NOT NULL,
				old_tags VARCHAR(600) NULL,
				new_tags VARCHAR(600) NULL,
				PRIMARY KEY (post_id)
				) ENGINE = MyISAM, COMMENT '".date('F j, Y, g:i a', $harborat_unique)."';";
			$wpdb->query($sql);
			
			// ------------------------------------------------------------------------------------
			// POPULATE TAG TABLE

			//update_option('harborat_notice', 'Populating tag table.');

			/*$sql = "INSERT INTO temp_autotagger_tags_".$harborat_unique." (term_taxonomy_id, term_id, name)
				SELECT x.term_taxonomy_id, x.term_id, CONCAT(' ', TRIM(LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(t.name,'''',' '),'-',' '),':',' '),'.',' '),'  ',' '))), ' ')
				FROM wp_term_taxonomy x
				JOIN wp_terms t ON x.term_id = t.term_id
				WHERE (x.taxonomy LIKE 'post_tag') AND (TRIM(t.name) <> '');";*/
			if ($harborat_tags) {
				$sql = "INSERT INTO temp_autotagger_tags_".$harborat_unique." (term_taxonomy_id, term_id, name)
					SELECT x.term_taxonomy_id, x.term_id, CONCAT(' ', TRIM(LOWER(REPLACE(t.slug, '-', ' '))), ' ')
					FROM wp_term_taxonomy x
					JOIN wp_terms t ON x.term_id = t.term_id
					WHERE (x.taxonomy LIKE 'post_tag') AND (x.term_id IN (".$harborat_tags.")) AND (TRIM(t.name) <> '');";
				delete_option('harborat_tags');
			} else {
				$sql = "INSERT INTO temp_autotagger_tags_".$harborat_unique." (term_taxonomy_id, term_id, name)
					SELECT x.term_taxonomy_id, x.term_id, CONCAT(' ', TRIM(LOWER(REPLACE(t.slug, '-', ' '))), ' ')
					FROM wp_term_taxonomy x
					JOIN wp_terms t ON x.term_id = t.term_id
					WHERE (x.taxonomy LIKE 'post_tag') AND (TRIM(t.name) <> '');";
				delete_option('harborat_posts');
			}
			$wpdb->query($sql);

			// ------------------------------------------------------------------------------------
			// POPULATE POST TABLE

			//update_option('harborat_notice', 'Populating post table.');

			// remove CONCAT in queries: CONCAT(' ', TRIM(LOWER(p.post_content))

			$post_types = "'".implode("', '", $harborat_types)."'";

			if ($filter == 'daily') {
				$yesterday = date('Y-m-d', strtotime('-1 day'));
				$sql = "INSERT INTO temp_autotagger_posts_".$harborat_unique." (post_id, post_content) 
					SELECT p.ID AS post_id, TRIM(LOWER(p.post_content))
					FROM wp_posts p
					WHERE (p.post_status IN ('draft', 'pending', 'future', 'publish'))
					AND (p.post_type IN (".$post_types."))
					AND (CHAR_LENGTH(p.post_content) > 4)
					AND ((p.post_date > '".$yesterday." 00:00:00') OR (p.post_modified > '".$yesterday." 00:00:00'));";
			} else if ($filter == 'recent') {
				$a_week_ago = date('Y-m-d', strtotime('-1 week'));
				$sql = "INSERT INTO temp_autotagger_posts_".$harborat_unique." (post_id, post_content) 
					SELECT p.ID AS post_id, TRIM(LOWER(p.post_content))
					FROM wp_posts p
					WHERE (p.post_status IN ('draft', 'pending', 'future', 'publish'))
					AND (p.post_type IN (".$post_types."))
					AND (CHAR_LENGTH(p.post_content) > 4)
					AND ((p.post_date > '".$a_week_ago." 00:00:00') OR (p.post_modified > '".$a_week_ago." 00:00:00'));";
			} else if ($filter == 'tagless') {
				$sql = "INSERT INTO temp_autotagger_posts_".$harborat_unique." (post_id, post_content) 
					SELECT p.ID AS post_id, TRIM(LOWER(p.post_content))
					FROM wp_posts p
					WHERE (p.post_status IN ('draft', 'pending', 'future', 'publish'))
					AND (p.post_type IN (".$post_types."))
					AND (CHAR_LENGTH(p.post_content) > 4)
					AND (p.ID NOT IN (SELECT DISTINCT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM temp_autotagger_tags_".$harborat_unique.")));";
			} else if ($filter == 'posts') {
				$sql = "INSERT INTO temp_autotagger_posts_".$harborat_unique." (post_id, post_content) 
					SELECT p.ID AS post_id, TRIM(LOWER(p.post_content))
					FROM wp_posts p
					WHERE (p.ID IN (".$harborat_posts."));";
				delete_option('harborat_posts');
			} else {
				$sql = "INSERT INTO temp_autotagger_posts_".$harborat_unique." (post_id, post_content) 
					SELECT p.ID AS post_id, TRIM(LOWER(p.post_content))
					FROM wp_posts p 
					WHERE (p.post_status IN ('draft', 'pending', 'future', 'publish'))
					AND (p.post_type IN (".$post_types."))
					AND (CHAR_LENGTH(p.post_content) > 4);";
			}
			$wpdb->query($sql);

			// ------------------------------------------------------------------------------------
			// STRIP CRUFT FROM POST_CONTENT

			//update_option('harborat_notice', 'Temporary tables created. Stripping HTML and punctuation.');

			$sql = "SELECT COUNT(post_id) FROM temp_autotagger_posts_".$harborat_unique.";";
			$count = $wpdb->get_var($sql);

			update_option('harborat_total', count($results));

			$loops = $count/1000;

			for ($i = 0; $i <= $loops; $i++) {

				$start = $i * 1000;

				$sql = "SELECT post_id, LOWER(TRIM(post_content)) AS post_content FROM temp_autotagger_posts_".$harborat_unique." ORDER BY post_id LIMIT ".$start.", 1000;";

				$results = $wpdb->get_results($sql, ARRAY_A);

				$preg_start = microtime(true);
				$errors = array();

				if ($results) {
					foreach ($results as $r) {
						$id = $r['post_id'];
						$pcf = $r['post_content'];
						$pcf = strip_tags($pcf);
						$pcf = preg_replace('/[^A-Za-z0-9]/', ' ', $pcf);
						$pcf = preg_replace('/\s+/', ' ', $pcf);
						$sql = "UPDATE temp_autotagger_posts_".$harborat_unique." SET post_content = ' ".$pcf." ' WHERE (post_id = ".$id.");";
						$error = $wpdb->query($sql);
						if ($error === false) { $errors[] = $id; }
					}
				}

				$preg_time = round((microtime(true) - $preg_start), 2);

			}

		} else {

			// ========================================================================================
			// WE HAVE AN HBAT_UNIQUE, TEMP TABLES DONE, BEGIN THE AUTOTAGGING

			$report = 'AUTOTAGGING - ';

			// ----------------------------------------------------------------------------------------
			// PULL 100 NEW POSTS

			$base_sql = "SELECT post_id from temp_autotagger_posts_".$harborat_unique." WHERE new_tags IS NULL LIMIT 100;";
			$posts = $wpdb->get_col($base_sql);

			update_option('harborat_done', count($posts) + $harborat_done);

			if ($posts) {

				if ($harborat_done == 0) {
					//update_option('harborat_notice', 'The autotagging loop has started.');
				} else {
					//update_option('harborat_notice', 'The autotagging loop is running. Processed '.$harborat_done.' of '.$harborat_total.' posts.');
				}

				// ------------------------------------------------------------------------------------
				// TAKE THESE POSTS OUT OF CIRCULATION (REPLACE NULL)

				$sql = "UPDATE temp_autotagger_posts_".$harborat_unique." SET new_tags = 'none' WHERE (post_id IN (".implode(', ', $posts)."));";

				$wpdb->query($sql);

				// ------------------------------------------------------------------------------------
				// SQL JOIN POSTS WITH TAGS

				// SHORTER TO USE TERM_TAXONOMY_ID IF INSERTING TAGS WITH SQL, CODE BELOW IS SET TO USE TERM_TAXONOMY_ID.
				//$sql = "SELECT p.post_id, t.term_taxonomy_id FROM temp_autotagger_posts_".$harborat_unique." p JOIN temp_autotagger_tags_".$harborat_unique." t ON p.post_content LIKE CONCAT('%', t.name, '%') WHERE (p.post_id IN (".implode(', ', $posts).")) ORDER BY p.post_id;";

				// MUST USE TERM_ID IF INSERTING TAGS WITH wp_set_object_terms();
				$sql = "SELECT p.post_id, t.term_id FROM temp_autotagger_posts_".$harborat_unique." p JOIN temp_autotagger_tags_".$harborat_unique." t ON p.post_content LIKE CONCAT('%', t.name, '%') WHERE (p.post_id IN (".implode(', ', $posts).")) ORDER BY p.post_id;";

				$tags = $wpdb->get_results($sql, ARRAY_A);

				$report .= ' (MATCHES: '.count($tags).") ";

				$these_matches = count($tags);		
				update_option('harborat_matches', $these_matches + $harborat_matches);		

				if ($tags) {

					$output = array();

					foreach ($tags as $t) {
						//$output[$t['post_id']][] = $t['term_taxonomy_id'];
						$output[$t['post_id']][] = $t['term_id'];
					}

					// ------------------------------------------------------------------------------------
					// SAVE EACH POST TAGS TO TEMP TABLE

					foreach ($output as $post_id => $o) {
						$tag_string = implode(',', $o);
						$sql = "UPDATE temp_autotagger_posts_".$harborat_unique." SET new_tags = '".$tag_string."' WHERE (post_id = ".$post_id.");";
						$wpdb->query($sql);
					}
				}
			
			} else {

				// ========================================================================================
				// NO NULL POSTS FOUND, COPY DATA FROM TEMP TABLES TO REAL TABLES

				$report = 'MOVING DATA - ';

				//update_option('harborat_notice', 'Autotagged '.$harborat_total.' posts. Moving data from temp tables to working database.');

				// ----------------------------------------------------------------------------------------
				// PULL 10,000 POSTS

				$base_sql = "SELECT post_id, new_tags from temp_autotagger_posts_".$harborat_unique." WHERE (new_tags IS NOT NULL) AND NOT (new_tags = 'none') AND NOT (new_tags LIKE 'done %') LIMIT 10000;";
				$posts = $wpdb->get_results($base_sql, ARRAY_A);

				if ($posts) {
					$post_sql = array();

					// ----------------------------------------------------------------------------------------
					// UPDATE wp_term_relationships TABLE

					foreach ($posts as $p) {

						$post_id = $p['post_id'];
						$tags = array_map('intval', explode(',', $p['new_tags']));

						if ($post_id && $tags) {
							// USES TERM_ID - SEE LINE 303
							$success = wp_set_object_terms($post_id, $tags, 'post_tag', true);
							if (is_array($success)) {
								$post_sql[] = $post_id;
							}
						} else {
							$post_sql[] = $post_id;
						}

						/*
						// USES TERM_TAXONOMY_ID - SEE LINE 300
						$tag_sql = array();
						if ($post_id && $tags) {
							$sql = "SELECT DISTINCT x.term_taxonomy_id FROM wp_term_taxonomy x JOIN wp_term_relationships r ON x.term_taxonomy_id = r.term_taxonomy_id AND x.taxonomy = 'post_tag' AND r.object_id = ".$post_id.";";
							$delete_tag_list = $wpdb->get_col($sql);
							if ($delete_tag_list) {
								$sql = "DELETE FROM wp_term_relationships WHERE (object_id = ".$post_id.") AND (term_taxonomy_id IN (".implode(', ', $delete_tag_list)."));";
								$wpdb->query($sql);
							}
							foreach ($tags as $t) {
								if (!empty($t)) {
									$tag_sql[] = "(".$post_id.", ".$t.")";
								}
							}
							$sql = "INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES ".implode(', ', $tag_sql).";";
							$rows = $wpdb->query($sql);
							if (!$rows === false) {
								$post_sql[] = $post_id;
							}
						} else {
							$post_sql[] = $post_id;
						}
						*/
					}
					
					// ----------------------------------------------------------------------------------------
					// DEACTIVATE THESE POSTS IN TEMP TABLE

					if ($post_sql) {
						$sql = "UPDATE temp_autotagger_posts_".$harborat_unique." SET new_tags = CONCAT('done ', new_tags) WHERE (post_id IN (".implode(',', $post_sql)."));";
						$wpdb->query($sql);
					}

				} else {

					// ========================================================================================
					// ALL VALID POSTS SAVED TO LIVE DATABASE
					
					$report = 'CLEANING UP - ';

					//update_option('harborat_notice', 'Finishing up. Removing temp tables.');

					$sql = "SELECT COUNT(post_id) AS x FROM temp_autotagger_posts_".$harborat_unique." WHERE (new_tags = 'none');";
					$skipped = $wpdb->get_var($sql);

					// ----------------------------------------------------------------------------------------
					// DROP TEMPORARY TABLES, DELETE RUNTIME OPTIONS

					if (!$debug) {
						$sql = "DROP TABLE temp_autotagger_posts_".$harborat_unique.";";
						$wpdb->query($sql);
						$sql = "DROP TABLE temp_autotagger_tags_".$harborat_unique.";";
						$wpdb->query($sql);
					}
					$sql = "DELETE FROM wp_options WHERE (option_name IN ('harborat_unique','harborat_disable','harborat_total','harborat_done', 'harborat_matches'));";
					$wpdb->query($sql);

					// ----------------------------------------------------------------------------------------
					// FORMAT ADMIN MESSAGE

					$script_time = round((microtime(true) - $time_start), 2);
					$process_time = gmdate("H:i:s", ($harborat_secs + $script_time));
					$total_time = gmdate("H:i:s", (time() - $harborat_unique));

					$found = $harborat_done - $skipped;
					$matches = $harborat_matches + $these_matches;		
					delete_option('harborat_matches');

					if ($harborat_done > 0) {
						$harborat_recent = "Harbor Autotagger processed {$harborat_done} posts/pages in {$total_time}, of which, the script was active for {$process_time}. ";
						if ($found > 0) { $harborat_recent .= "Found {$matches} matches in {$found} posts. "; } else { $harborat_recent .= "No matches found. "; }
					} else {
						$harborat_recent = "Harbor Autotagger process completed in {$total_time}, of which, the script was active for {$process_time}. No posts/pages processed.";
					}

					$harborat_recent = 'Cron was most recently run on '.date('l F j, Y').' at '.date('G:i').'<br/>'.$harborat_recent;

					//update_option('harborat_notice', 'Completed at '.date('G:i e').'. Process took '.$total_time.', during which the script was active for '.$process_time.'.');
					update_option('harborat_recent', $harborat_recent);

					if ($harborat_email) {
						wp_mail($harborat_email, 'Harbor Autotagger Report', str_replace('<br/>', '. ', $harborat_recent));
					}

					if ($harborat_ping) {
						wp_mail($harborat_ping, 'Your Harbor Autotagger process is complete', str_replace('<br/>', '. ', $harborat_recent));
						delete_option('harborat_ping');
					}

				}
			}
		}

		if (!$script_time) {
			$script_time = round((microtime(true) - $time_start), 2);
			$process_time = $harborat_secs + $script_time;
			update_option('harborat_secs', $process_time);
		}

		if ($report && $echo_reports) {
			echo $report.' DURATION: '.$script_time.' SECONDS (TOTAL: '.$process_time.')';
		}

		if ($total_time) {
			delete_option('harborat_secs');
		}

	} else if ($harborat_temps) {

		// ----------------------------------------------------------------------------------------
		// THERE'S A TEMP TAGS TABLE READY FOR IMPORT

		global $wpdb;

		$processed = array();

		$sql = "SELECT id, name FROM temp_autotagger_upload_".$harborat_temps." WHERE (processed = 0) LIMIT 500;";
		$tags = $wpdb->get_results($sql, ARRAY_A);
		if (empty($tags)) {
			update_option('harborat_temps', false);
			if (!$debug) {
				$sql = "DROP TABLE temp_autotagger_upload_".$harborat_temps.";";
				$wpdb->query($sql);
			}
		} else {
			foreach ($tags as $t) {
				$params = array();
				$taxonomy = 'post_tag';
				if ($id = term_exists($t['name'], $taxonomy)) {
					$params['name'] = $t['name'];
					$error = wp_update_term($id['term_id'], $taxonomy, $params);
					if (!is_wp_error($error)) { $processed[] = $t['id']; }
				} else {
					$error = wp_insert_term($t['name'], $taxonomy, $params);
					if (!is_wp_error($error)) { $processed[] = $t['id']; }
				}
			}
			if (!empty($processed)) {
				$sql = "UPDATE temp_autotagger_upload_".$harborat_temps." SET processed = processed + 1 WHERE (id IN (".implode(',', $processed)."));";
				$error = $wpdb->query($sql);
				if ($error === false) {
					mail('mwndll@gmail.com', 'bad update query in autotagger', $sql."\r\n\r\n".print_r($tags, true)."\r\n\r\n".print_r($processed, true));
				}
			}
		}

		$script_time = round((microtime(true) - $time_start), 2);
		$process_time = gmdate("H:i:s", ($harborat_secs + $script_time));
	}
	
}

