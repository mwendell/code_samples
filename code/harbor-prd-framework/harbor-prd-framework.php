<?php
/**
 * Plugin Name: Harbor PRD Framework
 * Description: PRD Payment integration framework and admin interface
 * Version: 0.9.14
 * License: GPL
 * Text Domain: harbor-prd
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

class harborPRD {

// ------------------------------------------------------------------------
// ACTIVATION AND SETUP

	static $instance = false;

	private $_settings;
	private $_optionsName = 'harbor-prd';
	private $_optionsGroup = 'harbor-prd-options';
	private $_errors = array();
	private $_message = '';
	private $_transactionID = '';
	private $_currencies = array();

	private function __construct() {

		global $wpdb;
		$this->_order_table = $wpdb->prefix . "Harbor_orders";
		$this->_offers_table = $wpdb->prefix . "Harbor_offers";
		$this->_transactions_table = $wpdb->prefix . "Harbor_transactions";
		$this->_transaction_types_table = $wpdb->prefix . "Harbor_transaction_types";

		$this->_getSettings();

		$this->_currencies = array(
			'CN'	=> __('Canadian Dollar', 'harbor-prd'),
			'US'	=> __('U.S. Dollar', 'harbor-prd')
		);

		add_action('admin_init', array($this,'registerOptions'));
		add_action('admin_menu', array($this,'adminMenu'));
		add_action('profile_update', array($this,'customer_update'));

		add_filter('init', array($this, 'init_locale'));
	}

	public static function getInstance() {
		if (!self::$instance) { self::$instance = new self; }
		return self::$instance;
	}

	public function init_locale() {
		load_plugin_textdomain('harbor-prd', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	private function _getSettings() {

		if (empty($this->_settings)) { $this->_settings = get_option($this->_optionsName); }

		if (!is_array($this->_settings)) { $this->_settings = array(); }

		foreach ($this->_options as $key => $o) {
			if( isset( $o['default'] ) ){
				$defaults[$key] = $o['default'];
			}
		}

		$this->_settings = wp_parse_args($this->_settings, $defaults);
	}

	public function registerOptions() {
		register_setting($this->_optionsGroup, $this->_optionsName, array($this, 'sanitize_options'));
	}

	public function adminMenu() {
		add_options_page(__('PRD Settings', 'harbor-prd'), __('PRD Settings', 'harbor-prd'), 'manage_options', 'PRD', array($this, 'options'));
		add_menu_page('PRD Terminal', 'PRD Terminal', 'manage_options', 'prd-terminal', array($this, 'terminal'));
	}

	public function is_prd_awake() {

		$harbor_prd = get_option('harbor-prd');

		if ( $harbor_prd['bypass_server_check'] ) { return true; }

		$url = ( $harbor_prd['base_url'] ) ? trim( str_replace( 'https://', '', $harbor_prd['base_url'] ) ) : "69.165.42.21";

		$fp = @fsockopen($url, 443, $errno, $errstr, 5);
		if (!$fp) return false;
		fclose($fp);
		return true;
	}

// ------------------------------------------------------------------------
// SETTINGS PAGE

	public function options() {

		global $wpdb;

		$plugin_data = get_plugin_data(__FILE__, 0, 0);

		echo '<div class="wrap">';
		echo '<h1>'.__($plugin_data['Title']).' - Version '.__($plugin_data['Version']).'</h1>';

		if ( ! $this->is_prd_awake() ) {
			echo '<div class="error"><p><b>PRD SERVER PROBLEM</b><br/>The PRD API server is not responding to requests at the moment.</p></div>';
		}

		?>
		<style>
			.form-table td { padding: 4px 10px; vertical-align: center; }
			.form-table tr.head-row td { border-bottom: 1px solid #ccc; }
			.form-table h3 { margin: 30px 0 3px; }
			.keycode-table { border-spacing: 0; }
			.keycode-table tr.head-row td { border-bottom: 1px solid #ccc; font-weight: bold; }
			.keycode-table tr.closing-row td { border-bottom: 1px solid #ccc; font-size: 2px; padding: 0px; }
			.keycode-table input[type='text'] { width: 90px; }
			td.center input, td.center select { text-align: center; }
			p { max-width: 800px; }
		</style>
		<?php

		echo '<!-- '.print_r($this->_settings,1).' -->';

		echo '<form action="options.php" method="post" id="harbor_prd">';

			settings_fields($this->_optionsGroup);

			echo '<table class="form-table">';

			$root = $this->_optionsName;
			foreach ($this->_options as $key => $d) {
				if (strpos($key, 'header') === 0) {
					echo '<tr valign="top" class="head-row">';
					echo '<td colspan=2><h3>'.$d['label'].'</h3></td>';
					echo '<td style="vertical-align: bottom;"><small>'.$d['help'].'</small></td>';
					echo '</tr>';
					echo '<tr><td colspan=3>&nbsp;</td></tr>';
				} else {
					if ($d['type'] == 'radio') {
						echo '<tr valign="top">';
						echo '<td scope="row"><label for="'.$root.'_'.$key.'">'.$d['label'].'</label></td>';
						echo '<td>';
						foreach ($d['values'] as $vk => $v) {
							echo '<label><input type="radio" name="'.$root.'['.$key.']" value="'.$v.'" id="'.$root.'_'.$key.'" ';
								if ($this->_settings[$key] == $v) { echo 'checked'; }
							echo ' />&nbsp;'.$d['labels'][$vk].'</label><br/>';
						}
						echo '</td>';
						echo '<td><small>'.$d['help'].'</small></td>';
						echo '</tr>';
					} else {
						echo '<tr valign="top">';
						echo '<td scope="row"><label for="'.$root.'_'.$key.'">'.$d['label'].'</label></td>';
						echo '<td><input type="text" name="'.$root.'['.$key.']" value="'.$this->_settings[$key].'" id="'.$root.'_'.$key.'" class="regular-text code" /></td>';
						echo '<td><small>'.$d['help'].'</small></td>';
						echo '</tr>';
					}
				}
			}

			echo '<tr><td colspan=3><p class="submit"><input type="submit" name="Submit" class="button button-primary" value="'.__('Update Options &raquo;', 'harbor-prd').'" /></p></td></tr>';

			$pubs = get_pubs(true);

			$services = array(
				'su'	=> 'Subscription',
				'sp'	=> 'Special Programs',
				'ca'	=> 'Catalog Product',
				'cn'	=> 'Continuity',
			);

			$default_entitlements = array(
				'0'		=> 'None, Entitlements Provided by Gatekeeper',
				'M'		=> 'One Month',
				'Y'		=> 'One Year',
				'P'		=> 'Perpetuity',
			);

			$products = $this->_settings['products'];

			echo '<tr valign="top" class="head-row">';
			echo '<td colspan=2><h3>PRD Product Codes</h3></td>';
			echo '<td style="vertical-align: bottom;"><small>Created by client in conjunction with PRD. Changes in PRD product codes must be activated here concurrently to assure proper customer entitlements.</small></td>';
			echo '</tr>';

			echo '<tr><td colspan=3>&nbsp;</td></tr>';

			echo '<tr><td colspan=3>';
			
			echo '<p>The following PRD Secondary Product Codes are baked into the system and do not need to be specified: <strong>COMBO</strong> (all access combo subscription), <strong>DIGITAL</strong> (digital only combo subscription), <strong>PRINT</strong>, <strong>TABLET</strong>, <strong>WEB</strong>. The system also recognizes the following deprecated values: ALL-ACCESS (alternate to COMBO), DIG-COMBO (alternate to DIGITAL), and ALL (an alternate to WEB, but only for web-only products)</p>';

			echo '</td></tr>';

			echo '<tr><td colspan=3>&nbsp;</td></tr>';

			echo '<tr><td colspan=3><table class="keycode-table">';

			echo '<tr valign="top" class="head-row">';
			echo '<td align=center rowspan=2>PRD<br/>Product</td>';
			echo '<td align=center rowspan=2>PRD<br/>Secondary</td>';
			echo '<td align=center rowspan=2>Harbor<br/>Publication</td>';
			echo '<td colspan="'.count($services).'" align=center><strong style="font-size: 15px; color: #444;">PRD Programs</strong></td>';
			echo "<td rowspan=2>&nbsp;</td>";
			echo '<td colspan="4" align=center><strong style="font-size: 15px; color: #444;">Default Entitlement</strong></td>';
			echo '<td align=center rowspan=2>Delete</td>';
			echo '</tr>';

			echo '<tr valign="top" class="head-row">';
				foreach ($services as $service_code => $service_name) {
					echo '<td align=center title="'.$service_name.'">'.strtoupper($service_code).'</td>';
				}
				foreach ($default_entitlements as $entitlement_code => $entitlement_name) {
					if ( !$entitlement_code ) { $entitlement_code = '&#8416;'; }
					echo '<td align=center title="'.$entitlement_name.'">'.strtoupper($entitlement_code).'</td>';
				}
			echo '</tr>';

			$key = 0;

			if ($products) {

				foreach ($products as $key => $prod) {
					$pub_options = "<option value=''>Select Publication</option>";
					$pub_options .= "<option value='REQUIRE_SECONDARY' ".$sec.">Require Secondary Code</option>";
					$service_buttons = $entitlement_buttons = $disable = false;

					if ( $prod['service'] == 'sp' || $prod['service'] == 'su' ) {
						$disable = " disabled";
						unset($prod['entitle']);
					}

					foreach ($pubs as $p) {
						$pub_options .= "<option value='".$p['pub_id']."' ";
						if ($p['pub_id'] == $prod['pub_id']) { $pub_options .= "selected"; }
						$pub_options .= ">".$p['title']."</option>";
					}

					foreach ($services as $service_code => $service_name) {
						$service_buttons .= '<td align=center><input type=radio name="harbor-prd[products]['.$key.'][service]" title="'.$service_name.'" value="'.$service_code.'" ';
						if ($prod['service'] == $service_code) { $service_buttons .= "checked"; }
						$service_buttons .= '/></td>';
					}

					foreach ($default_entitlements as $entitlement_code => $entitlement_name) {
						$entitlement_buttons .= '<td align=center><input type=radio name="harbor-prd[products]['.$key.'][entitle]" title="'.$entitlement_name.'" value="'.$entitlement_code.'" '.$disable.' ';
						if ($prod['entitle'] == $entitlement_code) { $entitlement_buttons .= 'checked'; }
						$entitlement_buttons .= '/></td>';
					}

					echo '<tr valign="top">';
					echo '<td align=center class="center"><input type="text" name="harbor-prd[products]['.$key.'][product_id]" value="'.$prod['product_id'].'"></td>';
					echo '<td align=center class="center"><input type="text" name="harbor-prd[products]['.$key.'][secondary_product_id]" value="'.$prod['secondary_product_id'].'"></td>';
					echo '<td><select name="harbor-prd[products]['.$key.'][pub_id]">'.$pub_options.'</select></td>';

					echo $service_buttons;

					echo "<td>&nbsp;</td>";

					echo $entitlement_buttons;

					echo '<td align=center class="center"><input type="checkbox" value="delete" name="harbor-prd[products]['.$key.'][delete]"></td>';

					echo '</tr>';

					echo '<tr class="closing-row"><td colspan=13></tr></tr>';

				}

			}

			$key++;

			$pub_options = "<option value=''>Select Publication</option>";
			$service_buttons = '';
			foreach ($pubs as $p) { $pub_options .= "<option value='".$p['pub_id']."'>".$p['title']."</option>"; }
			foreach ($services as $service_code => $service_name) {
				$service_buttons .= '<td align=center><input type=radio name="harbor-prd[products]['.$key.'][service]" value="'.$service_code.'" /></td>';
			}

			echo '<tr><td colspan=8><small> &nbsp; Add new PRD Product Codes below.</small></td></tr>';

			echo '<tr valign="top">';
			echo '<td align=center class="center"><input type="text" name="harbor-prd[products]['.$key.'][product_id]" value=""></td>';
			echo '<td align=center class="center"><input type="text" name="harbor-prd[products]['.$key.'][secondary_product_id]" value=""></td>';
			echo '<td><select name="harbor-prd[products]['.$key.'][pub_id]">'.$pub_options.'</select></td>';
			echo $service_buttons;
			echo '<td align=center class="center"></td>';
			echo '</tr>';
			echo '</table></td></tr>';

			echo '<tr><td colspan=3><p class="submit"><input type="submit" name="Submit" class="button button-primary" value="'.__('Update Options &raquo;', 'harbor-prd').'" /></p></td></tr>';


		echo '</form>';

		echo '</div>';
	}

	public function sanitize_options($options) {

		// clear empty products
		foreach($options['products'] as $key => $o) {
			if (empty($o['product_id']) || $o['delete'] == 'delete') {
				unset($options['products'][$key]);
			}
			if ($o['sec']) {
				foreach($o['sec'] as $sk => $s) {
					if (empty($s['product_id']) || $s['delete'] == 'delete') {
						unset($options['products'][$key]['sec'][$sk]);
					}
				}
			}
		}

		// re-create keys
		array_values($options['products']);

		// sort by product_id - https://stackoverflow.com/questions/2699086/sort-multi-dimensional-array-by-value
		//usort( $options['products'], function($a, $b) { return $a['product_id'] <=> $b['product_id']; } );

		return $options;
	}

	private $_options = array(
		'header_00'			=> array(
			'label'			=> 'Site Options',
			),
		'org'				=> array(
			'label'			=> 'PRD Org ID',
			'help'			=> 'Provided by PRD',
			'default'		=> '',
			),
		'app_version'		=> array(
			'label'			=> 'PRD App Version',
			'help'			=> 'Provided by PRD',
			'default'		=> '',
			),
		'base_url'			=> array(
			'label'			=> 'Base PRD API URL',
			'help'			=> 'Provided by PRD',
			'default'		=> 'https://api.prdnetwork.com',
			),
		'default_currency'	=> array(
			'label'			=> 'Default Currency',
			'help'			=> 'Default currency if not provided as part of order. See PRD docs for acceptable values.',
			'default'		=> 'US',
			),
		'terminal'			=> array(
			'label'			=> 'Terminal Payments',
			'help'			=> 'Activate PRD payments terminal (requires PRD Special Programs E-Commerce API access).',
			'default'		=> '1',
			'type'			=> 'radio',
			'labels'		=> array('Active', 'Inactive'),
			'values'		=> array('1', '0'),
			),
		'pull_userdata'	=> array(
			'label'			=> 'Pull Address Info',
			'help'			=> 'Pull subscriber contact and shipping changes from PRD to Harbor whenever Gatekeeper API is called.',
			'default'		=> '0',
			'type'			=> 'radio',
			'labels'		=> array('Update customer data in Harbor on Gatekeeper calls', 'Do not use PRD data to update addresses in Harbor'),
			'values'		=> array('1', '0'),
			),
		'push_userdata'	=> array(
			'label'			=> 'Push Address Info',
			'help'			=> 'Push subscriber contact and shipping changes from Harbor to PRD using the Subscriber Update API',
			'default'		=> '0',
			'type'			=> 'radio',
			'labels'		=> array('Push changes made in Harbor to PRD', 'Do not send address changes to PRD'),
			'values'		=> array('1', '0'),
			),
		'perpetuity'		=> array(
			'label'			=> 'Define Perpetuity',
			'help'			=> 'Enter the realistic product lifetime, in years, of perpetually entitled digital products.',
			'default'		=> '20',
			),
		'header_10'			=> array(
			'label'			=> 'Special Programs E-commerce API Keys',
			'help'			=> 'This API used for placing initial online orders, one off and subscriptions.',
			),
		'userid'			=> array(
			'label'			=> 'UserID',
			'help'			=> 'Provided by PRD.',
			),
		'pw'				=> array(
			'label'			=> 'Password',
			'help'			=> 'Provided by PRD.',
			),
		'header_20'			=> array(
			'label'			=> 'Manage Special Programs API Keys',
			'help'			=> 'This API used for modifying expiry dates and card numbers, and for issuing refunds.',
			),
		'userid2'			=> array(
			'label'			=> 'UserID',
			'help'			=> 'Provided by PRD.',
			),
		'pw2'				=> array(
			'label'			=> 'Password',
			'help'			=> 'Provided by PRD.',
			),
		'header_30'			=> array(
			'label'			=> 'Gatekeeper API Keys',
			'help'			=> 'This API used for querying customer history.',
			),
		'userid3'			=> array(
			'label'			=> 'UserID',
			'help'			=> 'Provided by PRD.',
			),
		'pw3'				=> array(
			'label'			=> 'Password',
			'help'			=> 'Provided by PRD.',
			),
		'header_40'			=> array(
			'label'			=> 'Customer Update API Keys',
			'help'			=> 'This API used for modifying customer information.',
			),
		'userid4'			=> array(
			'label'			=> 'UserID',
			'help'			=> 'Provided by PRD.',
			),
		'pw4'				=> array(
			'label'			=> 'Password',
			'help'			=> 'Provided by PRD.',
			),
		'header_50'			=> array(
			'label'			=> 'Debug Settings',
			),
		'bypass_server_check'	=> array(
			'label'		=> 'Pre-check API Availability',
			'help'		=> 'Pre-checking API availability will incur a small performance penalty and should only be used when API outages are expected.',
			'default'	=> '0',
			'type'		=> 'radio',
			'labels'	=> array('Ping API prior to all requests', 'Assume API is available <em>(default operation)</em>'),
			'values'	=> array('1', '0'),
			),
		'test_mode'			=> array(
			'label'			=> 'PRD Test Mode',
			'help'			=> 'All orders and customers created while in Test Mode will only exist on PRD\'s servers if queried within Test Mode. Orders and customer\'s created in production mode will be invisible when queried via Gatekeeper, and vice versa.',
			'default'		=> 'Y',
			'type'			=> 'radio',
			'labels'		=> array('Test Mode', 'Production Mode'),
			'values'		=> array('Y', 'N'),
			),
		'debug'				=> array(
			'label'			=> 'Debugging',
			'help'			=> 'Activating this may send between 3 and 9 emails <i>per transaction</i>.',
			'default'		=> '0',
			'type'			=> 'radio',
			'labels'		=> array('Send Debugging Emails', 'No Emails'),
			'values'		=> array('1', '0'),
			),
		'debug_email'		=> array(
			'label'			=> 'Debugging Addresses',
			'help'			=> 'Comma delimited list of email addresses to receive debug emails',
			'default'		=> '',
			),
	);

// ------------------------------------------------------------------------
// TERMINAL PAGE - DISPLAY

	public function terminal() {

		global $wpdb;

		$plugin_data = get_plugin_data(__FILE__, 0, 0);

		$cards = array('VISA' => 'Visa', 'MC' => 'Mastercard', 'AMEX' => 'American Express', 'DISC' => 'Discover');
		$months = range(1, 12);
		$yearstart = (int) date('Y', time());
		$years = range($yearstart, ($yearstart+10));
		$states = prd_state_array();
		$countries = prd_country_array_for_terminal();

		$message = false;
		$refund = false;
		$history = false;
		$search = $search_email = $search_last = $search_prd_id = $search_think_id = false;
		$this->_message = '';
		$user_info = false;

		if (($_POST['trigger'] == 'charge') && check_admin_referer('harbor-prd-terminal-charge')) {
			$result = $this->_terminal_charge_via_prd($_POST);
			if ($result) {
				$type = $cards[$_POST['card_type']];
				$card = substr($_POST['card_number'], -4);
				$amt = number_format(preg_replace('/[\$,]/', '', $_POST['price']), 2, '.', '');
				$message = '<div class="updated"><p><b>SUCCESS</b><br/>';
				$message .= 'The '.$type.' card ending in '.$card.' has been successfuly charged $'.$amt.'.<br/>';
				$message .= 'PRD Transaction ID: '.$this->_transactionID.'</p></div>';
				$_POST = array();
			} else {
				$message = '<div class="error"><p><b>CHARGE FAILURE</b><br/>';
				foreach ($this->_errors as $e) {
					$message .= $e.'<br/>';
				}
				$message .= '<b>The card was not charged.</b></p></div>';
			}
		} else if (($_POST['trigger'] == 'refund') && check_admin_referer('harbor-prd-terminal-refund')) {
			$result = $this->_terminal_refund_via_prd($_POST['refund_id'], $_POST['amount']);
			if ($result) {
				$message = '<div class="updated"><p><b>SUCCESS</b><br/>';
				$message .= 'The requested refund has been successfuly posted.<br/>';
				$message .= 'PRD Transaction ID: '.$this->_transactionID.'</p></div>';
				$_POST = array();
			} else {
				$message = '<div class="error"><p><b>REFUND FAILURE</b><br/>';
				foreach ($this->_errors as $e) {
					$message .= $e.'<br/>';
				}
				$message .= '<b>No refund was issued.</b></p></div>';
			}
			$refund = true;
		} else if (($_POST['trigger'] == 'history') && check_admin_referer('harbor-prd-terminal-history')) {
			$search_prd_customer_id = esc_attr($_POST['search_prd_customer_id']);
			if (empty($search_prd_customer_id)) {
				$search_harbor_email = esc_attr($_POST['search_harbor_email']);
				$sql = $wpdb->prepare("SELECT m.meta_value FROM wp_usermeta m JOIN wp_users u ON m.user_id = u.ID AND m.meta_key = 'prd_customer_id' WHERE (u.user_email = %s);", $search_harbor_email);
				$search_prd_customer_id = $wpdb->get_var($sql);
			}
			if (is_numeric($search_prd_customer_id)) {
				$search_for = $search_prd_customer_id;
			} else {
				$search_for = $search_harbor_email;
			}
			if (!empty($search_for)) {
				$result = $this->customer_history($search_for);
				if ($result) {
					$message = '<div class="updated"><p><b>SUCCESS</b><br/>';
					$message .= 'The requested information has been found.<br/></div>';
					$_POST = array();
					$sql = $wpdb->prepare("SELECT m2.meta_value AS first_name, m3.meta_value AS last_name, u.user_email FROM wp_usermeta m1 JOIN wp_usermeta m2 ON m1.user_id = m2.user_id AND m2.meta_key = 'first_name' JOIN wp_usermeta m3 ON m1.user_id = m3.user_id AND m3.meta_key = 'last_name' JOIN wp_users u ON m1.user_id = u.ID WHERE (m1.meta_key = 'prd_customer_id') AND (m1.meta_value = %s);", $search_prd_customer_id);
					$f = $wpdb->get_row($sql, ARRAY_A);
					$user_info = (is_array($f)) ? '<h3><a href="mailto:'.$f['user_email'].'">'.$f['first_name'].'&nbsp;'.$f['last_name'].'</a></h3>': false;
				} else {
					$message = '<div class="error"><p><b>HISTORY NOT FOUND</b><br/>';
					if (is_array($this->_errors)) {
						foreach ($this->_errors as $e) {
							$message .= $e.'<br/>';
						}
					}
					$message .= '<b>Customer history query was not successful.</b></p></div>';
				}
			} else {
				$message = '<div class="error"><p><b>SEARCH FAILURE</b><br/>';
				$message .= '<b>Could not find the PRD customer information based on your input ('.$search_for.').</b></p></div>';
			}
			$history = true;
		} else if (($_POST['trigger'] == 'search') && check_admin_referer('harbor-prd-terminal-search')) {
			$search_email = esc_attr($_POST['search_email']);
			$search_last = esc_attr($_POST['search_last']);
			$search_prd_id = esc_attr($_POST['search_prd_id']);
			$search_think_id = esc_attr($_POST['search_think_id']);
			$search = true;
			$refund = true;
		}

		if ( ! $message ) {
			if ( ! $this->is_prd_awake() ) {
				$message = '<div class="error"><p><b>PRD SERVER PROBLEM</b><br/>The PRD API server is not responding to requests at the moment.</p></div>';
			}
		}

		?>

		<style>
			div.tab-panel { position: relative; display: block; width: 95%; float: left; margin-top: 50px; border-radius: 0 10px 10px 10px; border: 1px solid #aaa; padding: 10px; }
			ul.tabs { position: absolute; top: -52px; left: -1px; }
			ul.tabs li { position: absolute; display: block; height: 23px; top: 0; border: 1px solid #aaa; border-bottom: none; background-color: #f1f1f1; border-radius: 10px 10px 0 0; padding: 10px 10px 5px; width: 184px; text-align: center; font-size: 120%; font-weight: bold; color: #0074a2; z-index: 999; }
			ul.tabs li.newsub-tab { left: 200px; }
			ul.tabs li.charge-tab { left: 400px; }
			ul.tabs li.refund-tab { left: 600px; }
			ul.tabs li.inactive { top: -4px; height: 26px; background-color: #e1e1e1; color: #999; cursor: pointer; z-index: 998; }
			.terminal { width: 100%; margin-bottom: 20px;}
			.terminal p { position: relative; }
			.terminal hr { margin-top: 1.33em;  }
			.terminal p input, form.terminal p select { position: absolute; left: 180px; width: 300px; }
			.terminal p.submit input[type='submit'] { float: right; width: 120px; font-size: 120%; }
			.terminal table { width: 100%; border-spacing: 0; margin-bottom: 20px;}
			.terminal table tr.header { background-color: #666; color: #fff; font-weight: bold; font-size: 110%; text-align: center; }
			.terminal table td { padding: 4px 2px; text-align: center; border-bottom: 1px solid #ccc; }
			.terminal table td.left { text-align: left; }
			.terminal input.refund { position: relative; left: 0; width: 65px; text-align: right; vertical-align: bottom }
			.terminal .tinysubmit { width: 31px; height: 26px; font-size: 70%; cursor: pointer; padding: 0; margin-left: 3px; }
			.terminal h3 { margin-bottom: 0.5em; }
			.terminal a { text-decoration: none; }
		</style>

		<script>
			jQuery(document).ready(function(){
				jQuery('ul.tabs li.inactive').live('click', function(){
					jQuery('ul.tabs li').addClass('inactive');
					jQuery(this).removeClass('inactive');
					var activate = '.'+jQuery(this).data('tab');
					jQuery('.terminal').hide();
					jQuery(activate).show();
				})

				jQuery('#ajax-button').live('click', function(e) {

					var custno = jQuery('#custno').val();
					var email = jQuery('#email').val();
					var firstname = jQuery('#firstname').val();
					var lastname = jQuery('#lastname').val();
					var address1 = jQuery('#address1').val();
					var address2 = jQuery('#address2').val();
					var city = jQuery('#city').val();
					var state = jQuery('#state').val();
					var zip = jQuery('#zip').val();
					var country = jQuery('#country').val();
					var keycode = jQuery('#keycode').val();
					var transid = jQuery('#transid').val();
					var harborsc = jQuery('#harborsc').val();
					var uid = jQuery('#uid').val();

					var mydata = { 'custno' : custno, 'email' : email, 'firstname' : firstname, 'lastname' : lastname, 'address1' : address1, 'address2' : address2, 'city' : city, 'state' : state, 'zip' : zip, 'country' : country, 'keycode' : keycode, 'transid' : transid, 'harborsc' : harborsc, 'uid' : uid };

					var ajaxURL = '/wp-content/plugins/harbor-prd/ajax-flexpage.php';
					jQuery.ajax({
						url: ajaxURL,
						type: 'POST',
						crossDomain: true,
						data: { 'order' : mydata },
						dataType: 'json',
						success:function(data) {
							alert( "ORDER PLACED, SUBSCRIBER CREATED IF NECESSARY\r\nUser ID: " + data.user + "\r\nOrder ID: " + data.order );
						},
						error:function(xhr, status, error){
							alert( "Order Failed.\r\n\r\nerr: " + error + "\r\nsts: " + status + "\r\nxhr: " + xhr );
						}
					});
				});

			})
		</script>

		<?php

		$harbor_prd = get_option('harbor-prd');
		$terminal = ($harbor_prd['terminal']) ? true : false;
		$history = ($terminal) ? $history : true;

		echo '<div class="wrap">';
		echo '<h2>'.__($plugin_data['Title']).' Terminal - Version '.__($plugin_data['Version']).'</h2>';

		echo $message;

		echo "<hr/>";

		echo "<div class='tab-panel'>";

		echo "<ul class='tabs'>";
			if ($refund) {
				echo "<li data-tab='history-form' class='inactive history-tab'>Customer History</li>";
				echo "<li data-tab='newsub-form' class='inactive newsub-tab'>New Subscriber</li>";
				if ($terminal) {
					echo "<li data-tab='refund-form' class='refund-tab'>Refund Terminal</li>";
					echo "<li data-tab='charge-form' class='inactive charge-tab'>Charge Terminal</li>";
				}
				echo "<style>";
				echo ".charge-form { display: none; }";
				echo ".refund-form { display: block; }";
				echo ".history-form { display: none; }";
				echo ".newsub-form { display: none; }";
				echo "</style>";
			} else if ($history) {
				echo "<li data-tab='history-form' class='history-tab'>Customer History</li>";
				echo "<li data-tab='newsub-form' class='inactive newsub-tab'>New Subscriber</li>";
				if ($terminal) {
					echo "<li data-tab='refund-form' class='inactive refund-tab'>Refund Terminal</li>";
					echo "<li data-tab='charge-form' class='inactive charge-tab'>Charge Terminal</li>";
				}
				echo "<style>";
				echo ".charge-form { display: none; }";
				echo ".refund-form { display: none; }";
				echo ".history-form { display: block; }";
				echo ".newsub-form { display: none; }";
				echo "</style>";
			} else if ($newsub) {
				echo "<li data-tab='history-form' class='inactive history-tab'>Customer History</li>";
				echo "<li data-tab='newsub-form' class='newsub-tab'>New Subscriber</li>";
				if ($terminal) {
					echo "<li data-tab='refund-form' class='inactive refund-tab'>Refund Terminal</li>";
					echo "<li data-tab='charge-form' class='inactive charge-tab'>Charge Terminal</li>";
				}
				echo "<style>";
				echo ".charge-form { display: none; }";
				echo ".refund-form { display: none; }";
				echo ".history-form { display: none; }";
				echo ".newsub-form { display: block; }";
				echo "</style>";
			} else  {
				echo "<li data-tab='history-form' class='inactive history-tab'>Customer History</li>";
				echo "<li data-tab='newsub-form' class='inactive newsub-tab'>New Subscriber</li>";
				if ($terminal) {
					echo "<li data-tab='refund-form' class='inactive refund-tab'>Refund Terminal</li>";
					echo "<li data-tab='charge-form' class='charge-tab'>Charge Terminal</li>";
				}
				echo "<style>";
				echo ".charge-form { display: block; }";
				echo ".refund-form { display: none; }";
				echo ".history-form { display: none; }";
				echo ".newsub-form { display: none; }";
				echo "</style>";
			}
		echo "</ul>";

		if ($terminal) {

			echo '<form action="admin.php?page=prd-terminal" method="post" id="harbor_prd_terminal" class="terminal charge-form">';

				echo '<h4>'.__('Order Information').'</h4>';
				echo '<p><label>Amount (USD): <input type="text" name="price" value="'.esc_attr($_POST['price']).'"></label></p>';
				echo '<p><label>THINK Order No.: <input type="text" name="think_id" value="'.esc_attr($_POST['think_id']).'"></label></p>';

				echo "<hr/>";

				echo '<h4>'.__('Card Information').'</h4>';
				echo '<p><label>Card Type: <select name="card_type"><option value="">Select...</option>';
					foreach ($cards as $key => $c) {
						echo '<option value="'.$key.'"';
						if (esc_attr($_POST['card_type']) == $key) { echo ' selected'; }
						echo '>'.$c.'</option>';
					}
				echo '</select></label></p>';
				echo '<p><label>Card Number: <input type="text" name="card_number" value="'.esc_attr($_POST['card_number']).'"></label></p>';
				echo '<p><label>Expiration Month: <select name="exp_month"><option value="">Select...</option>';
					foreach ($months as $m) {
						echo '<option value="'.$m.'"';
						if (esc_attr($_POST['exp_month']) == $m) { echo ' selected'; }
						echo '>'.$m.'</option>';
					}
				echo '</select></label></p>';
				echo '<p><label>Expiration Year: <select name="exp_year"><option value="">Select...</option>';
					foreach ($years as $y) {
						echo '<option value="'.$y.'"';
						if (esc_attr($_POST['exp_year']) == $y) { echo ' selected'; }
						echo '>'.$y.'</option>';
					}
				echo '</select></label></p>';
				echo '<p><label>CVV2: <input type="text" name="cvv2" value="'.esc_attr($_POST['cvv2']).'"></label></p>';

				echo "<hr/>";

				echo '<h4>'.__('Customer Information').'</h4>';
				echo '<p><label>Email: <input type="text" name="user_email" value="'.esc_attr($_POST['user_email']).'"></label></p>';
				echo '<p><label>First Name: <input type="text" name="first_name" value="'.esc_attr($_POST['first_name']).'"></label></p>';
				echo '<p><label>Last Name: <input type="text" name="last_name" value="'.esc_attr($_POST['last_name']).'"></label></p>';
				echo '<p><label>Address: <input type="text" name="address" value="'.esc_attr($_POST['address']).'"></label></p>';
				echo '<p><label>City: <input type="text" name="city" value="'.esc_attr($_POST['city']).'"></label></p>';
				echo '<p><label>State: <select name="state"><option value="">Select...</option>';
					foreach ($states as $key => $s) {
						echo '<option value="'.$key.'"';
						if (esc_attr($_POST['state']) == $key) { echo ' selected'; }
						echo '>'.$s.'</option>';
					}
				echo '</select></label></p>';
				echo '<p><label>Zip: <input type="text" name="zip_code" value="'.esc_attr($_POST['zip_code']).'"></label></p>';
				echo '<p><label>Country: <select name="country"><option value="">Select...</option>';
					echo '<option value="USA" ';
						if (esc_attr($_POST['country']) == 'USA') { echo ' selected'; }
						echo '>UNITED STATES</option>';
					echo '<option value="CANADA" ';
						if (esc_attr($_POST['country']) == 'CANADA') { echo ' selected'; }
						echo '>CANADA</option>';
					foreach ($countries as $c) {
						echo '<option value="'.$c.'"';
						if (esc_attr($_POST['country']) == $c) { echo ' selected'; }
						echo '>'.$c.'</option>';
					}
				echo '</select></label></p>';
				echo '<p><label>Phone: <input type="text" name="phone" value="'.esc_attr($_POST['phone']).'"></label></p>';

				echo "<hr/>";

				echo '<input type="hidden" name="trigger" value="charge">';
				echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="Submit Charge &raquo;" /></p>';

				wp_nonce_field('harbor-prd-terminal-charge');

			echo '</form>';

			echo '<div class="terminal refund-form">';

				echo "<p>The Refund Terminal may only be used to grant refunds for transactions placed on the Charge Terminal. To refund subscription orders or online Shopp orders please visit the customer's page under Users and refund the purchased product or subscription directly.</p>";

				if ($search) {
					$sql = "SELECT * FROM wp_Harbor_orders WHERE (product_name LIKE 'TERMINAL PAYMENT') AND (RESULT = 1) ";
					if ($search_email) { $sql .= "AND (user_email LIKE '%".$search_email."%') "; }
					if ($search_last) { $sql .= "AND (last_name LIKE '%".$search_last."%') "; }
					if ($search_prd_id) { $sql .= "AND (prd_transaction_id = '".$search_prd_id."') "; }
					if ($search_think_id) { $sql .= "AND (think_orderhdr_id = '".$search_think_id."') "; }
					$sql .= "ORDER BY order_time DESC;";

					echo "<p>The results of your search are shown below.</p>";

				} else {

					echo "<p>Below are the twenty most recent terminal charges. If the order you would like to refund is not listed, please use the fields below to search the database.</p>";

					$sql = "SELECT * FROM wp_Harbor_orders WHERE (product_name LIKE 'TERMINAL PAYMENT') AND (RESULT = 1) ORDER BY order_time DESC LIMIT 20;";
				}
				$orders = $wpdb->get_results($sql, ARRAY_A);

				if ($orders) {
					echo '<table>';
					echo '<tr class="header"><td>Date</td><td>Customer</td><td>Email</td><td>PRD ID</td><td>Think ID</td><td>Amount</td><td>Refund</td></tr>';
					foreach ($orders as $o) {
						echo '<tr>';
						$confirm = "'Do you really want to refund this transaction?'";
						echo '<form action="admin.php?page=prd-terminal" method="post" name="refund-form" onsubmit="return confirm('.$confirm.');">';
						echo '<td>'.date('M d, Y', strtotime($o['order_time'])).'</td>';
						echo '<td>'.$o['first_name'].' '.$o['last_name'].'</td>';
						echo '<td>'.$o['user_email'].'</td>';
						echo '<td>'.$o['prd_transaction_id'].'</td>';
						echo '<td>'.$o['think_orderhdr_id'].'</td>';
						echo '<td>$'.$o['payment'].'</td>';
						echo '<td><nobr><input type="text" name="amount" class="refund" placeholder="$0.00"><input type="submit" class="button-primary tinysubmit" value="GO"></nobr></td>';
						echo '<input type="hidden" name="trigger" value="refund">';
						echo '<input type="hidden" name="refund_id" value="'.$o['id'].'">';
						wp_nonce_field('harbor-prd-terminal-refund');
						echo '</form>';
						echo '</tr>';
					}
					echo '</table>';
				}

				echo "<hr/>";

				echo '<form action="admin.php?page=prd-terminal" name="search-form" method="post">';
				echo '<h4>'.__('Search Charge Terminal Transactions').'</h4>';
				echo '<p><label>Email: <input type="text" name="search_email" value="'.$search_email.'"></label></p>';
				echo '<p><label>Last Name: <input type="text" name="search_last" value="'.$search_last.'"></label></p>';
				echo '<p><label>PRD Transaction ID: <input type="text" name="search_prd_id" value="'.$search_prd_id.'"></label></p>';
				echo '<p><label>Think Order ID: <input type="text" name="search_think_id" value="'.$search_think_id.'"></label></p>';
				echo '<input type="hidden" name="trigger" value="search">';
				echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="Search &raquo;" /></p>';
				wp_nonce_field('harbor-prd-terminal-search');
				echo '</form>';

			echo '</div>';

		}

		echo '<div class="terminal history-form">';

			echo "<p>This allows you to query the PRD transaction history for a specific customer. Because of how orders are grouped in the PRD system, this function will only list recurring subscription orders, it will not show Terminal or Shopp orders.</p>";

			if ($user_info) { echo $user_info; }
			if (!empty($this->_message)) { echo $this->_message; }

			if (!empty($this->_prd_customer_number) && $search_prd_customer_id != $this->_prd_customer_number) { $search_prd_customer_id = $this->_prd_customer_number; }
			if (!empty($this->_prd_userid_email) && $search_harbor_email != $this->_prd_userid_email) { $search_harbor_email = $this->_prd_userid_email; }

			echo '<form action="admin.php?page=prd-terminal" name="search-form" method="post">';
			echo '<h4>'.__('Search PRD Customer History').'</h4>';
			echo '<p><label>PRD Customer ID: <input type="text" name="search_prd_customer_id" value="'.$search_prd_customer_id.'"></label></p>';
			echo '<p><label>Email Address: <input type="text" name="search_harbor_email" value="'.$search_harbor_email.'"></label></p>';
			echo '<input type="hidden" name="trigger" value="history">';
			if (current_user_can('manage_options')) {
				echo '<input type="checkbox" name="raw" value="raw" style="position: absolute; top: -20px; right: -4px; background-color: #f1f1f1; border-color: #f1f1f1; box-shadow: none;" title="Check to display raw output."';
				if ($this->_raw) { echo ' checked'; }
				echo '>';
			}
			echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="Search &raquo;" /></p>';
			wp_nonce_field('harbor-prd-terminal-history');
			echo '</form>';

		echo '</div>';

		echo '<div class="terminal newsub-form">';

			echo "<p>This form will insert a new subscriber into Harbor using the exact code that is used to insert subscribers coming from PRD. The new subscriber will recieve all mailings as if they had just subscribed via the FlexPage at PRD.</p>";

			echo "<p>Using this form will send transactional email to the new user immediately. Please use caution and double check your values.</p>";

			echo '<p>Currently this functionality is <em><strong>not applicable</strong></em> to new "POST Method" sites such as Ceramic Arts Network. An updated version of this feature will become available for these sites in the next major release.</p>';

			echo "<hr/>";

			echo "<p><b>Notes</b><ul>
				<li>Fields in bold are required.</li>
				<li>PRD Customer Number can be found by using the Customer History tab on this page.</li>
				<li>Harbor UserID is optional, but <em><strong>required if the user already exists in Harbor</strong></em>.</li>
			</ul>";

			echo "<hr/>";

			echo '<form name="newsub-ajax-form" id="ajax">';
			echo '<h4>'.__('Insert New Subscriber').'</h4>';
			echo '<p><label><strong>PRD Customer Number</strong>: <input type="text" id="custno" value=""></label></p>';
			echo '<p><label><strong>Email</strong>: <input type="text" id="email" value=""></label></p>';
			echo '<p><label><strong>First Name</strong>: <input type="text" id="firstname" value=""></label></p>';
			echo '<p><label><strong>Last Name</strong>: <input type="text" id="lastname" value=""></label></p>';
			echo '<p><label><em>Address 1</em>: <input type="text" id="address1" value=""></label></p>';
			echo '<p><label><em>Address 2</em>: <input type="text" id="address2" value=""></label></p>';
			echo '<p><label><em>City</em>: <input type="text" id="city" value=""></label></p>';
			echo '<p><label><em>State</em>: <input type="text" id="state" value=""></label></p>';
			echo '<p><label><em>Zip</em>: <input type="text" id="zip" value=""></label></p>';
			echo '<p><label><em>Country</em>: <input type="text" id="country" value=""></label></p>';
			echo '<p><label>Keycode: <input type="text" id="keycode" value=""></label></p>';
			echo '<p><label><em>Transaction ID</em>: <input type="text" id="transid" value=""></label></p>';
			echo '<p><label><strong>Harbor MQSC</strong>: <input type="text" id="harborsc" value=""></label></p>';
			echo '<p><label><em>Harbor User ID</em>: <input type="text" id="uid" value=""></label></p>';
			echo '<p class="submit"><input type="button" id="ajax-button" class="button button-primary" value="Subscribe &raquo;" /></p>';
			wp_nonce_field('harbor-prd-terminal-newsub');
			echo '</form>';

		echo '</div>';

		echo '</div>'; //tab-panel

		echo '</div>'; //wrap
	}

// ------------------------------------------------------------------------
// TERMINAL PAGE - TRANSACTIONS

	private function _terminal_charge_via_prd($post_values) {

		if ( ! $this->is_prd_awake() ) { return false; }

		$current_user = wp_get_current_user();

		$harbor_prd = get_option('harbor-prd');
		if (!$harbor_prd) {
			$this->_errors[] = 'The credit card processor has not been configured.';
			return false;
		}

		$debug = $debug_email = false;
		if (!empty($harbor_prd['debug'])) {
			$debug = true;
			$debug_email = $harbor_prd['debug_email'];
		}

		if ($debug) {
			$clean_post_values = $post_values;
			$clean_post_values['card_number'] = preg_replace('/[0-9]/', 'x', substr($post_values['card_number'], 0, -4)) . substr($post_values['card_number'], -4);
			mail($debug_email, '1.TERMINAL CHARGE INITIAL VALUES', print_r($clean_post_values, true));
		}

		$headers = array();

		$currency = 'US';
		$price = number_format(preg_replace('/[\$,]/', '', $post_values['price']), 2, '.', '');

		$cvv2 = esc_attr($post_values['cvv2']);
		$cc_require_verify = (empty($cvv2)) ? 'N' : 'Y';

		$post_values = $this->_sanitize_data_set($post_values);
		$post = array(
			'org'					=> $harbor_prd['org'],
			'app_version'			=> $harbor_prd['app_version'],
			'test_mode'				=> $harbor_prd['test_mode'],
			'program_type_id'		=> 'MS',
			'payment_type'			=> 'C',
			'currency'				=> $currency,
			'order_amount'			=> $price,
			'amount_paid'			=> $price,
			'cc_number'				=> esc_attr($post_values['card_number']),
			'cc_exp_mm'				=> sprintf("%02d", $post_values['exp_month']),
			'cc_exp_yyyy'			=> $post_values['exp_year'],
			'cc_trans_type'			=> 'charge',
			'cc_amount'				=> $price,
			'cc_name'				=> esc_attr($post_values['first_name']).' '.esc_attr($post_values['last_name']),
			'cc_addr'				=> esc_attr($post_values['address']),
			'cc_city'				=> esc_attr($post_values['city']),
			'cc_state'				=> esc_attr($post_values['state']),
			'cc_zip'				=> esc_attr($post_values['zip_code']),
			'cc_country'			=> esc_attr($post_values['country']),
			'cc_verify'				=> $cvv2,
			'cc_require_verify'		=> $cc_require_verify,
		);

		$c = array(
			'customer_number'		=> '',
			'org'					=> '',
			'refresh_customer'		=> 'N',
			'first'					=> esc_attr($post_values['first_name']),
			'last'					=> esc_attr($post_values['last_name']),
			'add1'					=> esc_attr($post_values['address']),
			'city'					=> esc_attr($post_values['city']),
			'st'					=> esc_attr($post_values['state']),
			'zip'					=> esc_attr($post_values['zip_code']),
			'country'				=> esc_attr($post_values['country']),
			'email'					=> esc_attr($post_values['user_email']),
			'optin'					=> 'N',
		);

		$sp[0]  = array(
			'gift_ref'				=> '',
			'program_type_id'		=> 'SP',
			'product'				=> 'TRMNL',
			'key_code'				=> 'Harbor',
			'offer_pmt'				=> $price,
			'offer_tax'				=> 0,
			'offer_baldue'			=> 0,
			'offer_baldue_date'		=> '',
			'recurring_amt'			=> 0,
			'recurring_date'		=> '',
			'period'				=> 'O',
			'frequency'				=> '1',
			);

		$g = array();

		$post['customer'] = $c;
		$post['gift_customers'] = $g;
		$post['sp_orders'] = $sp;
		$post['su_orders'] = array();
		$post['cn_orders'] = array();
		$post['ca_orders'] = array();

		if ($debug) {
			$clean_post = $post;
			$clean_post['cc_number'] = preg_replace('/[0-9]/', 'x', substr($post['cc_number'], 0, -4)) . substr($post['cc_number'], -4);
			mail($debug_email, '2.TERMINAL CHARGE PRD POST', print_r($clean_post, true));
		}

		$post = http_build_query($post);

		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		$test = ($harbor_prd['test_mode'] == 'Y') ? 'test/' : '';
		$url = $harbor_prd['base_url'].'/rest/ecom_order/'.$harbor_prd['org'].'/v'.$harbor_prd['app_version'].'/'.$test.'json/';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_VERBOSE,true);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false );
		curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_SSLVERSION, '6');

		curl_setopt($ch, CURLOPT_USERPWD, $harbor_prd['userid'].':'.$harbor_prd['pw']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

		$response = curl_exec($ch);

		$r = json_decode($response);

		if ($debug) {
			mail($debug_email, '3.TERMINAL CHARGE PRD RESPONSE', print_r($r, true));
		}

		$success = ($r->response->ORDER_PROCESSED == 'Y') ? true : false;

		if ($success) {

			$payment_type = ($post_values['card_type']) ? $post_values['card_type'] : 'CC';
			//slashes prevent proper URLencoding
			$post_values= str_replace('\\', '', $post_values);
			$new_values = array(
				'RESULT'					=> '1',
				'RESPMSG'					=> 'success',
				'prd_transaction_id'		=> $r->response->TRANSACTION_ID,
				'prd_reference_id'			=> $r->response->REFERENCE_ID,
				'prd_sp_ref_id'				=> $r->response->SP_REF_IDS->TRMNL[0],
				'price'						=> $price,
				'payment'					=> $price,
				'prd_customer_id'			=> $r->response->CUSTOMER_NUMBER,
				'prd_json_response'			=> $response,
				'payment_type'				=> $payment_type,
				'purchase_order'			=> 'CSR ID: '.$current_user->ID.' / '.$current_user->first_name.' '.$current_user->last_name,
				'product_name'				=> 'TERMINAL PAYMENT',
				'order_summary'				=> 'Terminal Charge for $'.$price.' placed by '.$current_user->first_name.' '.$current_user->last_name,
				'first_name'				=> esc_attr($post_values['first_name']),
				'last_name'					=> esc_attr($post_values['last_name']),
				'address'					=> esc_attr($post_values['address']),
				'city'						=> esc_attr($post_values['city']),
				'state'						=> esc_attr($post_values['state']),
				'zip_code'					=> esc_attr($post_values['zip_code']),
				'country'					=> esc_attr($post_values['country']),
				'phone'						=> esc_attr($post_values['phone']),
				'user_email'				=> esc_attr($post_values['user_email']),
				'think_orderhdr_id'			=> $post_values['think_id'],
			);

			$this->_insert_order($new_values);

			$this->_transactionID = $r->response->TRANSACTION_ID;

			return true;

		} else {

			$prd_error = get_prd_error($r->error[0]->errno, 'ec');

			$this->_errors[] = $r->error[0]->errno.': <b>'.$prd_error.'</b>';

			return false;

		}
	}

	private function _terminal_refund_via_prd($refund_id, $amount) {

		if ( ! $this->is_prd_awake() ) { return false; }

		global $wpdb;
		$current_user = wp_get_current_user();

		$harbor_prd = get_option('harbor-prd');
		if (!$harbor_prd) {
			$this->_errors[] = 'The credit card processor has not been configured.';
			return false;
		}

		$debug = $debug_email = false;
		if (!empty($harbor_prd['debug'])) {
			$debug = true;
			$debug_email = $harbor_prd['debug_email'];
		}

		if ($debug) {
			mail($debug_email, '1.TERMINAL REFUND INITIAL VALUES', $refund_id.'\r\n'.$amount);
		}

		if (empty($amount)) {
			$this->_errors[] = 'Please enter a non-zero value for the refund amount.';
			return false;
		}

		$headers = array();

		$sql = "SELECT prd_customer_id, prd_transaction_id, prd_reference_id, prd_sp_ref_id, payment, think_orderhdr_id FROM wp_Harbor_orders WHERE (id = ".$refund_id.")";
		$order = $wpdb->get_row($sql, ARRAY_A);

		if (!$order) {
			$this->_errors[] = 'The transaction referenced in the refund order could not be found in the Harbor database.';
			return false;
		}

		if ($amount > $order['payment']) {
			$this->_errors[] = 'The refund amount must not exceed the original payment amount, or any remaining balance after subsequent refunds.';
			return false;
		}

		$post = array(
			'org'				=> $harbor_prd['org'],
			'program_type_id'	=> 'SP',
			'program_id'		=> 'TRMNL',
			'test_mode'			=> $harbor_prd['test_mode'],
			'app_version'		=> $harbor_prd['app_version'],
			'customer_number'	=> $order['prd_customer_id'],
			'refund'			=> 'Y',
			'refund_amt'		=> $amount,
			'sp_ref_id'			=> $order['prd_sp_ref_id'],
		);

		if ($debug) {
			mail($debug_email, '2.TERMINAL REFUND PRD POST', print_r($post, true));
		}

		$post = http_build_query($post);

		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		$test = ($harbor_prd['test_mode'] == 'Y') ? 'test/' : '';
		$url = $harbor_prd['base_url'].'/rest/manage_sp/'.$harbor_prd['org'].'/v'.$harbor_prd['app_version'].'/'.$test.'json/';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_VERBOSE,true);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false );
		curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_SSLVERSION, '6');

		curl_setopt($ch, CURLOPT_USERPWD, $harbor_prd['userid2'].':'.$harbor_prd['pw2']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

		$response = curl_exec($ch);

		$r = json_decode($response);

		if ($debug) {
			mail($debug_email, '3.TERMINAL REFUND PRD RESPONSE', print_r($r, true));
		}

		$success = ($r->response->UPDATED == 'Y') ? true : false;

		if ($success) {

			$csr_id = $current_user->ID;
			$csr_name = $current_user->first_name.' '.$current_user->last_name;

			$new_values = array(
				'RESULT'					=> '1',
				'RESPMSG'					=> 'success',
				'prd_transaction_id'		=> $r->response->TRANSACTION_ID,
				'correlation_id'			=> $refund_id,
				'prd_customer_id'			=> $order['prd_customer_id'],
				'prd_json_response'			=> $response,
				'refund'					=> $amount,
				'payment_type'				=> 'REFUND',
				'purchase_order'			=> 'CSR ID: '.$csr_id.' / '.$csr_name,
				'order_summary'				=> 'Terminal Refund for $'.$amount.' placed by '.$csr_name,
				'product_name'				=> 'TERMINAL REFUND',
				'think_orderhdr_id'			=> $order['think_id'],
			);

			$this->_insert_order($new_values);

			$now = date('M d, Y');
			$balance = $order['payment'] - $amount;

			$sql = "UPDATE wp_Harbor_orders SET payment = ".$balance.", order_summary = CONCAT(order_summary, '\r\nRefunded $".$amount." on ".$now.".\r\nOrder payment total has been adjusted.') WHERE (id = ".$refund_id.");";
			$wpdb->query($sql);

			$this->_transactionID = $r->response->TRANSACTION_ID;

			return true;

		} else {

			$prd_error = get_prd_error($r->error[0]->errno, 'sp');

			$this->_errors[] = $r->error[0]->errno.': <b>'.$prd_error.'</b>';

			return false;

		}
	}

	private function _insert_order($new_values) {

		$defaults = array(
			'price'							=> 0,
			'payment'						=> 0,
			'paypal_fee'					=> 0,
			'refund'						=> 0,
			'payment_type'					=> '',
			'card_expire'					=> '',
			'purchase_order'				=> '',
			'payment_status'				=> '',
			'pending_reason'				=> '',
			'reason_code'					=> '',
			'product_name'					=> '',
			'file_name'						=> '',
			'offer_code'					=> '',
			'premiums'						=> '',
			'term'							=> '',
			'donor_id'						=> 0,
			'user_id'						=> 0,
			'first_name'					=> '',
			'last_name'						=> '',
			'address'						=> '',
			'address2'						=> '',
			'city'							=> '',
			'state'							=> '',
			'zip_code'						=> '',
			'country'						=> '',
			'phone'							=> '',
			'user_email'					=> '',
			'title'							=> '',
			'company'						=> '',
			'user_ip'						=> '',
			'RESULT'						=> '',
			'RESPMSG'						=> '',
			'HOSTCODE'						=> '',
			'RESPTEXT'						=> '',
			'PNREF'							=> '',
			'BAID'							=> '',
			'AUTHCODE'						=> '',
			'AVSADDR'						=> 'X',
			'AVSZIP'						=> 'X',
			'IAVS'							=> 'X',
			'CVV2MATCH'						=> 'X',
			'DUPLICATE'						=> '',
			'RB_RESULT'						=> '',
			'PROFILEID'						=> '',
			'RB_RESPMSG'					=> '',
			'RPREF'							=> '',
			'cancel_rpref'					=> '',
			'ack'							=> '',
			'profile_id'					=> '',
			'transaction_id'				=> '',
			'avs_code'						=> '',
			'cvv2_match'					=> '',
			'correlation_id'				=> '',
			'authorizenet_response_reason_text'	=> '',
			'authorizenet_account_number'	=> '',
			'arb_response_code'				=> '',
			'arb_response_text'				=> '',
			'order_summary'					=> '',
			'track'							=> '',
			'order_type'					=> 'n',
			'cancelled'						=> 'n',
			'rb_cancelled'					=> 'n',
			'comments'						=> '',
			'authorizenet_xml_check'		=> '',
			'payflow_pro_response'			=> '',
			'payflow_pro_rb_response'		=> '',
			'chargebee_json'				=> '',
			'chargebee_id'					=> '',
			'chargebee_status'				=> '',
			'chargebee_current_term_end'	=> 0,
			'renewal_date'					=> 0,
			'think_orderhdr_id'				=> 0,
			'think_data'					=> '',
			'think_subscrip_id'				=> 0,
		);

		$wp_Harbor_orders = array_merge($defaults, $new_values);

		$keys = implode(", ", array_keys($wp_Harbor_orders));
		$vals = "'".implode("', '", $wp_Harbor_orders)."'";

		$sql = "INSERT INTO wp_Harbor_orders (".$keys.") VALUES (".$vals.")";

		global $wpdb;
		$wpdb->query($sql);

		return true;
	}

	/**
	 * given a one dimensional array:
	 *	-sanitize the data
	 *	-remove escape characters
	 *	-hand back the raw values as an array
	 * @param $data
	 * @return array
	*/
	private function _sanitize_data_set($data){

		//sanitize our data
		$args = array( 'flags' => FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW );
		foreach($data as $key => $d){
			$data[$key] = filter_var( $d, FILTER_SANITIZE_STRING, $args );
		}

		//slashes prevent proper URLencoding
		$data = str_replace('\\', '', $data);

		return $data;
	}

// ------------------------------------------------------------------------
// TERMINAL PAGE - HISTORY

	public function customer_history($customer_id) {

		$r = $this->gatekeeper($customer_id);

		$msg = '';

		if ($_POST['raw'] == 'raw') {
			$msg .= '<pre>'.print_r($r, true).'</pre>';
			$this->_raw = true;
		} else {
			$this->_raw = false;
		}

		$success = ($r->response->CUST_FOUND == 'Y') ? true : false;

		if ($success) {

			$this->_prd_customer_number = $r->response->CUSTOMER_NUMBER;
			$this->_prd_userid_email = $r->response->CUSTOMER_INFO->EMAIL;

			// new customer information display

			$c = $r->response->CUSTOMER_INFO;
			$name = ($c->FIRST || $c->LAST) ? '<b>'.implode(' ', array_filter(array($c->TITLE, $c->FIRST, $c->MI, $c->LAST, $c->SUFFIX))).'</b><br/>' : false;
			$title = ($c->PROFESSIONAL_TITLE) ? '<i>'.$c->PROFESSIONAL_TITLE.'</i><br/>' : false;
			$company = ($c->BUSINESS_NAME) ? '<b>'.$c->BUSINESS_NAME.'</b><br/>' : false;
			$address = ($c->ADD1 || $c->ADD2 || $c->ADD3 || $c->ADD4) ? implode(' ', array_filter(array($c->ADD1, $c->ADD2, $c->ADD3, $c->ADD4))).'<br/>' : false;
			$state = ($c->ST) ? $c->ST : $c->STATE_NAME;
			$city = (!$c->CITY && $c->ALTCITY) ? $c->ALTCITY : $c->CITY;
			$region = ($city || $state) ? implode(', ', array_filter(array($city, $state))).'<br/>' : false;
			$postal = ($c->ZIP) ? $c->ZIP.'<br/>' : false;
			$country = ($c->COUNTRY && $c->COUNTRY != 'USA') ? $c->COUNTRY.'<br/>' : false;
			$email = ($c->EMAIL) ? '<a href="'.$c->EMAIL.'">'.$c->EMAIL.'</a><br/>' : false;
			$phone = ($c->PHONE) ? $c->PHONE.'<br/>' : false;
			$prd_customer_number = ($c->CUSTOMER_NUMBER) ? 'PRD ID: '.$c->CUSTOMER_NUMBER.'<br/>' : false;

			$msg .= '<table>';
			$msg .= '<tr class="header"><td class="left" colspan="2">&nbsp;&nbsp;PRD Customer Information</td></tr>';
			$msg .= '<tr class="data"><td class="left" width=25%>'.$name.$email.$prd_customer_number.'</td>';
			$msg .= '<td class="left" width=75%>'.$title.$company.$address.$region.$postal.$country.$phone.'</td></tr>';
			$msg .= '</table>';

			if ($r->response->SPECIAL_PROGRAMS || $r->response->ORDER_HISTORY) {
				$msg .= '<table class="prd_terminal_output">';
			}

			if ($r->response->SPECIAL_PROGRAMS) {

				$msg .= '<tr class="header"><td class="left">&nbsp;&nbsp;PRD Special Programs</td><td>Gift?</td><td>Recurrence</td><td>Prev Charge</td><td>Next Charge</td></tr>';
				foreach($r->response->SPECIAL_PROGRAMS as $s) {
					$gift = '';
					if ($s->IS_GIFT == 'Y') { $gift = 'Recipient'; }
					if ($s->IS_DONOR == 'Y') { $gift = 'Giver'; }
					if ($s->SP_CODE == 'TRMNL' || $s->SP_CODE == 'SHOPP') {
						//$msg .= '<tr>';
						//$msg .= '<td class="left">&nbsp;&nbsp;'.$s->SP_CODE.': <b>'.$s->SP_DESC.'</b></td>';
						//$msg .= '<td>'.$gift.'</td>';
						//$msg .= '<td></td>';
						//$msg .= '<td></td>';
						//$msg .= '<td></td>';
						//$msg .= '</tr>';
					} else {
						global $wpdb;
						if ($s->PERIOD != 'O') {
							switch ($s->FREQUENCY.$s->PERIOD) {
								case '1Y' : $recur = 'Yearly'; break;
								case '2Y' : $recur = '2 Years'; break;
								case '3Y' : $recur = '3 Years'; break;
								case '20Y' : $recur = '20 Years'; break;
								case '1M' : $recur = 'Monthly'; break;
								case '2M' : $recur = 'Bi-Monthly'; break;
								case '3M' : $recur = 'Quarterly'; break;
								case '1S' : $recur = 'Twice Monthly'; break;
								case '1W' : $recur = 'Weekly'; break;
								case '2W' : $recur = 'Bi-Weekly'; break;
								case '1D' : $recur = 'Daily'; break;
							}
							// this condition addressed a specific issue on CSN, and will be deleted 
							// in the next release now that more than a year has passed
							if (strtotime($s->JOIN_DATE) < strtotime('2016-03-10')) {
								$renewal_date = $s->RECURRING_DATE;
								$prd_auto_renew = $s->FREQUENCY.$s->PERIOD;
								$prd_sp_ref_id = $s->LAST_TRANS_REFID;
								if ($renewal_date && $prd_auto_renew && $prd_sp_ref_id) {
									$wpdb->query($wpdb->prepare("UPDATE wp_Harbor_orders SET renewal_date = %s, prd_auto_renew = %s WHERE (prd_sp_ref_id = %s) AND (prd_auto_renew IS NULL);", $renewal_date, $prd_auto_renew, $prd_sp_ref_id));
								}
							}
						} else {
							// this condition addressed a specific issue on CSN, and will be deleted 
							// in the next release now that more than a year has passed
							if (strtotime($s->JOIN_DATE) < strtotime('2016-03-10')) {
								$prd_sp_ref_id = $s->LAST_TRANS_REFID;
								if ($prd_sp_ref_id) {
									$wpdb->query($wpdb->prepare("UPDATE wp_Harbor_orders SET prd_auto_renew = 'NO' WHERE (prd_sp_ref_id = %s) AND (prd_auto_renew IS NULL);", $prd_sp_ref_id));
								}
							}
							$recur = 'None';
						}
						switch ($s->ACCESS_LEVEL) {
							case 'COMBO':
							case 'ALL-ACCESS':
							case 'C': $channel = ' (Combo)'; break;
							case 'DIGITAL':
							case 'DIG-COMBO':
							case 'D': $channel = ' (Digital Combo)'; break;
							case 'PRINT':
							case 'P': $channel = ' (Print Only)'; break;
							case 'TABLET':
							case 'TABLE':
							case 'T': $channel = ' (Tablet Only)'; break;
							case 'ALL':
							case 'WEB':
							case 'W': $channel = ' (Web Only)'; break;
							default: $channel = false;
						}

						$status = $this->_status($s->STATUS_FLAG, strtolower($s->CATEGORY));
						if ( $status['entitle'] ) {
							$next_charge = '$'.$s->RECURRING_AMT.'<br/>'.$s->RECURRING_DATE;
						} else {
							$next_charge = ($s->STATUS_DESC) ? $s->STATUS_DESC : $status[0];
						}
						$msg .= '<tr class="data">';
						$msg .= '<td class="left">&nbsp;&nbsp;SP Code (Secondary SP Code): '.$s->SP_CODE.' ('.$s->SECONDARY_SP_CODE.')<br/>&nbsp;&nbsp;<b>'.$s->SP_DESC.' - '.$s->SECONDARY_SP_DESC.'</b>'.$channel.'</td>';
						$msg .= '<td>'.$gift.'</td>';
						$msg .= '<td>'.$recur.'</td>';
						$msg .= '<td>$'.$s->LAST_TRANS_AMT.'<br/>'.$s->LAST_TRANS_DATE.'</td>';
						$msg .= '<td>'.$next_charge.'</td>';
						$msg .= '</tr>';
					}
				}
			}

			if ($r->response->ORDER_HISTORY) {
				$msg .= '<tr class="header"><td class="left">&nbsp;&nbsp;PRD Subscription / Continuity / Catalog</td><td>Gift?</td><td>Recurring?</td><td>Status</td><td>Dates</td></tr>';
				foreach($r->response->ORDER_HISTORY as $s) {
					$gift = ($s->IS_GIFT == 'Y') ? 'Recipient' : '';
					if ($s->IS_DONOR == 'Y') { $gift = 'Giver'; }
					$recur = ($s->AUTO_CHARGE == 'Y') ? 'Y' : '' ;
					$status = $this->_status($s->STATUS_FLAG, $s->CATEGORY);
					$fallback_description = ($s->OFFER_DESCRIPTION) ? $s->OFFER_DESCRIPTION : $s->DESCRIPTION;
					$product_description = $this->_product_name($s->PROD_CODE, $s->REFID, $fallback_description);
					$purchase_date = date('Y-m-d', strtotime($s->DATE));

					// $expires = date('Y-m-d', strtotime($s->DATE) + ($s->WEEKS_SHIP_INTERVAL*(7*24*3600))); // work for CONTINUITY?

					switch ($s->ACCESS_LEVEL) {
						case 'C': $channel = ' (Combo)'; break;
						case 'D': $channel = ' (Digital Combo)'; break;
						case 'P': $channel = ' (Print Only)'; break;
						case 'T': $channel = ' (Tablet Only)'; break;
						case 'W': $channel = ' (Web Only)'; break;
						default: $channel = false;
					}

					if ($s->CATEGORY == 'Publication') {
						$msg .= '<tr>';
						$msg .= '<td class="left">&nbsp;&nbsp;<span title="KEY_CODE">'.$s->KEY_CODE.'</span> | <span title="PROD_CODE">'.$s->PROD_CODE.'</span><br/>&nbsp;&nbsp;PRD Subscription:&nbsp; <b>'.$product_description.'</b>'.$channel.'</td>';
						$msg .= '<td>'.$gift.'</td>';
						$msg .= '<td>'.$recur.'</td>';
						$msg .= '<td>'.$status['desc'].'</td>';
						$msg .= '<td><small>Expires</small><br/>'.$s->ACCESS_THRU.'</td>';
						$msg .= '</tr>';
					} else if ($s->CATEGORY == 'Continuity') {
						$msg .= '<tr>';
						$msg .= '<td class="left">&nbsp;&nbsp;<span title="KEY_CODE">'.$s->KEY_CODE.'</span> | <span title="PROD_CODE">'.$s->PROD_CODE.'</span> : <span title="OFFER_PRODUCT_0">'.$s->OFFER_PRODUCTS[0].'</span><br/>&nbsp;&nbsp;PRD Continuity:&nbsp; <b>'.$product_description.'</b>'.$channel.'</td>';
						$msg .= '<td>'.$gift.'</td>';
						$msg .= '<td>'.$recur.'</td>';
						$msg .= '<td>'.$status['desc'].'<br/>'.$status['cn_only'].'</td>';
						$msg .= '<td><small>Order Date</small><br/>' . $purchase_date . '</td>';
						$msg .= '</tr>';
					} else if ($s->CATEGORY == 'Catalog') {
						$msg .= '<tr>';
						$msg .= '<td class="left">&nbsp;&nbsp;<span title="KEY_CODE">'.$s->KEY_CODE.'</span> | <span title="PROD_CODE">'.$s->PROD_CODE.'</span><br/>&nbsp;&nbsp;PRD Catalog Product:&nbsp; <b>'.$product_description.'</b>'.$channel.'</td>';
						$msg .= '<td>'.$gift.'</td>';
						$msg .= '<td>'.$recur.'</td>';
						$msg .= '<td>'.$status['desc'].'</td>';
						$msg .= '<td><small>Expires</small><br/>'.$s->ACCESS_THRU.'</td>';
						$msg .= '</tr>';
					} else {
						$msg .= '<tr>';
						$msg .= '<td class="left">&nbsp;&nbsp;<span title="KEY_CODE">'.$s->KEY_CODE.'</span> | <span title="PROD_CODE">'.$s->PROD_CODE.'</span> : <span title="SECONDARY_SP_CODE">'.$s->SECONDARY_SP_CODE.'</span><br/>&nbsp;&nbsp;Other PRD Products:&nbsp; <b>'.$product_description.'</b>'.$channel.'</td>';
						$msg .= '<td>'.$gift.'</td>';
						$msg .= '<td>'.$recur.'</td>';
						$msg .= '<td>'.$status['desc'].'</td>';
						$msg .= '<td><small>Expires</small><br/>'.$s->ACCESS_THRU.'</td>';
						$msg .= '</tr>';
					}
				}
			}

			if ($r->response->SPECIAL_PROGRAMS || $r->response->ORDER_HISTORY) {
				$msg .= '</table><hr/>';
			} else {
				$msg .= "<pre>No order history found. This could mean the user has not been found, or the customer has no current recurring subscriptions in PRD's system.</pre><br/><br/>";
			}

			$this->_message = $msg;
			return true;

		} else {

			if ($r->error[0]->errno) {
				$prd_error = get_prd_error($r->error[0]->errno, 'gk');
				if ($prd_error) {
					$this->_errors[] = $r->error[0]->errno.': <b>'.$prd_error.'</b>';
				} else {
					$this->_errors[] = '<b>PRD error number '.$r->error[0]->errno.' could not be defined.</b>';
				}
			} else {
				$this->_errors[] = '<b>No PRD error code returned.</b>';
			}

			if ($this->_raw) {
				$this->_message = $msg;
			}

			return false;

		}
	}

	private function _status($status, $category) {

		$status = str_split(preg_replace("/[^A-Za-z0-9]/", "", $status));
		$category = strtolower($category);

		$values = array(
			'publication' => array(
				'B'	=> 'Bad Debt',
				'E'	=> 'Expired',
				'O'	=> 'Open A/R Active',
				'P'	=> 'Paid Active',
				'S'	=> 'Credit Suspend',
				'I'	=> 'Inactive',
			),
			'continuity' => array(
				'P'	=> 'Paid Active',
				'B'	=> 'Bad Debt',
				'C'	=> 'Canceled',
				'Z'	=> 'Complete',
			),
			'catalog' => array(
				'O'	=> 'On Order',
				'P'	=> 'In Process',
				'S'	=> 'Shipped',
				'B'	=> 'Back Ordered',
			),
			'status_code' => array(
				'A'	=> 'Offer Declined',
				'0'	=> 'New Member, Not Fulfilled',
				'1'	=> 'Next Offer Card Sent',
				'2'	=> 'Next Offer Declined',
				'3'	=> 'Fulfilled',
				'4'	=> 'Returned',
				'5'	=> 'Bad Debt',
				'6'	=> 'Offer Sent, Not Fulfilled',
				'7'	=> 'Canceled',
				'8'	=> 'Lost',
				'9'	=> 'Paid',
			)
		);

		$entitle = array(
			'publication'	=> array( 'O','P','S','I' ),
			'continuity'	=> array( 'P', 'C', 'Z' ),
			'catalog'		=> array( 'O', 'P', 'S' ),
			'status_code'	=> array( '0', '3', '9' ),
		);

		$output = array();

		$output['desc'] = $values[$category][$status[0]];

		$output['cn_only'] = ( isset($status[1]) ) ? $values['status_code'][$status[1]] : false ;

		$output['entitle'] = false;

		if ( $category == 'continuity' ) {
			if ( in_array( $status[0], $entitle['continuity'] ) ) {
				if ( in_array( $status[1], $entitle['status_code'] ) ) {
					$output['entitle'] = true;
				}
			}
		} else {
			if ( is_array( $entitle[$category] ) ) {
				if ( in_array($status[0], $entitle[$category] ) ) {
					$output['entitle'] = true;
				}
			}
		}

		return $output;
	}

	private function _product_name($prod_code, $refid, $fallback) {
		global $wpdb;

		$refid = ($refid > 0) ? str_pad($refid, 3, '0', STR_PAD_LEFT) : '';
		$prd_prod_code = $prod_code.$refid;

		$output = $wpdb->get_var($wpdb->prepare("SELECT t.name FROM wp_terms t JOIN wp_termmeta m ON t.term_id = m.term_id AND m.meta_key = 'pub_id' WHERE (meta_value = %s);", $prd_prod_code));

		return ($output) ? $output : $fallback;
	}

// ------------------------------------------------------------------------
// GATEKEEPER API FUNCTIONS

	public function gatekeeper($customer_id, $user_id = false) {

		if ( ! $this->is_prd_awake() ) { return false; }

		$harbor_prd = get_option('harbor-prd');
		if (!$harbor_prd) {
			$this->_errors[] = 'The credit card processor has not been configured.';
			return false;
		}

		$debug = $debug_email = false;
		if (!empty($harbor_prd['debug'])) {
			$debug = true;
			$debug_email = $harbor_prd['debug_email'];
		}

		if ($debug) {
			mail($debug_email, '1.GATEKEEPER INITIAL VALUE', $customer_id);
		}

		if (empty($customer_id)) {
			$this->_errors[] = 'Please submit a valid PRD customer ID.';
			return false;
		}

		$headers = array();

		$post = array(
			'org'					=> $harbor_prd['org'],
			'program_type_id'		=> 'MS',
			'test_mode'				=> $harbor_prd['test_mode'],
			'app_version'			=> $harbor_prd['app_version'],
			'skip_user_search'		=> 'N',
			'across_the_universe'	=> 'Y',
			'load_sp'				=> 'Y',
			'load_customer'			=> 'Y',
			'load_history_su'		=> 'Y',
			'load_history_cn'		=> 'Y',
			'load_history_ca'		=> 'Y',
			'days_history_su'		=> '',
			'days_history_cn'		=> '',
			'days_history_ca'		=> '',
			'search_by_custno'		=> 'Y',
			'load_access_level'		=> 'Y',
			'load_key_code'			=> 'Y',
			'load_e_allow'			=> 'Y',
			'load_entitlements_su'	=> 'N',
			'load_offer_prod'		=> 'Y',
			'load_auto_renew_cc_su'	=> 'Y',
		);

		if (is_numeric($customer_id)) {
			$post['customer_number'] = $customer_id;
		} else {
			$post['userid'] = $customer_id;
		}

		if ($debug) {
			mail($debug_email, '2.GATEKEEPER PRD POST', print_r($post, true));
		}

		$post = http_build_query($post);

		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		$test = ($harbor_prd['test_mode'] == 'Y') ? 'test/' : '';
		$url = $harbor_prd['base_url'].'/rest/gate_keeper/'.$harbor_prd['org'].'/v'.$harbor_prd['app_version'].'/'.$test.'json/';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_VERBOSE,true);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false );
		curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_SSLVERSION, '6');

		curl_setopt($ch, CURLOPT_USERPWD, $harbor_prd['userid3'].':'.$harbor_prd['pw3']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

		$response = curl_exec($ch);

		$r = json_decode($response);

		if ($debug) {
			mail($debug_email, '3.GATEKEEPER PRD RESPONSE', print_r($r, true));
		}

		if ( $harbor_prd['pull_userdata'] ) { $this->_pull_userdata($r, $user_id); }

		return $r;
	}

	private function _pull_userdata( $gatekeeper_object, $user_id = false ) {

		$harbor_prd = get_option('harbor-prd');

		if ( !$harbor_prd['pull_userdata'] ) { return false; }

		if (!$gatekeeper_object) { return false; }
		if (!$gatekeeper_object->response->CUST_FOUND == 'Y') { return false; }
		if (!is_object($gatekeeper_object->response->CUSTOMER_INFO)) { return false; }

		$debug = $debug_email = false;
		if (!empty($harbor_prd['debug'])) {
			$debug = true;
			$debug_email = $harbor_prd['debug_email'];
		}

		$prd_cust = $gatekeeper_object->response->CUSTOMER_INFO;

		if ( !$user_id ) {
			global $wpdb;
			$sql = $wpdb->prepare("SELECT user_id FROM wp_usermeta WHERE (meta_key = '') AND (meta_value = %s);", $prd->CUSTOMER_NUMBER);
			$user_id = $wpdb->get_var($sql);
		}

		if ( !$user_id ) { return false; }

		$harbor_meta = get_user_meta($user_id);

		// $vars: PRD object name => HAVEN user_meta name

		$vars = array(
			'FIRST'					=> 'first_name',
			'MI'					=> 'middle_initial',
			'LAST'					=> 'last_name',
			'PROFESSIONAL_TITLE'	=> 'title',
			'BUSINESS_NAME'			=> 'business_name',
			'ADD1'					=> 'address',
			'ADD2'					=> 'address2',
			'ADD3'					=> 'address3',
			'CITY'					=> 'city',
			'ALTCITY'				=> 'alt_city',
			'ST'					=> 'state',
			'STATE_NAME'			=> 'alt_state',
			'ZIP'					=> 'zip_code',
			'COUNTRY'				=> 'country',
			'PHONE'					=> 'phone',
			'TITLE'					=> 'alt_title',
			'SUFFIX'				=> 'suffix',
			'CUSTOMER_NUMBER'		=> 'prd_customer_id',
			'OPTIN'					=> 'prd_optin',
		);

		$report = array();

		foreach ( $vars as $prd_var => $harbor_var ) {
			if ( $prd_cust->$prd_var && $prd_cust->$prd_var != $harbor_meta[$harbor_var] ) {
				update_user_meta($user_id, $harbor_var, $prd_cust->$prd_var);
				$report[$harbor_var] = $prd_cust->$prd_var;
			}
		}

		if ($debug) {
			if ( empty($report) ) { $report[] = 'Nothing updated.'; }
			mail($debug_email, 'GATEKEEPER _pull_userdata() DEBUG', print_r($report, true));
		}

		return true;
	}

// ------------------------------------------------------------------------
// MANAGE SPECIAL PROGRAMS API FUNCTIONS

	public function manage_prd($action = false, $order_id = 0, $amount = 0, $date = false) {

		if ( ! $this->is_prd_awake() ) { return false; }

		global $wpdb;
		$current_user = wp_get_current_user();

		$harbor_prd = get_option('harbor-prd');
		if (!$harbor_prd) {
			$this->_errors[] = 'The credit card processor has not been configured.';
			return false;
		}

		$debug = $debug_email = false;
		if (!empty($harbor_prd['debug'])) {
			$debug = true;
			$debug_email = $harbor_prd['debug_email'];
		}

		if ($debug) {
			mail($debug_email, '1.MANAGE SP - INITIAL VALUES', "ACTION: ".$action."\r\nORDER ID: ".$order_id."\r\nREFUND AMOUNT: ".$amount."\r\nRECURRING DATE: ".$date);
		}

		switch ($action) {
			case 'cancel_and_refund':
			case 'refund':
				if (empty($amount)) {
					$this->_errors[] = 'Expected a non-zero refund amount.';
					return false;
				}
				break;
			case 'set_recurring_fields':
				if (!$date) {
					$this->_errors[] = 'Expected a new recurring date.';
					return false;
				}
				break;
		}

		$headers = array();

		$amount	= ($amount) ? number_format(preg_replace('/[\$,]/', '', $amount), 2, '.', '') : 0;
		$date = ($date) ? date('m/d/Y', strtotime($date)) : false;

		$sql = "SELECT product_id, offer_code, payment, prd_customer_id, prd_transaction_id, prd_reference_id, prd_sp_ref_id, think_orderhdr_id FROM wp_Harbor_orders WHERE (id = ".$order_id.")";
		$order = $wpdb->get_row($sql, ARRAY_A);

		if (!$order) {
			$this->_errors[] = 'The transaction referenced in the refund order could not be found in the Harbor database.';
			return false;
		}

		$sql = "SELECT id, payment_type, payment, prd_sp_ref_id FROM wp_Harbor_orders WHERE (correlation_id = ".$order_id.") AND (payment_type = 'AUTO') ORDER BY order_time DESC LIMIT 1;";

		$auto_renew_order = $wpdb->get_row($sql, ARRAY_A);

		if ( $auto_renew_order ) {
			$order['prd_sp_ref_id'] = strtoupper($auto_renew_order['prd_sp_ref_id']);
			$order['latest_payment'] = $auto_renew_order['payment'];
			$order['latest_renewal_id'] = $auto_renew_order['id'];
		}

		$prd_product_id = get_field('prd_product_id', $order['product_id'], false);
		if (!$prd_product_id) { $prd_product_id = 'TRMNL'; }

		if ($amount > $order['payment']) {
			$this->_errors[] = 'The refund amount must not exceed the original payment amount, or any remaining balance after subsequent refunds.';
			return false;
		}

		if ($date && strtotime($date) < (time()+(24*60*60))) {
			$this->_errors[] = 'The new recurring charge date may not be within the next 24 hours.';
			return false;
		}

		$post_common = array(
			'org'				=> $harbor_prd['org'],
			'program_type_id'	=> 'SP',
			'program_id'		=> $prd_product_id,
			'test_mode'			=> $harbor_prd['test_mode'],
			'app_version'		=> $harbor_prd['app_version'],
			'customer_number'	=> $order['prd_customer_id'],
		);

		switch ($action) {
			case 'cancel_program':
				$post_action = array(
					'cancel_program'		=> 'Y',
				);
				break;

			case 'cancel_and_refund':
				$post_action = array(
					'cancel_and_refund'		=> 'Y',
					'refund_amt'			=> $amount,
					'sp_ref_id'				=> $order['prd_sp_ref_id'],
				);
				break;

			case 'refund':
				$post_action = array(
					'refund'				=> 'Y',
					'refund_amt'			=> $amount,
					'sp_ref_id'				=> $order['prd_sp_ref_id'],
				);
				break;

			case 'set_recurring_fields':

				$sql = "SELECT period, freq, amt, currency FROM wp_Harbor_offers WHERE (id = ".$order['offer_code'].");";
				$offer = $wpdb->get_row($sql, ARRAY_A);

				$prd_recurring_amt	= number_format(preg_replace('/[\$,]/', '', $offer['amt']), 2, '.', '');
				$prd_frequency = $offer['freq'];
				switch ($offer['period']) {
					case 'Year':		$prd_period = 'Y'; break;
					case 'Month':		$prd_period = 'M'; break;
					case 'Semi-Month':	$prd_period = 'S'; break;
					case 'Week':		$prd_period = 'W'; break;
					case 'Day':			$prd_period = 'D'; break;
				}

				$post_action = array(
					'set_recurring_fields'	=> 'Y',
					'recurring_amt'			=> $prd_recurring_amt,
					'recurring_date'		=> $date,
					'period'				=> $prd_period,
					'frequency'				=> $prd_frequency,
				);
				break;

			default:
				$this->_errors[] = 'An action was not specified for the PRD Payments plugin.';
				return false;
		}

		$post = array_merge($post_common, $post_action);

		if ($debug) {
			mail($debug_email, '2.MANAGE SP - PRD POST', print_r($post, true));
		}

		$post = http_build_query($post);

		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		$test = ($harbor_prd['test_mode'] == 'Y') ? 'test/' : '';
		$url = $harbor_prd['base_url'].'/rest/manage_sp/'.$harbor_prd['org'].'/v'.$harbor_prd['app_version'].'/'.$test.'json/';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_VERBOSE,true);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false );
		curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_SSLVERSION, '6');

		curl_setopt($ch, CURLOPT_USERPWD, $harbor_prd['userid2'].':'.$harbor_prd['pw2']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

		$response = curl_exec($ch);

		$r = json_decode($response);

		if ($debug) {
			mail($debug_email, '3.MANAGE SP - PRD RESPONSE', print_r($r, true));
		}

		$success = ($r->response->UPDATED == 'Y') ? true : false;

		if ($success) {

			$csr_id = $current_user->ID;
			$csr_name = $current_user->first_name.' '.$current_user->last_name;
			$now = date('M d, Y');

			if ($action == 'cancel_and_refund' || $action == 'refund') {

				$new_values = array(
					'RESULT'					=> '1',
					'RESPMSG'					=> 'success',
					'prd_transaction_id'		=> $r->response->TRANSACTION_ID,
					'correlation_id'			=> $order_id,
					'prd_customer_id'			=> $order['prd_customer_id'],
					'prd_json_response'			=> $response,
					'refund'					=> $amount,
					'payment_type'				=> 'REFUND',
					'purchase_order'			=> 'CSR ID: '.$csr_id.' / '.$csr_name,
					'order_summary'				=> 'Refund for $'.$amount.' placed by '.$csr_name,
					'product_name'				=> 'REFUND',
					'think_orderhdr_id'			=> $order['think_id'],
				);

				$this->_insert_order($new_values);

				$now = date('M d, Y');
				$balance = ( array_key_exists('latest_payment', $order) ) ? $order['latest_payment'] - $amount : $order['payment'] - $amount;

				if ( array_key_exists('latest_renewal_id', $order) ) {
					$sql = "UPDATE wp_Harbor_orders SET payment = ".$balance.", order_summary = CONCAT(order_summary, '\r\nRefunded $".$amount." on ".$now.".\r\nOrder payment total has been adjusted.'), prd_auto_renew = NULL WHERE (id = ".$order['latest_renewal_id'].");";
					$wpdb->query($sql);
					$sql = "UPDATE wp_Harbor_orders SET order_summary = CONCAT(order_summary, '\r\nRefunded $".$amount." on ".$now.". Refund applied to latest auto-renewal balance.'), prd_auto_renew = NULL WHERE (id = ".$order_id.");";
					$wpdb->query($sql);
				} else  {
					$sql = "UPDATE wp_Harbor_orders SET payment = ".$balance.", order_summary = CONCAT(order_summary, '\r\nRefunded $".$amount." on ".$now.".\r\nOrder payment total has been adjusted.'), prd_auto_renew = NULL WHERE (id = ".$order_id.");";
					$wpdb->query($sql);
				}

			} else if ($action == 'cancel_program') {

				$sql = "UPDATE wp_Harbor_orders SET order_summary = CONCAT(order_summary, '\r\n\r\nRecurring charge was cancelled on ".$now." by ".$csr_name.".'), prd_auto_renew = 'CANCELED' WHERE (id = ".$order_id.");";
				$wpdb->query($sql);

			} else if ($action == 'set_recurring_fields') {

				$sql = "UPDATE wp_Harbor_orders SET order_summary = CONCAT(order_summary, '\r\n\r\nRecurring expire date was adjusted on ".$now." by ".$csr_name.".\r\nNext charge will occur on ".$date.", one month prior to expiration.\r\nSubsequent renewals will occur at the frequency set in the product offer.'), prd_json_response = CONCAT('[', prd_json_response, ',".$response."]') WHERE (id = ".$order_id.");";
				$wpdb->query($sql);

			}

			$this->_transactionID = $r->response->TRANSACTION_ID;

			return true;

		} else {

			$prd_error = get_prd_error($r->error[0]->errno, 'sp');

			$this->_errors[] = $r->error[0]->errno.': <b>'.$prd_error.'</b>';

			mail($debug_email, '[PRD] manage_prd() REFUND FAILURE', "ACTION: ".$action."\r\nORDER ID: ".$order_id."\r\nREFUND AMOUNT: ".$amount."\r\nRECURRING DATE: ".$date."\r\nPRD ERROR: ".$prd_error."\r\n\r\nORDER DATA: ".print_r($order, 1)."\r\n\r\nPRD POST: ".print_r($post, 1)."\r\n\r\nPRD RESPONSE: ".print_r($r, 1));

			return false;

		}
	}

	public function record_auto_renewal($args) {

		global $wpdb;

		$sql = $wpdb->prepare("SELECT o.id, o.product_id, o.payment_type, o.user_id, o.track, o.offer_code, o.prd_transaction_id, f.period, f.freq, f.publication AS pub_id, m.meta_value AS 'access_control_name'
		FROM wp_Harbor_orders o
			LEFT JOIN wp_Harbor_offers f ON o.offer_code = f.id
			LEFT JOIN wp_postmeta m ON o.product_id = m.post_id AND m.meta_key = 'access_control_name'
		WHERE (o.prd_sp_ref_id = %s)
			AND NOT (o.payment_type = 'AUTO')
			AND NOT (order_type = 'r');", $args['original_sp_ref_id']);

		$order = $wpdb->get_row($sql, ARRAY_A);

		$new_values = array(
			'RESULT'					=> '1',
			'RESPMSG'					=> 'success',
			'prd_transaction_id'		=> $order['prd_transaction_id'],
			'correlation_id'			=> $order['id'],
			'product_id'				=> $order['product_id'],
			'user_id'					=> $order['user_id'],
			'track'						=> $order['track'],
			'offer_code'				=> $order['offer_code'],
			'payment_type'				=> 'AUTO',//$order['payment_type'],
			'prd_customer_id'			=> $args['prd_customer_id'],
			'prd_reference_id'			=> $args['original_sp_ref_id'],
			'prd_sp_ref_id'				=> $args['prd_sp_ref_id'],
			'prd_auto_renew'			=> '1',
			'renewal_date'				=> $args['prd_next_charge_date'],
			'prd_json_response'			=> json_encode($args),
			'price'						=> $args['prd_transaction_amount'],
			'payment'					=> $args['prd_transaction_amount'],
			'order_summary'				=> 'Auto Renewed: $'.$args['prd_transaction_amount'].' on card ending in '.$args['prd_cc_last'].', next charge on '.$args['prd_next_charge_date'],
			'product_name'				=> $args['prd_product'],
			'order_type'				=> 'r',
		);

		$order_id = $this->_insert_order($new_values);

		if ($order_id) {

			$order_summary = '<br/>Renewed on '.date('Y-m-d').' for $'.$args['prd_transaction_amount'].' using card ending in '.$args['prd_cc_last'].'.';

			$sql = $wpdb->prepare("UPDATE wp_Harbor_orders SET renewal_date = %s, order_summary = CONCAT(order_summary, %s) WHERE (id = %d);", $args['prd_next_charge_date'], $order_summary, $order['id']);
			$wpdb->query($sql);

			// EXPIRATION DATE
				// use offer freq and period to determine additional time to add to order

					$length = 24*60*60;

					$dim = date('t');

					switch ($order['period']) {
						case 'Year': $length *= 365; break;
						case 'Month': $length *= $dim; break;
						case 'Semi-Month': $length *= 14; break;
						case 'Week': $length *= 7; break;
					};

					$length *= intval($order['freq']);

				// check all channels, updating only so if exists will be updated

					$channels = array('web', 'print', 'tablet');

				// remove channels if they do not exist in access_control_name

					if (!empty($order['access_control_name'])) {
						foreach($channels as $key => $c) {
							if (strpos($order['access_control_name'], $c) === false) {
								unset($channels[$key]);
							}
						}
					}

				// update each channel's meta value if it exists

					foreach($channels as $c) {
						$meta_key = 'expire_date_'.$order['pub_id'].'_'.$c;
						$expire = get_user_meta($order['user_id'], $meta_key, true);
						if (!empty($expire)) {
							$expire = strtotime($expire) + $length;
							update_user_meta($order['user_id'], $meta_key, date('Ymd', $expire));
						}
					}

			$think_args = array(
				'user_id'			=> $order['user_id'],
				'itemId'			=> $order_id,
				'original_order_id'	=> $order['id'],
				'payment'			=> $args['prd_transaction_amount'],
			);

			do_action('Harbor-ordered-renewal', $think_args);

			$harborsc = get_user_meta($order['user_id'], 'harborsc', true);
			$trans_args = array(
				'type'			=> 'activate-auto-renew',
				'type_desc'		=> 'Add Auto-Renew',
				'itemId'		=> $order['id'],
				'user_id'		=> $order['user_id'],
				'asid'			=> $harborsc,
			);

			do_action( 'Harbor-transaction', $trans_args );

			return true;

		} else { // ERROR CONDITION

			$err_message = "ARGS:\r\n".print_r($args,1)."\r\n\r\n";
			$err_message .= "SQL:\r\n".$sql."\r\n\r\n";
			$err_message .= "NEW VALUES:\r\n".print_r($new_values,1)."\r\n\r\n";
			$err_message .= "ORDER ID:\r\n".print_r($order_id,1)."\r\n\r\n";

			mail('mwndll@gmail.com', '[PRD] Auto-Renewal Error', $err_message);

			return false;

		}
	}

// ------------------------------------------------------------------------
// CUSTOMER UPDATE API FUNCTIONS

	public function customer_update( $user_id ) {

		if ( ! $this->is_prd_awake() ) { return false; }

		$harbor_prd = get_option('harbor-prd');

		if ( !$harbor_prd['push_userdata'] ) { return false; }

		$debug = $debug_email = false;
		if (!empty($harbor_prd['debug'])) {
			$debug = true;
			$debug_email = $harbor_prd['debug_email'];
		}

		$user = get_userdata( $user_id );
		$meta = get_user_meta( $user_id );

		// required variables
		$prd_customer_id = $meta['prd_customer_id'][0];
		$last_name = $meta['last_name'][0];
		$city = substr($meta['city'][0],0,25);
		$state = strtoupper(substr($meta['state'][0],0,2));
		$zip_code = strtoupper(substr($meta['zip_code'][0],0,10));
		$country = strtoupper($meta['country'][0]);

		$prd_country_array = prd_country_array();
		if ( array_key_exists($country, $prd_country_array) ) { $country = substr($prd_country_array[$country],0,30); }
		if ( $country == 'US' || $country == 'UNITED STATES' ) { $country = 'USA'; }

		if ( !is_numeric($prd_customer_id) ) { //return false;
		}
		if ( empty($last_name) || empty($city) || empty($state) || empty($country) ) { //return false;
		}

		$headers = array();

		$post = array(
			'org'				=> $harbor_prd['org'],
			'program_type_id'	=> 'MS',
			'test_mode'			=> $harbor_prd['test_mode'],
			'app_version'		=> $harbor_prd['app_version'],
			'customer_number'	=> $prd_customer_id,
			'last'				=> $last_name,
			'city'				=> $city,
			'st'				=> $state,
			'zip'				=> $zip_code,
			'country'			=> $country,
		);

		// optional variables
		if ( !empty($meta['first_name'][0]) ) { $post['first'] = substr($meta['first_name'][0],0,15); }
		if ( !empty($meta['address'][0]) ) { $post['add1'] = substr($meta['address'][0],0,40); }
		if ( !empty($meta['address2'][0]) ) { $post['add2'] = substr($meta['address2'][0],0,40); }
		if ( !empty($meta['phone'][0]) ) { $post['phone'] = substr($meta['phone'][0],0,15); }

		$post_query = http_build_query($post);

		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		$test = ( $harbor_prd['test_mode'] == 'Y' ) ? 'test/' : '';
		$url = $harbor_prd['base_url'].'/rest/customer_update/'.$harbor_prd['org'].'/v'.$harbor_prd['app_version'].'/'.$test.'json/';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_query);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_VERBOSE,true);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false );
		curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_SSLVERSION, '6');

		curl_setopt($ch, CURLOPT_USERPWD, $harbor_prd['userid4'].':'.$harbor_prd['pw4']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

		$response = curl_exec($ch);

		$r = json_decode($response);

		if ($debug) {
			if ( empty($report) ) { $report[] = 'Nothing updated.'; }
			mail($debug_email, 'PRD _customer_update() REPORT', "POST: ".print_r($post, true)."\r\n\r\nURL: ".$url."\r\n\r\nRESPONSE: ".print_r($r, true));
		}

		return true;

	}

	private function _push_userdata( $user_id ) {

		$harbor_prd = get_option('harbor-prd');

		if ( !$harbor_prd['push_userdata'] ) { return false; }

	}

// ------------------------------------------------------------------------
// ENTITLEMENTS

	public function get_entitlements($user_id, $include_auto_renew = false) {

		global $wpdb;

		$products = $default_entitlements = array();
		foreach ( $this->_settings['products'] as $p ) {
			$secondary_product_id = ($p['secondary_product_id']) ? $p['secondary_product_id'] : 0;
			if ( strpos($secondary_product_id, ',') !== false ) {
				$spids = explode(',', $secondary_product_id);
				foreach ( $spids as $spid ) {
					$products[$p['service']][$p['product_id']][$spid] = $p['pub_id'];
					if ( $p['service'] != 'sp' && $p['service'] != 'su' && isset($p['entitle']) ) {
						$default_entitlements[$p['service']][$p['product_id']][$spid] = $p['entitle'];
					}
				}
			} else {
				$products[$p['service']][$p['product_id']][$secondary_product_id] = $p['pub_id'];
				if ( $p['service'] != 'sp' && $p['service'] != 'su' && isset($p['entitle']) ) {
					$default_entitlements[$p['service']][$p['product_id']][$secondary_product_id] = $p['entitle'];
				}
			}
			
		}

		$customer_id = get_user_meta($user_id, 'prd_customer_id', true);

		if (empty($customer_id)) {
			$user = get_userdata($user_id);
			$user_email = $user->user_email;
			if (!$user_email) {
				return false;
			}
			$customer_id = $user_email;
		}

		$entitlements = array();
		$auto_renew = array();

		$r = $this->gatekeeper($customer_id, $user_id);

		$debug = $debug_email = false;
		if (!empty($this->_settings['debug'])) {
			$debug = true;
			$debug_email = $this->_settings['debug_email'];
		}

		$url = $_SERVER['REQUEST_URI'];

		if (!$r) {
			if ($debug) {
				mail($debug_email, '0.ENTITLEMENTS REQUEST ERROR', "CUSTOMER:\r\n".$customer_id);
			}
			return false;
		}

		if ($debug) {
			mail($debug_email, '1.ENTITLEMENTS INITIAL VALUES FROM GATEKEEPER', "CUSTOMER:\r\n".$customer_id."\r\nURL:\r\n".$url."\r\nRESPONSE:\r\n".print_r($r, true)."\r\nSETTINGS:\r\n".print_r($this->_settings,1)."\r\nPRODUCTS:\r\n".print_r($products,1)."\r\nDEFAULT ENTITLEMENTS:\r\n".print_r($default_entitlements,1));
		}

		$success = ($r->response->CUST_FOUND == 'Y') ? true : false;

		if ($success) {

			if ($customer_id == $user_email) {
				update_user_meta($user_id, 'prd_customer_id', $r->response->CUSTOMER_NUMBER);
			}

			$now = time();

			if ($r->response->ORDER_HISTORY) {

				foreach($r->response->ORDER_HISTORY as $key => $s) {

					if ($s->IS_DONOR != 'Y') { 

						$pub_id = false;

						$status = $this->_status($s->STATUS_FLAG, $s->CATEGORY);

						if ( $status['entitle'] ) {

							switch($s->CATEGORY) {
								case 'Catalog':
									$category_code = 'ca'; break;
								case 'Continuity':
									$category_code = 'cn'; break;
								case 'Publication':
								default:
									$category_code = 'su';
							}

							$channel = 'WEB';

							$default_entitlement = false;

							if ( is_array($products[$category_code]) && $s->PROD_CODE ) {
								if (array_key_exists($s->PROD_CODE, $products[$category_code])) {
									if ( $s->CATEGORY == 'Continuity' || $s->CATEGORY == 'Catalog' ) {
										$offer_product = ( is_array($s->OFFER_PRODUCTS) && !empty($s->OFFER_PRODUCTS) ) ? $s->OFFER_PRODUCTS[0] : false;
										if ( $offer_product ) {
											$secondary_code = (array_key_exists($offer_product, $products[$category_code][$s->PROD_CODE])) ? $offer_product : 0;
											$pub_id = $products[$category_code][$s->PROD_CODE][$secondary_code];
											$default_entitlement = $default_entitlements[$category_code][$s->PROD_CODE][$secondary_code];

											// UHN makes heavy use of continuities, but continuities don't have access levels.
											// However, PRD has a pattern in their OFFER_PRODUCT codes that indicates channel.
											if ( strpos(get_site_url(), 'universityhealthnews') !== false ) {
												$ch = substr($offer_product, 0, 2);
												switch ( $ch ) {
													case 'SR':
													case 'P9':
													case 'U9':
													case 'M9': $channel = 'PRINT'; break;
													case 'CR':
													case 'C9': $channel = 'COMBO'; break;
													case 'ER':
													case 'D9':
													default:   $channel = 'WEB';
												}
											}

										} else {
											mail( 'mwndll@gmail.com', 'UHN ORDER w/o OFFER_PRODUCTS ARRAY', print_r( $r, 1 ) );
										}
									} else {
										$pub_id = $products[$category_code][$s->PROD_CODE][0];
										$default_entitlement = $default_entitlements[$category_code][$s->PROD_CODE][0];
									}
								}
							}

							if ($pub_id) {

								$expires = false;

								$perpetuity_years = (is_int($this->_settings['perpetuity'])) ? intval($this->_settings['perpetuity']) : 20;
								$perpetuity_epoch = strtotime('+'.$perpetuity_years.' years');

								if ($s->CATEGORY == 'Publication') {
									if (!empty($s->ACCESS_THRU)) {
										$expires = strtotime($s->ACCESS_THRU) + 86399;
									} else if (!empty($s->EXPIRE_DATE)) {
										$expires = strtotime($s->EXPIRE_DATE) + 86399;
									}
								} else if ($s->CATEGORY == 'Continuity' || $s->CATEGORY == 'Catalog') {
									$purchase_date = strtotime($s->DATE);
									if ( $default_entitlement ) {
										switch ( $default_entitlement ) {
											case 'M': $expires = $purchase_date + ( 30 * 24 * 60 * 60 ); break;
											case 'Y': $expires = $purchase_date + ( 365 * 24 * 60 * 60 ); break;
											case 'P': $expires = $perpetuity_epoch; break;
										}
									} else {
										$expires = $purchase_date + ( 365 * 24 * 60 * 60 );
									}
								} else {
									$expires = strtotime('+1 year');
								}

								$channel = ($s->ACCESS_LEVEL) ? $s->ACCESS_LEVEL : $channel;

								if ($expires) {
									switch ($channel) {
										case 'COMBO':
										case 'ALL-ACCESS':
										case 'CB':
										case 'C':
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'print', $expires );
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'web', $expires );
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'tablet', $expires );
											//$entitlements[$pub_id]['print'] = $expires;
											//$entitlements[$pub_id]['web'] = $expires;
											//$entitlements[$pub_id]['tablet'] = $expires;
											break;
										case 'DIGITAL':
										case 'DIG-COMBO':
										case 'D':
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'web', $expires );
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'tablet', $expires );
											break;
										case 'PRINT':
										case 'P':
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'print', $expires );
											break;
										case 'TABLET':
										case 'TABLE':
										case 'T':
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'tablet', $expires );
											break;
										case 'WEB':
										case 'ALL':
										case 'W':
										default:
											$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'web', $expires );
											break;
									}

									if ($s->AUTO_CHARGE == 'Y') {
										$auto_renew[] = $pub_id;
									}
								}

							} // if $pub_id

						}
					} // if IS_DONOR
				} // foreach ORDER_HISTORY
			} // if ORDER_HISTORY

			if ($r->response->SPECIAL_PROGRAMS) {

				foreach($r->response->SPECIAL_PROGRAMS as $key => $s) {
					if ($s->IS_DONOR != 'Y') { 

						$pub_id = false;

						if (array_key_exists($s->SP_CODE, $products['sp'])) {
							$secondary_sp_code = (array_key_exists($s->SECONDARY_SP_CODE, $products['sp'][$s->SP_CODE])) ? $s->SECONDARY_SP_CODE : 0;
							$pub_id = $products['sp'][$s->SP_CODE][$secondary_sp_code];
						}

						if ($pub_id) { 

							if ($s->STATUS_FLAG == 'A') {

								$expires = strtotime($s->ACCESS_EXP_DATE) + 86399;

								// to entitle single issues of a publication, SECONDARY_SP_CODE must
								// follow format XX###, where XX is valid pub_id and ### is an integer
								if (preg_match( '/^'.$pub_id.'[0-9]*$/', $s->SECONDARY_SP_CODE)) {
									$issue_number = intval(preg_replace('/^\D*/', '', $s->SECONDARY_SP_CODE));
									$pub_id .= '-'.$issue_number;
								}

								switch ($s->SECONDARY_SP_CODE) {
									case 'COMBO':
									case 'ALL-ACCESS':
									case 'CB':
									case 'C':
										//$entitlements[$pub_id]['print'] = $expires;
										//$entitlements[$pub_id]['web'] = $expires;
										//$entitlements[$pub_id]['tablet'] = $expires;
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'print', $expires );
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'web', $expires );
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'tablet', $expires );
										break;
									case 'DIGITAL':
									case 'DIG-COMBO':
									case 'D':
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'web', $expires );
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'tablet', $expires );
										break;
									case 'PRINT':
									case 'P':
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'print', $expires );
										break;
									case 'TABLET':
									case 'TABLE':
									case 'T':
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'tablet', $expires );
										break;
									case 'WEB':
									case 'W':
									case 'ALL':
									default:
										$entitlements = $this->_insert_newest_entitlement( $entitlements, $pub_id, 'web', $expires );
										break;
								}

								if ( $s->RECURRING_STATUS == 'A' && $s->PENDING_CANCEL != 'Y' ) {
									$auto_renew[] = $pub_id;
								}

							} // if STATUS_FLAG

						} // if $pub_id

					} // if IS_DONOR
				} // foreach SPECIAL_PROGRAMS
			} // if SPECIAL_PROGRAMS

		} else {

			if ($debug) {
				mail($debug_email, '2.ENTITLEMENTS COULD NOT FIND USER', "URL:\r\n".$url."\r\n\r\nCUSTOMER:\r\n".$customer_id."\r\n\r\nSETTINGS:\r\n".print_r($this->_settings,1));
			}

			return false;

		}

		if ($debug) {
			mail($debug_email, '2.ENTITLEMENTS FINAL RETURN VALUE', "CUSTOMER:\r\n".$customer_id."\r\n\r\nENTITLEMENTS:\r\n".print_r($entitlements, true));
		}

		if ( !empty($auto_renew) ) {
			$auto_renew = implode(',',$auto_renew);
			update_user_meta( $user_id, 'prd_auto_renew', $auto_renew );
		}

		return $entitlements;
	}

	private function _insert_newest_entitlement( $entitlements = array(), $pub_id = false, $channel = false, $expires = false ) {
		if ( $pub_id && $channel && $expires ) {
			$old_expires = ( isset( $entitlements[$pub_id][$channel] ) ) ? $entitlements[$pub_id][$channel] : 0;
			if ( $expires > $old_expires ) {
				$entitlements[$pub_id][$channel] = $expires;
			}
		}
		return $entitlements;
	}

} //end class

// Instantiate our class
$harborPRD = harborPRD::getInstance();


// ================================================================================================

// look up PRD error explanations
require('prd-error-codes.php');

// look up PRD states & countries
require('prd-geo-codes.php');
