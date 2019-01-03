<?php

/**
 * Cron Program to BUILD emails for Harbor Newsletters
 * Version: 0.8
 */

// TROUBLESHOOTING
// http://[URL]/wp-content/plugins/harbor-newsletter-manager/cli-harbor-newsletter-builder.php

$em = 'mwndll@gmail.com';
$ts = true; // troubleshooting in the html
$rp = true; // send email report

// DISABLE ALL CACHE/CDN

define('DONOTCACHEDB', true);
define('DONOTCACHEPAGE', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHEOBJECT', true);

// ONLY RUN IN CRON (OR IN TESTING IF $ts TRUE)

if ((php_sapi_name() == 'cli')||($ts)) {

	$report = ($ts) ? "<pre>\n" : "";
	$report .= "[NEWSLETTER BUILDER - TOPICS]\n\n";

	// LOAD WORDPRESS

	$root = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($root . '/wp-load.php');
	global $wpdb;

	$subject = array(
	 'lif' => 'Life Sciences', 'lif_xx_genetics' => 'Genetics', 'lif_xx_medicaldevices' => 'Medical Devices', 'lif_xx_pharma' => 'Pharmaceuticals', 'lif_xx_biotech' => 'Biotech', 'lif_xx_longevity' => 'Longevity', 'res' => 'Resources', 'res_ag' => 'Agriculture', 'res_ag_cannabis' => 'Cannabis', 'res_ag_phosphate' => 'Phosphate', 'res_ag_potash' => 'Potash', 'res_bm' => 'Base Metals', 'res_bm_copper' => 'Copper', 'res_bm_iron' => 'Iron', 'res_bm_lead' => 'Lead', 'res_bm_nickel' => 'Nickel', 'res_bm_zinc' => 'Zinc', 'res_cm' => 'Critical Metals', 'res_cm_cobalt' => 'Cobalt', 'res_cm_graphite' => 'Graphite', 'res_cm_magnesium' => 'Magnesium', 'res_cm_manganese' => 'Manganese', 'res_cm_rareearth' => 'Rare Earth', 'res_cm_scandium' => 'Scandium', 'res_cm_tantalum' => 'Tantalum', 'res_cm_tellurium' => 'Tellurium', 'res_cm_tungsten' => 'Tungsten', 'res_en' => 'Energy', 'res_en_oilgas' => 'Oil & Gas', 'res_en_gas' => 'Gas', 'res_en_lithium' => 'Lithium', 'res_en_oil' => 'Oil', 'res_en_uranium' => 'Uranium', 'res_gm' => 'Gem', 'res_gm_diamonds' => 'Diamond', 'res_im' => 'Industrial Metals', 'res_im_aluminum' => 'Aluminum', 'res_im_chromium' => 'Chromium', 'res_im_coal' => 'Coal', 'res_im_moly' => 'Molybdenum', 'res_im_tin' => 'Tin', 'res_im_vanadium' => 'Vanadium', 'res_pm' => 'Precious Metals', 'res_pm_gold' => 'Gold', 'res_pm_palladium' => 'Palladium', 'res_pm_platinum' => 'Platinum', 'res_pm_silver' => 'Silver', 'tec' => 'Technology', 'tec_xx_data' => 'Data', 'tec_xx_3dprinting' => '3D Printing', 'tec_xx_fintech' => 'Financial Technology', 'tec_xx_cleantech' => 'Clean Tech', 'tec_xx_cloud' => 'Cloud', 'tec_xx_app' => 'Apps', 'tec_xx_nano' => 'Nanotechnology', 'tec_xx_security' => 'Security', 'tec_xx_graphene' => 'Graphene', 'tec_xx_mobile' => 'Mobile Web'
	);

	$openx = "<!--/* OpenX No Cookie Image Tag v2.8.9 */--><a href='http://ox.harbor.com/openx/www/delivery/ck.php?zoneid=%%ZONEID%%' target='_blank'><img src='http://ox.harbor.com/openx/www/delivery/avw.php?zoneid=%%ZONEID%%&amp;cb=".rand()."' border='0' alt='' /></a>";

	$settings = get_option('harbor_autonewsmanager');
	$hex_color = $settings['hex_color'];
	$featured_articles = $settings['featured_articles'];
	$company_articles = $settings['company_articles'];

	$url = ($settings['image_url']) ? $settings['image_url'] : get_site_url();

	$wc_settings = get_option('whatcounts-framework');
	$fields = $wc_settings['fields'];
	$fields = new ArrayObject($fields);
	$fields->ksort();

	$cats = $wpdb->get_results("SELECT t.term_id, t.name, x.parent FROM wp_terms t INNER JOIN wp_term_taxonomy x ON t.term_id = x.term_id WHERE (x.taxonomy = 'category') AND (t.name NOT LIKE 'zuber%') AND (x.parent > 0) ORDER BY x.parent, t.term_id;", OBJECT_K);

	$company_news = $wpdb->get_var("SELECT x.term_taxonomy_id FROM wp_term_taxonomy x JOIN wp_terms t ON x.term_id = t.term_id AND x.taxonomy = 'category' WHERE (t.slug = 'company-news');");

	$market_news = $wpdb->get_var("SELECT x.term_taxonomy_id FROM wp_term_taxonomy x JOIN wp_terms t ON x.term_id = t.term_id AND x.taxonomy = 'category' WHERE (t.slug = 'market-news');");

	$default_ad_order = $wpdb->get_var("SELECT ads FROM wp_harbor_auto_newsletter WHERE (field_name = 'default');");
	$default_ad_order = unserialize($default_ad_order);
	$default_use_ads = array();
	foreach ($default_ad_order[ad] as $key => $a) {
		if ($ad_order[src][$key] == 'textad') { $default_ad_query[] = $a; }
		if ($a) {
			$default_use_ads[$key][ad] = $a;
			$default_use_ads[$key][url] = $default_ad_order[url][$key];
			$default_use_ads[$key][src] = $default_ad_order[src][$key];
		}
	}

	include 'template-pieces.php';

	//date_default_timezone_set('America/New_York');
	date_default_timezone_set('America/Vancouver');

	// NEWSLETTER SCHEDULE
	// Send primaries every weekday morning 8pdt (search last 24 hours for feature, last 5 days for supporting)
	// Send ubers every weekday afternoon (search last 24 hours for feature, last 5 days for supporting)
	// Send uber2s every saturday 12pdt (search last 7 days)
	
	$afternoon = (date('H') > 9) ? true : false;
	$saturday = (date('w') == 6) ? true : false;

	$folder = ($afternoon) ? 'pm/' : 'am/';
	$folder = ($saturday) ? 'weekend/' : $folder;

	$now = time();
	$sincetime = ($saturday) ? $now - (7*60*60*24) : $now - (60*60*24);
	$sincetime_five = ($saturday) ? $now - (7*60*60*24) : $now - (5*60*60*24);

	$since = " AND (p.post_date > '".date('Y-m-d H:i:s', $sincetime)."')";
	$since_five = " AND (p.post_date > '".date('Y-m-d H:i:s', $sincetime_five)."')";
	
	$maco_sql = "SELECT p.ID FROM wp_posts p JOIN wp_term_relationships r ON p.ID = r.object_id WHERE (p.post_status = 'publish') AND (p.post_type = 'post') ".$since." AND (r.term_taxonomy_id IN (".$company_news.", ".$market_news."));";
	$maco = $wpdb->get_col($maco_sql);
	$maco_string = (is_array($maco) && !empty($maco)) ? implode(',', $maco) : '0';

	$juco_sql = "SELECT p.ID FROM wp_posts p JOIN wp_term_relationships r ON p.ID = r.object_id WHERE (p.post_status = 'publish') AND (p.post_type = 'post') ".$since_five." AND (r.term_taxonomy_id = ".$company_news.");";
	$juco = $wpdb->get_col($juco_sql);
	$juco_string = (is_array($juco) && !empty($juco)) ? implode(',', $juco) : '0';

	foreach ($fields as $f) {

		$category_link = get_category_link($f[term_id]);

		$depth_array = explode('_', $f[name]);
		$depth = count($depth_array);

		// skip uber2 and primaries in the afternoon
		
		$skip_topic = ($afternoon) ? true : false;
		
		switch ($depth) {
			case 1:
				$needle = $depth_array[0];
				$search_array = array();
				$skip_topic = ($afternoon) ? false : true; // skip ubers in the morning
				$skip_topic = ($saturday) ? true : $skip_topic; // always skip ubers on saturday
				break;
			case 2:
				$needle = $depth_array[0].'_'.$depth_array[1];
				$search_array = array();
				$skip_topic = ($saturday) ? false : true; // only show uber2 on saturday
				break;
			default:
				$needle = false;
				$search_array = array($f[term_id]);
				$skip_topic = ($afternoon) ? true : false; // skip primaries in the afternoon
				$skip_topic = ($saturday) ? true : $skip_topic; // always skip primaries on saturday
				break;
		}

		if ($needle) {
			foreach ($fields as $d) {
				if (strpos($d[name], $needle.'_') !== false || $d[name] === $needle) {
					$search_array[] = $d[term_id];
				}
			}
		}

		$deep_search = " AND (r.term_taxonomy_id IN (".implode(',', $search_array)."))";

		$count_ft = intval($featured_articles[$f[name]]);
		$count_co = intval($company_articles[$f[name]]) + $count_ft;

		// get extra articles in case we need to skip a featured article that was pulled into company by mistake
		// $count_co will also serve as our maximum articles limit for the newsletter

		$sql = "(SELECT DISTINCT 'true' AS feature, p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_name, p.post_date, m1.meta_value AS subheadline, m3.meta_value AS image
			FROM wp_posts p
				JOIN wp_term_relationships r ON p.ID = r.object_id
				JOIN wp_term_relationships r2 ON p.ID = r2.object_id
				LEFT JOIN wp_postmeta m1 ON p.ID = m1.post_id AND m1.meta_value IS NOT NULL AND m1.meta_key = 'subheadline'
				LEFT JOIN wp_postmeta m2 ON p.ID = m2.post_id AND m2.meta_value IS NOT NULL AND m2.meta_key = '_thumbnail_id'
				LEFT JOIN wp_postmeta m3 ON m2.meta_value = m3.post_id AND m3.meta_value IS NOT NULL AND m3.meta_key = '_wp_attached_file'
			WHERE (p.post_status='publish') AND (p.post_type='post') ".$since.$deep_search." AND (p.ID NOT IN (".$maco_string."))
			ORDER BY post_date DESC LIMIT ".$count_ft.")
			UNION
			(SELECT DISTINCT 'false' AS feature, p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_name, p.post_date, m1.meta_value AS subheadline, m3.meta_value AS image
			FROM wp_posts p
				JOIN wp_term_relationships r ON p.ID = r.object_id
				JOIN wp_term_relationships r2 ON p.ID = r2.object_id
				LEFT JOIN wp_postmeta m1 ON p.ID = m1.post_id AND m1.meta_value IS NOT NULL AND m1.meta_key = 'subheadline'
				LEFT JOIN wp_postmeta m2 ON p.ID = m2.post_id AND m2.meta_value IS NOT NULL AND m2.meta_key = '_thumbnail_id'
				LEFT JOIN wp_postmeta m3 ON m2.meta_value = m3.post_id AND m3.meta_value IS NOT NULL AND m3.meta_key = '_wp_attached_file'
			WHERE (p.post_status='publish') AND (p.post_type='post') ".$deep_search." AND (p.ID IN (".$juco_string."))
			ORDER BY post_date DESC LIMIT ".$count_co.");";

		//$report .= $sql."\n\n";
		
		$results = $wpdb->get_results($sql, ARRAY_A);

		if ($results && $results[0][feature] == 'true' && $skip_topic == false) {

			$articles = '';

			$ad_order = $wpdb->get_var("SELECT ads FROM wp_harbor_auto_newsletter WHERE (field_name = '".$f[name]."');");
			$ad_order = unserialize($ad_order);
			$use_ads = array();
			$ad_query = array();
			if (is_array($ad_order[ad])) {
				foreach ($ad_order[ad] as $key => $a) {
					if ($ad_order[ad][$key] > 0) { $ad_query[] = $a; }
					if ($a) {
						$use_ads[$key][ad] = $a;
						$use_ads[$key][url] = $ad_order[url][$key];
						$use_ads[$key][src] = $ad_order[src][$key];
					}
				}
			}

			if (count($ad_query) == 0) {
				$ad_query = $default_ad_query;
				$use_ads = $default_use_ads;
			}
			
			$ad_string = (is_array($ad_query)) ? implode(',', $ad_query) : false;

			if ($ad_string) {

				$sql = "SELECT p.ID, p.post_content, m1.meta_value AS link, m2.meta_value AS button, m3.meta_value AS config, m4.meta_value AS image
					FROM wp_posts p
						LEFT JOIN wp_postmeta m1 ON p.ID = m1.post_id AND m1.meta_value IS NOT NULL AND m1.meta_key = 'text_ad_link'
						LEFT JOIN wp_postmeta m2 ON p.ID = m2.post_id AND m2.meta_value IS NOT NULL AND m2.meta_key = 'text_ad_button'
						LEFT JOIN wp_postmeta m3 ON p.ID = m3.post_id AND m3.meta_value IS NOT NULL AND m3.meta_key = 'text_ad_config'
						LEFT JOIN wp_postmeta mx ON p.ID = mx.post_id AND mx.meta_value IS NOT NULL AND mx.meta_key = '_thumbnail_id'
						LEFT JOIN wp_postmeta m4 ON mx.meta_value = m4.post_id AND m4.meta_value IS NOT NULL AND m4.meta_key = '_wp_attached_file'
					WHERE (p.post_status='publish') AND (p.post_type = 'text_ad') AND (p.ID IN (".$ad_string."));";

				$ad_results = $wpdb->get_results($sql, ARRAY_A);

				$ads = array();
				foreach($ad_results as $ar) {
					$id = $ar[ID];
					$ads[$id][post_content] = $ar[post_content];
					$ads[$id][link] = $ar[link];
					$ads[$id][button] = $ar[button];
					$ads[$id][config] = $ar[config];
					$ads[$id][image] = $ar[image];
				}

			}

			$articles_in_this_newsletter = array();
			foreach($results as $key => $r) {
				if (!in_array($r[ID], $articles_in_this_newsletter)) {
					$articles_in_this_newsletter[] = $r[ID];
				} else {
					unset($results[$key]);
				}
			}

			$results = array_values($results);

			$last_key = end(array_keys($results));
			$count = 0;

			$first_excerpt = false;

			foreach($results as $key => $r) {
				$permalink = get_permalink($r[ID]);
				if ($r[post_excerpt]) {
					$excerpt = $r[post_excerpt];
				} else {
					$excerpt_length = ($r[image]) ? 120 : 300;
					$excerpt = myTruncate(strip_tags($r[post_content]), $excerpt_length);
				}

				if (!$first_excerpt) { $first_excerpt = $excerpt; }

				$has_underline = ($key != $last_key || $key == 0) ? true : false;

				$small_image = false;
				$imgsize = getimagesize($url.'/wp-content/uploads/'.$r[image]);
				
				if ($r[image]) {
					if ($imgsize[0] < 100) {
						$r[image] = false;
					} else if ($imgsize[0] < 220) {
						$small_image = true;
					}
				}

				$articles .= article($url, $r[post_title], $r[subheadline], $r[post_date], $excerpt, $permalink, $r[image], $has_underline, 'readmore', '', $small_image);
				if ($key == 0) {
					$error = $wpdb->query("DELETE FROM wp_harbor_auto_newsletter_titles WHERE (field_name LIKE '".$f[name]."');");
					//$report .= $error."\n\n";
					$post_title = ($r[post_title]) ? $subject[$f[name]].': '.$r[post_title] : 'XYZ '.$subject[$f[name]].' Investing News';
					$report .= 'NEWSLETTER: '.$post_title."\n\n";
					$sql = $wpdb->prepare("INSERT INTO wp_harbor_auto_newsletter_titles (field_name, newsletter_title) VALUES (%s, %s);", $f[name], $post_title);
					$error = $wpdb->query($sql);
					//$report .= 'SQL: '.$sql."\n\n";
				}
				$count++;

				$report .= "    ".$r[post_title]."\n";

				if (($use_ads[$key][ad] && $key < $last_key)||($key == 0)) {
					$src = $use_ads[$key][src];
					$ta = $use_ads[$key][ad];
					if ($src == 'textad') {
						$articles .= article($url, '','','',$ads[$ta][post_content], $ads[$ta][link], $ads[$ta][image], true, $ads[$ta][config], $ads[$ta][button], false);
					} else {
						$image_content = "<center>".str_replace('%%ZONEID%%', $use_ads[$key][ad], $openx)."</center>";
						$articles .= article($url, '','','',$image_content,'','',true,'','', false);
					}
				}
				if ($count == $count_co) { break; }
			}

			$email = wrapper($url, $cats[$f[term_id]]->name, $f[name], $hex_color[$f[name]], $articles, $category_link, $first_excerpt);

			$email = str_replace('src="https', 'src="http', $email);
			$email = str_replace('href="https', 'href="http', $email);

			$email = preg_replace('/\s+/S', " ", $email);

			$directory = dirname(dirname(dirname(dirname(__FILE__))));
			
			$filename = $directory.'/newsletters/'.$folder.$f[name].'.html';
			file_put_contents($filename, $email);

			$meridiem = ($afternoon) ? 'pm' : 'am';

			$archive_date = date('Ymd');
			$archive_filename = $directory.'/newsletters/archive/'.$archive_date.'_'.$f[name].'.html';
			file_put_contents($archive_filename, $email);

		} else {
		
			$email = 'NO ARTICLES';
			$count = 0;

			// no articles, remove that newsletter from directory if it exists.
			$directory = dirname(dirname(dirname(dirname(__FILE__))));
			$filename = $directory.'/newsletters/'.$folder.$f[name].'.html';
			if (file_exists($filename)) { unlink($filename); }

		}

		if ($count == '0') {
			$had_feature = ($results[0][feature]) ? "" : " (no feature article)";
			if ($skip_topic) {
				$report .= "Topic ".$f[name].$had_feature." was skipped\n\n";
			} else {
				$report .= "No articles for ".$f[name].$had_feature."\n\n";
			}
		} else {
			$report .= "\n";
			$report .= strtoupper($f[name])." had ".$count." articles\n";
			$report .= "http://harbor.com/newsletters/".$folder.$f[name].".html\n\n";
		}
		
	}

	if ($ts) { echo $report; }
	if ($rp) { mail($em, "NEWSLETTER BUILDER REPORT", $report); }

} // if CRON or $ts

// Original PHP code by Chirp Internet: www.chirp.com.au
// Please acknowledge use of this code by including this header.
// http://www.the-art-of-web.com/php/truncate/

function myTruncate($string, $limit, $break=".", $pad="...") {

	// return with no change if string is shorter than $limit
	if (strlen( $string ) <= $limit ) return $string;

	// is $break present between $limit and the end of the string?
	if ( false !== ( $breakpoint = strpos( $string, $break, $limit ) ) ) {
		if($breakpoint < strlen($string) - 1) {
			$string = substr($string, 0, $breakpoint) . $pad;
		}
	}

	return $string;
}

?>
