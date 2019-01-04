<?php
/**
 * Plugin Name: Harbor Leads
 * Plugin URI: http://www.kwyjibo.com
 * Description: Gather and distribute leads based on free report download transactions
 * Version: 0.9.4
 * License: GPL
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

class harborLeadManager {

	function __construct(){
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action('admin_menu', array($this, 'dashboard'));
	}

	public function dashboard() {
		if ( function_exists( 'add_submenu_page' ) ) {
			add_submenu_page( 'users.php', __('Harbor Lead Manager'), __('Harbor Lead Manager'), 'manage_options', 'harbor-lead-manager', array($this, 'options') );
		}
	}

	public function options() {

		global $wpdb;

		// DISABLE CACHING
		define('DONOTCACHEPAGE', true);
		define('DONOTCACHEDB', true);
		define('DONOTMINIFY', true);
		define('DONOTCDN', true);
		define('DONOTCACHEOBJECT', true);

		$default_msg = "Attached you will find the most recent leads generated by your free report sponsorship:\n";
		$default_msg .= "[REPORT]\n\n";
		$default_msg .= "Thank you, and please let us know if you have any questions.\n\n";
		$default_from_email = "info@kwyjibo.com";
		$default_from_name = "Harbor";
		$default_subject = "Leads from Harbor";

		$settings = get_option('harbor_leadmgr');

		$message = ($settings['message']) ? $settings['message'] : $default_msg;
		$from_email = ($settings['from_email']) ? $settings['from_email'] : $default_from_email;
		$from_name = ($settings['from_name']) ? $settings['from_name'] : $default_from_name;
		$subject = ($settings['subject']) ? $settings['subject'] : $default_subject;
		$inc_phone = ($settings['inc_phone'] === '1') ? true : false;
		$exclude_countries = ($settings['exclude_countries']) ? $settings['exclude_countries'] : array();

		echo '<div class="wrap">';

			$plugin_data = get_plugin_data(__FILE__, 0, 0);

			echo "<h2>".__($plugin_data['Title'])." - Version ".__($plugin_data['Version'])."</h2>";

			if (!empty($_POST) && check_admin_referer('updateLeadOptions','lead-option-nonce')) {

				$post_id = filter_input(INPUT_POST, "postid", FILTER_VALIDATE_INT);

				if (is_int($post_id)) {

					$hlm_sponsor_split = ($_POST['hlm_sponsor_split'] == 'split') ? true : false;
					update_post_meta($post_id, 'hlm_sponsor_split', $hlm_sponsor_split);

					$hlm_sponsor_addl_data = ($_POST['hlm_sponsor_addl_data'] == '1') ? true : false;
					update_post_meta($post_id, 'hlm_sponsor_addl_data', $hlm_sponsor_addl_data);

					$hlm_sponsor_frequency = $_POST['hlm_sponsor_frequency'];
					if (!is_nan($hlm_sponsor_frequency)) {
						update_post_meta($post_id, 'hlm_sponsor_frequency', $hlm_sponsor_frequency);
					}

					for ($i = 1; $i <= 4; $i++) {
						$meta_key = 'hlm_sponsor_email_'.$i;
						$meta_value = trim($_POST[$meta_key]);
						update_post_meta($post_id, $meta_key, $meta_value);
					}

					echo "<div class='updated'>";
					echo "<p>Lead Options Updated</p>";
					echo "</div>";

				}

				if ($_POST['message']) {

					$new_settings = array(
						'message' => $_POST['message'],
						'from_email' => $_POST['from_email'],
						'from_name' => $_POST['from_name'],
						'subject' => $_POST['subject'],
						'inc_phone' => $_POST['inc_phone'],
						'exclude_countries' => $_POST['exclude_countries'],
					);

					if ( get_option('harbor_leadmgr') !== false ) {
						update_option('harbor_leadmgr',$new_settings);
					} else {
						// create option with deprecated = null and autoload = 'no'.
						add_option('harbor_leadmgr',$new_settings,null,'no');
					}
					
					$settings = get_option('harbor_leadmgr');

					$message = ($settings['message']) ? $settings['message'] : $_POST['message'];
					$from_email = ($settings['from_email']) ? $settings['from_email'] : $_POST['from_email'];
					$from_name = ($settings['from_name']) ? $settings['from_name'] : $_POST['from_name'];
					$subject = ($settings['subject']) ? $settings['subject'] : $_POST['subject'];
					$inc_phone = ($settings['inc_phone'] === '1') ? true : false;
					$exclude_countries = ($settings['exclude_countries']) ? $settings['exclude_countries'] : $_POST['exclude_countries'];

					echo "<div class='updated'>";
					echo "<p>Lead Manager Settings Updated</p>";
					echo "</div>";

				}
			}

			$date_to = date('Y-m-d');
			$date_from = date('Y-m-d', strtotime('-7 days'));

			$query = "SELECT type, id FROM wp_harbor_transaction_types;";
			$types = $wpdb->get_results($query, OBJECT_K);

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

			$counts_base_sql = "SELECT %s AS type, %d AS ID, COUNT(DISTINCT user_id) as ct, %s AS filename, %s AS post_title
				FROM wp_harbor_transactions
				WHERE (";

			foreach ( $active_reports as $post_id => $ar ) {
				$x = array();
				$t = 'view';
				$filename = ( $ar['download'] ) ? $ar['download'] : '';
				if ( $ar['view'] ) { $x[] = "((typeId = ".$types['view']->id.") AND (itemId = '".$ar['view']."'))"; }
				if ( $ar['download'] ) { $x[] = "((typeId = ".$types['download']->id.") AND (itemId = '".$ar['download']."'))"; }
				if ( $ar['request'] ) { $x[] = "((typeId = ".$types['request']->id.") AND (itemId = '".$ar['request']."'))"; $t = 'request'; }
				if ( !empty($x) ) {
					$x = implode(' OR ', $x);
					$cs = $wpdb->prepare($counts_base_sql, $t, $post_id, $filename, $ar['post_title']);
					if ( $x != ' OR ' ) {
						$counts_sql[] = $cs . $x . ")";
					}
				}
			}

			if ( !empty($counts_sql) ) {
				$counts_sql_final = "(".implode(') UNION (', $counts_sql).")";
			}

			$query = $counts_sql_final . " ORDER BY type DESC, ct DESC";

			$results = $wpdb->get_results($query);

			if (!empty($results)) { ?>

				<style>
					table.lead-manager { text-align: center; border: none; }
					
					.light { background-color: #f6f6f6; }
					.light:hover { background-color: #f2f2f2; }
					.dark { background-color: #e6e6e6; }
					.dark:hover { background-color: #e2e2e2; }
					.title:hover { cursor: pointer; color: #f00; }

					tr.header, tr.header td {
						background-color: #666;
						color: #fff;
						font-weight: bold;
						font-size: 110%;
						text-align: center;
					}

					table.lead-manager td { text-align: center; }
					table.lead-manager td:first-child { text-align: left; border-left: 1px solid #aaa; }
					table.lead-manager td:last-child { border-right: 1px solid #aaa; }
					tr.dos td { padding-top: 0; padding-bottom: 0; }
					.dos form { display: none; border: 1px solid #ccc; margin: 5px 5px 13px; padding: 5px; overflow: hidden; background-color: #fff; }
					.dos form div { float: left; padding: 5px; padding-right: 30px; }
					.dos form div:last-child { float: right; padding-right: 5px }
					.dos label { display: block; }

					tr.spacer, tr.spacer td { background-color: #f1f1f1; border-left: none !important; border-right: none !important; border-top: 1px solid #aaa; }

					form.settings div { margin-left: 30px; }
					form.settings input[type='text'],
					form.settings select
						{ width: 650px; margin-bottom: 15px; }
					form.settings textarea { width: 650px; margin-bottom: 15px; height: 300px; }

					@media (max-width: 600px) {
						td.nomobile { display: none; }
					}

				</style>

				<script type="text/javascript">
					jQuery(function() {
						jQuery('.title').click(function() {
							var form = '.' + jQuery(this).data('form');
							jQuery(form).slideToggle('fast');
						});
					});
				</script>

				<table class="widefat lead-manager">

					<tr class='header'><td>Free Reports</td><td class='nomobile'></td><td class='ctr'>Downloads/Views</td></tr>

					<?php
					$already_split = false;
					foreach ($results as $r) {
						$postid = $r->ID;
						$title = wp_trim_words($r->post_title, 11, '...');
						$file = ($r->type == 'download') ? $r->filename : '' ;
						$count = $r->ct;

						$query = "SELECT meta_key, meta_value AS val FROM wp_postmeta WHERE (meta_key LIKE 'hlm_%') AND (post_id = ".$postid.");";
						$meta = $wpdb->get_results($query, OBJECT_K);

						$split = ($meta[hlm_sponsor_split]->val) ? true : false ;
						$em = array($meta[hlm_sponsor_email_1]->val, $meta[hlm_sponsor_email_2]->val, $meta[hlm_sponsor_email_3]->val, $meta[hlm_sponsor_email_4]->val);
						$freq = $meta[hlm_sponsor_frequency]->val;
						$recent = $meta[hlm_sponsor_recent]->val;
						$addl = $meta[hlm_sponsor_addl_data]->val ? true : false ;
						$class = ($class == 'light') ? 'dark' : 'light';

						if ($r->type == 'request' && !$already_split) {
							echo "<tr class='spacer'><td colspan=3></td></tr>";
							echo "<tr class='header'><td>Investor Kits</td><td class='nomobile'>URL</td><td>Requests</td></tr>";
							$already_split = true;
						}

						?>
						<tr class="uno <?php echo $class ?>">
							<td>
								<?php echo "<b class='title' title='Click to edit settings' data-form='form-".$postid."'>".$title." / ".$r->type."</b>"; ?>
							</td>
							<td class='nomobile'>
								<i><?php echo $file ?></i>
							</td>
							<td class='ctr'>
								<?php echo $count ?>
							</td>
						</tr>
						<tr class='dos <?php echo $class ?>'>
							<td colspan=3>
								<?php
								echo "<form method='post' class='form-".$postid."'>";
									echo "<div><label>Send Report To:</label>";
									foreach($em as $key => $e) {
										echo "<input type='text' name='hlm_sponsor_email_".($key+1)."'  id='email-".($key+1)."' value='".$e."'>";
										if ($key % 2) { echo "<br/>"; }
									}
									echo "</div>";

									echo "<div>";
									echo "<label>Sponsor Data:</label>";
									echo "<label for='all'><input type='radio' name='hlm_sponsor_addl_data' value='0' id='standard' checked>Standard Data Fields</label>";
									echo "<label for='all'><input type='radio' name='hlm_sponsor_addl_data' value='1' id='additional'";
									if ($addl == 1) { echo " checked"; }
									echo ">Requires Add'l Data Fields</label>";
									echo "</div>";

									$recent = strtotime('monday this week');

									if ($recent) {
										echo "<div>";
										if ($recent) {
											echo "<label>Leads Last Processed:</label>";
											echo date("F j, Y", $recent)."<br/>";
											echo "<small>Leads emailed at that time if one or more existed.</small>";
										}
										echo "</div>";
									}

									echo "<div>";
									echo "<input type='submit' value='Update Settings'>";
									echo "<input type='hidden' name='postid' value='".$postid."'>";
									wp_nonce_field('updateLeadOptions','lead-option-nonce');
									echo "</div>";
								echo "</form>";
								?>
							</td>
						</tr>
					<?php } ?>
				<tr class='spacer'><td colspan=3></td></tr>
				</table>
		<?php } else { ?>
			Nothing found.
		<?php }; ?>
		
		<br clear='all'/>
		<hr/>
		<h3>Global Settings</h3>

		<form method='post' class='settings'>
			<div>
				<label for='from_email'>From Email Address</label><br/>
				<input type='text' name='from_email' id='from_email' value='<?php echo $from_email; ?>'>
			</div>
			<div>
				<label for='from_name'>From Email Name</label><br/>
				<input type='text' name='from_name' id='from_name' value='<?php echo $from_name; ?>'>
			</div>
			<div>
				<label for='subject'>Email Subject</label><br/>
				<input type='text' name='subject' id='subject' value='<?php echo $subject; ?>'>
			</div>
			<div>
				<label for='message'>Email Text</label><br/>
				<textarea name='message' id='message'><?php echo $message; ?></textarea>
			</div>
			<div>
				<label for='inc_phone'>
				<input type='checkbox' value=1 name='inc_phone' <?php if ($inc_phone) { echo 'checked'; } ?>>
				Include phone number for leads (if available)</label><br/>
				<br/>
			</div>
			<div>
				<label for='exclude_countries'>Country Exclusion Table</label><br/>
				<select name='exclude_countries[]' id='exclude_countries' multiple size=20>
					<?php get_country_list($exclude_countries); ?>
				</select>
			</div>
			<div>
				<br/>
				<input type='submit' value='Update Settings'>
				<?php wp_nonce_field('updateLeadOptions','lead-option-nonce'); ?>
			</div>
		</form>



		<br clear='all'/>
		<hr/>
		<h3>Answers</h3>
		<ul style='list-style-type: disc; margin-left: 40px;'>
			<li>Click on a sponsored report to enter sponsor emails.</li>
			<li>Sponsored report must have at least one download transaction before it will appear in the Lead Manager.</li>
			<li>The system will send leads on Monday morning, if there are any leads to be sent.</li>
			<li>Sponsored report will be skipped if no sponsor email addresses have been associated with it.</li>
			<li>The Downloads/Views column indicates total distinct users  with download or view transactions on record for that report.</li>
		</ul>

		</div>

		<?php
	}

}

// Instantiate our class
$harborLeadManager = new harborLeadManager();

function get_country_list($codes) {
	
	$output = "";
		
	if (!is_array($codes) && strpos($codes, ',')) {
		$codes = explode(',', $codes);
	} else if (empty($codes)) {
		$codes = array();
	}

	// http://country.io/names.json

	$countries = array(
		'AF'=>'Afghanistan', 'AX'=>'Aland Islands', 'AL'=>'Albania', 'DZ'=>'Algeria', 'AS'=>'American Samoa', 'AD'=>'Andorra', 'AO'=>'Angola', 'AI'=>'Anguilla', 'AQ'=>'Antarctica', 'AG'=>'Antigua and Barbuda', 'AR'=>'Argentina', 'AM'=>'Armenia', 'AW'=>'Aruba', 'AU'=>'Australia', 'AT'=>'Austria', 'AZ'=>'Azerbaijan', 'BS'=>'Bahamas', 'BH'=>'Bahrain', 'BD'=>'Bangladesh', 'BB'=>'Barbados', 'BY'=>'Belarus', 'BE'=>'Belgium', 'BZ'=>'Belize', 'BJ'=>'Benin', 'BM'=>'Bermuda', 'BT'=>'Bhutan', 'BO'=>'Bolivia', 'BQ'=>'Bonaire Saint Eustatius and Saba ', 'BA'=>'Bosnia and Herzegovina', 'BW'=>'Botswana', 'BV'=>'Bouvet Island', 'BR'=>'Brazil', 'IO'=>'British Indian Ocean Territory', 'VG'=>'British Virgin Islands', 'BN'=>'Brunei', 'BG'=>'Bulgaria', 'BF'=>'Burkina Faso', 'BI'=>'Burundi', 'KH'=>'Cambodia', 'CM'=>'Cameroon', 'CA'=>'Canada', 'CV'=>'Cape Verde', 'KY'=>'Cayman Islands', 'CF'=>'Central African Republic', 'TD'=>'Chad', 'CL'=>'Chile', 'CN'=>'China', 'CX'=>'Christmas Island', 'CC'=>'Cocos Islands', 'CO'=>'Colombia', 'KM'=>'Comoros', 'CK'=>'Cook Islands', 'CR'=>'Costa Rica', 'HR'=>'Croatia', 'CU'=>'Cuba', 'CW'=>'Curacao', 'CY'=>'Cyprus', 'CZ'=>'Czech Republic', 'CD'=>'Democratic Republic of the Congo', 'DK'=>'Denmark', 'DJ'=>'Djibouti', 'DM'=>'Dominica', 'DO'=>'Dominican Republic', 'TL'=>'East Timor', 'EC'=>'Ecuador', 'EG'=>'Egypt', 'SV'=>'El Salvador', 'GQ'=>'Equatorial Guinea', 'ER'=>'Eritrea', 'EE'=>'Estonia', 'ET'=>'Ethiopia', 'FK'=>'Falkland Islands', 'FO'=>'Faroe Islands', 'FJ'=>'Fiji', 'FI'=>'Finland', 'FR'=>'France', 'GF'=>'French Guiana', 'PF'=>'French Polynesia', 'TF'=>'French Southern Territories', 'GA'=>'Gabon', 'GM'=>'Gambia', 'GE'=>'Georgia', 'DE'=>'Germany', 'GH'=>'Ghana', 'GI'=>'Gibraltar', 'GR'=>'Greece', 'GL'=>'Greenland', 'GD'=>'Grenada', 'GP'=>'Guadeloupe', 'GU'=>'Guam', 'GT'=>'Guatemala', 'GG'=>'Guernsey', 'GN'=>'Guinea', 'GW'=>'Guinea-Bissau', 'GY'=>'Guyana', 'HT'=>'Haiti', 'HM'=>'Heard Island and McDonald Islands', 'HN'=>'Honduras', 'HK'=>'Hong Kong', 'HU'=>'Hungary', 'IS'=>'Iceland', 'IN'=>'India', 'ID'=>'Indonesia', 'IR'=>'Iran', 'IQ'=>'Iraq', 'IE'=>'Ireland', 'IM'=>'Isle of Man', 'IL'=>'Israel', 'IT'=>'Italy', 'CI'=>'Ivory Coast', 'JM'=>'Jamaica', 'JP'=>'Japan', 'JE'=>'Jersey', 'JO'=>'Jordan', 'KZ'=>'Kazakhstan', 'KE'=>'Kenya', 'KI'=>'Kiribati', 'XK'=>'Kosovo', 'KW'=>'Kuwait', 'KG'=>'Kyrgyzstan', 'LA'=>'Laos', 'LV'=>'Latvia', 'LB'=>'Lebanon', 'LS'=>'Lesotho', 'LR'=>'Liberia', 'LY'=>'Libya', 'LI'=>'Liechtenstein', 'LT'=>'Lithuania', 'LU'=>'Luxembourg', 'MO'=>'Macao', 'MK'=>'Macedonia', 'MG'=>'Madagascar', 'MW'=>'Malawi', 'MY'=>'Malaysia', 'MV'=>'Maldives', 'ML'=>'Mali', 'MT'=>'Malta', 'MH'=>'Marshall Islands', 'MQ'=>'Martinique', 'MR'=>'Mauritania', 'MU'=>'Mauritius', 'YT'=>'Mayotte', 'MX'=>'Mexico', 'FM'=>'Micronesia', 'MD'=>'Moldova', 'MC'=>'Monaco', 'MN'=>'Mongolia', 'ME'=>'Montenegro', 'MS'=>'Montserrat', 'MA'=>'Morocco', 'MZ'=>'Mozambique', 'MM'=>'Myanmar', 'NA'=>'Namibia', 'NR'=>'Nauru', 'NP'=>'Nepal', 'NL'=>'Netherlands', 'NC'=>'New Caledonia', 'NZ'=>'New Zealand', 'NI'=>'Nicaragua', 'NE'=>'Niger', 'NG'=>'Nigeria', 'NU'=>'Niue', 'NF'=>'Norfolk Island', 'KP'=>'North Korea', 'MP'=>'Northern Mariana Islands', 'NO'=>'Norway', 'OM'=>'Oman', 'PK'=>'Pakistan', 'PW'=>'Palau', 'PS'=>'Palestinian Territory', 'PA'=>'Panama', 'PG'=>'Papua New Guinea', 'PY'=>'Paraguay', 'PE'=>'Peru', 'PH'=>'Philippines', 'PN'=>'Pitcairn', 'PL'=>'Poland', 'PT'=>'Portugal', 'PR'=>'Puerto Rico', 'QA'=>'Qatar', 'CG'=>'Republic of the Congo', 'RE'=>'Reunion', 'RO'=>'Romania', 'RU'=>'Russia', 'RW'=>'Rwanda', 'BL'=>'Saint Barthelemy', 'SH'=>'Saint Helena', 'KN'=>'Saint Kitts and Nevis', 'LC'=>'Saint Lucia', 'MF'=>'Saint Martin', 'PM'=>'Saint Pierre and Miquelon', 'VC'=>'Saint Vincent and the Grenadines', 'WS'=>'Samoa', 'SM'=>'San Marino', 'ST'=>'Sao Tome and Principe', 'SA'=>'Saudi Arabia', 'SN'=>'Senegal', 'RS'=>'Serbia', 'SC'=>'Seychelles', 'SL'=>'Sierra Leone', 'SG'=>'Singapore', 'SX'=>'Sint Maarten', 'SK'=>'Slovakia', 'SI'=>'Slovenia', 'SB'=>'Solomon Islands', 'SO'=>'Somalia', 'ZA'=>'South Africa', 'GS'=>'South Georgia and the South Sandwich Islands', 'KR'=>'South Korea', 'SS'=>'South Sudan', 'ES'=>'Spain', 'LK'=>'Sri Lanka', 'SD'=>'Sudan', 'SR'=>'Suriname', 'SJ'=>'Svalbard and Jan Mayen', 'SZ'=>'Swaziland', 'SE'=>'Sweden', 'CH'=>'Switzerland', 'SY'=>'Syria', 'TW'=>'Taiwan', 'TJ'=>'Tajikistan', 'TZ'=>'Tanzania', 'TH'=>'Thailand', 'TG'=>'Togo', 'TK'=>'Tokelau', 'TO'=>'Tonga', 'TT'=>'Trinidad and Tobago', 'TN'=>'Tunisia', 'TR'=>'Turkey', 'TM'=>'Turkmenistan', 'TC'=>'Turks and Caicos Islands', 'TV'=>'Tuvalu', 'VI'=>'U.S. Virgin Islands', 'UG'=>'Uganda', 'UA'=>'Ukraine', 'AE'=>'United Arab Emirates', 'GB'=>'United Kingdom', 'US'=>'United States', 'UM'=>'United States Minor Outlying Islands', 'UY'=>'Uruguay', 'UZ'=>'Uzbekistan', 'VU'=>'Vanuatu', 'VA'=>'Vatican', 'VE'=>'Venezuela', 'VN'=>'Vietnam', 'WF'=>'Wallis and Futuna', 'EH'=>'Western Sahara', 'YE'=>'Yemen', 'ZM'=>'Zambia', 'ZW'=>'Zimbabwe'
	);

	foreach ($countries as $country_code => $country_name) {
		$output .= "<option value='".$country_code."'";
		if (in_array($country_code, $codes)) { $output .= " selected"; }
		$output .= ">".$country_name."</option>";
	}

	echo $output;

}

