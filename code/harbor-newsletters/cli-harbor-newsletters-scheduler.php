<?php

/**
 * Cron Program to SEND emails for Harbor Newsletters
 * Version: 0.8
 */

// TROUBLESHOOTING
// https://[URL]/wp-content/plugins/harbor-newsletter-manager/cli-harbor-newsletter-scheduler.php

$em = 'mwndll@gmail.com';
$ts = false; // troubleshooting in the html
$rp = true; // send email report

// DISABLE ALL CACHE/CDN

define('DONOTCACHEDB', true);
define('DONOTCACHEPAGE', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHEOBJECT', true);

// ONLY RUN IN CRON (OR IN TESTING IF $ts TRUE)

if ((php_sapi_name() == 'cli')||($ts)) {

	$root = dirname(dirname(dirname(dirname(__FILE__))));
	require_once($root . '/wp-load.php');
	global $wpdb;

	$segment = array(
		'lif' => '77265',
		'lif_xx_genetics' => '77267',
		'lif_xx_medicaldevices' => '77269',
		'lif_xx_pharma' => '77270',
		'lif_xx_biotech' => '77266',
		'lif_xx_longevity' => '77268',
		'res' => '77206',
		'res_ag' => '77207',
		'res_ag_cannabis' => '77208',
		'res_ag_phosphate' => '77209',
		'res_ag_potash' => '77210',
		'res_bm' => '77215',
		'res_bm_copper' => '77216',
		'res_bm_iron' => '77217',
		'res_bm_lead' => '77218',
		'res_bm_nickel' => '77219',
		'res_bm_zinc' => '77230',
		'res_cm' => '77220',
		'res_cm_cobalt' => '77221',
		'res_cm_graphite' => '77262',
		'res_cm_magnesium' => '77223',
		'res_cm_manganese' => '77224',
		'res_cm_rareearth' => '77225',
		'res_cm_scandium' => '77226',
		'res_cm_tantalum' => '77227',
		'res_cm_tellurium' => '77228',
		'res_cm_tungsten' => '77234',
		'res_en' => '77236',
		'res_en_oilgas' => '77239',
		'res_en_gas' => '77237',
		'res_en_lithium' => '77238',
		'res_en_oil' => '77240',
		'res_en_uranium' => '77241',
		'res_gm' => '77235',
		'res_gm_diamonds' => '77235',
		'res_im' => '77242',
		'res_im_aluminum' => '77243',
		'res_im_chromium' => '77244',
		'res_im_coal' => '77245',
		'res_im_moly' => '77246',
		'res_im_tin' => '77247',
		'res_im_vanadium' => '77248',
		'res_pm' => '77249',
		'res_pm_gold' => '77250',
		'res_pm_palladium' => '77251',
		'res_pm_platinum' => '77252',
		'res_pm_silver' => '77253',
		'tec' => '77254',
		'tec_xx_data' => '77261',
		'tec_xx_3dprinting' => '77255',
		'tec_xx_fintech' => '77477',
		'tec_xx_cleantech' => '77258',
		'tec_xx_cloud' => '77259',
		'tec_xx_app' => '77256',
		'tec_xx_nano' => '77263',
		'tec_xx_security' => '77260',
		'tec_xx_graphene' => '77262',
		'tec_xx_mobile' => '77545'
		);

	$template = array(
		'lif' => '520183',
		'lif_xx_genetics' => '520185',
		'lif_xx_medicaldevices' => '520186',
		'lif_xx_pharma' => '520187',
		'lif_xx_biotech' => '520184',
		'lif_xx_longevity' => '520188',
		'res' => '520189',
		'res_ag' => '520190',
		'res_ag_cannabis' => '520191',
		'res_ag_phosphate' => '520192',
		'res_ag_potash' => '520193',
		'res_bm' => '520194',
		'res_bm_copper' => '520195',
		'res_bm_iron' => '520196',
		'res_bm_lead' => '520197',
		'res_bm_nickel' => '520198',
		'res_bm_zinc' => '520199',
		'res_cm' => '520200',
		'res_cm_cobalt' => '520201',
		'res_cm_graphite' => '520202',
		'res_cm_magnesium' => '520203',
		'res_cm_manganese' => '520204',
		'res_cm_rareearth' => '520205',
		'res_cm_scandium' => '520206',
		'res_cm_tantalum' => '520207',
		'res_cm_tellurium' => '520208',
		'res_cm_tungsten' => '520209',
		'res_en' => '520210',
		'res_en_oilgas' => '520211',
		'res_en_gas' => '520212',
		'res_en_lithium' => '520213',
		'res_en_oil' => '520214',
		'res_en_uranium' => '520215',
		'res_gm' => '520216',
		'res_gm_diamonds' => '520217',
		'res_im' => '520218',
		'res_im_aluminum' => '520219',
		'res_im_chromium' => '520220',
		'res_im_coal' => '520221',
		'res_im_moly' => '520222',
		'res_im_tin' => '520223',
		'res_im_vanadium' => '520224',
		'res_pm' => '520225',
		'res_pm_gold' => '520174',
		'res_pm_palladium' => '520226',
		'res_pm_platinum' => '520227',
		'res_pm_silver' => '520228',
		'tec' => '520229',
		'tec_xx_data' => '520230',
		'tec_xx_3dprinting' => '520231',
		'tec_xx_fintech' => '523201',
		'tec_xx_cleantech' => '520233',
		'tec_xx_cloud' => '520234',
		'tec_xx_app' => '520236',
		'tec_xx_nano' => '520237',
		'tec_xx_security' => '520238',
		'tec_xx_graphene' => '520235',
		'tec_xx_mobile' => '523993'
		);

	$subject = array(
		'lif' => 'Life Sciences',
		'lif_xx_genetics' => 'Genetics',
		'lif_xx_medicaldevices' => 'Medical Devices',
		'lif_xx_pharma' => 'Pharmaceuticals',
		'lif_xx_biotech' => 'Biotech',
		'lif_xx_longevity' => 'Longevity',
		'res' => 'Resources',
		'res_ag' => 'Agriculture',
		'res_ag_cannabis' => 'Cannabis',
		'res_ag_phosphate' => 'Phosphate',
		'res_ag_potash' => 'Potash',
		'res_bm' => 'Base Metals',
		'res_bm_copper' => 'Copper',
		'res_bm_iron' => 'Iron',
		'res_bm_lead' => 'Lead',
		'res_bm_nickel' => 'Nickel',
		'res_bm_zinc' => 'Zinc',
		'res_cm' => 'Critical Metals',
		'res_cm_cobalt' => 'Cobalt',
		'res_cm_graphite' => 'Graphite',
		'res_cm_magnesium' => 'Magnesium',
		'res_cm_manganese' => 'Manganese',
		'res_cm_rareearth' => 'Rare Earth',
		'res_cm_scandium' => 'Scandium',
		'res_cm_tantalum' => 'Tantalum',
		'res_cm_tellurium' => 'Tellurium',
		'res_cm_tungsten' => 'Tungsten',
		'res_en' => 'Energy',
		'res_en_oilgas' => 'Oil & Gas',
		'res_en_gas' => 'Gas',
		'res_en_lithium' => 'Lithium',
		'res_en_oil' => 'Oil',
		'res_en_uranium' => 'Uranium',
		'res_gm' => 'Gem',
		'res_gm_diamonds' => 'Diamond',
		'res_im' => 'Industrial Metals',
		'res_im_aluminum' => 'Aluminum',
		'res_im_chromium' => 'Chromium',
		'res_im_coal' => 'Coal',
		'res_im_moly' => 'Molybdenum',
		'res_im_tin' => 'Tin',
		'res_im_vanadium' => 'Vanadium',
		'res_pm' => 'Precious Metals',
		'res_pm_gold' => 'Gold',
		'res_pm_palladium' => 'Palladium',
		'res_pm_platinum' => 'Platinum',
		'res_pm_silver' => 'Silver',
		'tec' => 'Technology',
		'tec_xx_data' => 'Data',
		'tec_xx_3dprinting' => '3D Printing',
		'tec_xx_fintech' => 'Fintech',
		'tec_xx_cleantech' => 'Clean Tech',
		'tec_xx_cloud' => 'Cloud',
		'tec_xx_app' => 'Apps',
		'tec_xx_nano' => 'Nanotechnology',
		'tec_xx_security' => 'Security',
		'tec_xx_graphene' => 'Graphene',
		'tec_xx_mobile' => 'Mobile Web'
		);

	$sql = 'SELECT field_name, newsletter_title FROM wp_harbor_auto_newsletter_titles ORDER BY field_name;';
	$newsletter_titles = $wpdb->get_results($sql, OBJECT_K);

	//date_default_timezone_set('America/New_York');
	date_default_timezone_set('America/Vancouver');
	
	$afternoon = (date('H') > 9) ? true : false;
	$saturday = (date('w') == 6) ? true : false;

	$folder = ($afternoon) ? 'pm/' : 'am/';
	$folder = ($saturday) ? 'weekend/' : $folder;
	
	$time = ($afternoon) ? 'Afternoon' : 'Morning'; // in XYZ world, afternoon starts at 8am.

	echo '<pre>';

	$title = '[XYZ] '.date('Y-m-d').' '.$time.' Mailing Report';

	$report = $title."\n\n";

	$harborWhatCountsFramework = harborWhatCountsFramework::getInstance();

	$directory = dirname(dirname(dirname(dirname(__FILE__))));
	$files = scandir($directory.'/newsletters/'.$folder);

	$report .= 'Found '.count($files).' files.'."\n\n";
	
	$sendcount = 0;

	$everything = ($afternoon && !$saturday) ? false : true;

	$whatcounts_settings = get_option('whatcounts-framework');
	$list_id = $whatcounts_settings['list']; // LIVE LIST!

	//$list_id = '382624'; // test list id

	foreach($files as $f) {
		if (strpos($f, '.html') !== false) {
			$topic = explode('.', $f);

			$report .= 'Sending '.$subject[$topic[0]]."\n";

			$backup_wc_subject = 'XYZ '.$subject[$topic[0]].' Investing News';
			$wc_campaign = $subject[$topic[0]].' Daily - '.$time;
			$wc_segment = $segment[$topic[0]];

			//$wc_segment = '78001'; // test segment id

			$wc_subject = $newsletter_titles[$topic[0]]->newsletter_title;
			$wc_subject = ($wc_subject) ? $wc_subject : $backup_wc_subject;

			$args = array(
				'cmd'				=> 'launch',
				'realm'				=> 'harbor_xyz',
				'pwd'				=> $password,
				'list_id'			=> $list_id,
				'template_id'		=> $template[$topic[0]],
				'segmentation_id'	=> $wc_segment,
				'format'			=> '2',
				'subject'			=> $wc_subject,
				'campaign_alias'	=> $wc_campaign
			);
			
			if ($everything) {
				$report .= print_r($args, true);
				$sendcount++;
				$response = $harborWhatCountsFramework->callServer($args);
				$report .= 'Whatcounts says '.$response."\n\n";
			} else {
				$just = $argv[1];
				if ($f == $just.'.html') {
					$report .= print_r($args, true);
					$sendcount++;
					$response = $harborWhatCountsFramework->callServer($args);
					$report .= 'Whatcounts says '.$response."\n\n";
				} else {
					$report .= $f.' skipped'."\n\n";
				}
			}
		} else {
			$report .= 'The file '.$f.' is not a newsletter template.'."\n\n";
		}
	}

	$sent = ($sendcount > 0) ? 'Submitted '.$sendcount.' newsletters to Whatcounts.' : 'No newsletters submitted to Whatcounts.';

	$report .= $sent."\n\n";

	echo '<pre>'.$report;
	
	mail($em, $title, $report);

}

?>
