<?php
/**
 * Plugin Name: Harbor Entitlements
 * Plugin URI: http://www.kwyjibo.com
 * Description: Subscription entitlement management plugin
 * Version: 0.2.18
 * License: GPL
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

// ------------------------------------------------------------------------
// ACTIVATION AND SETUP

	add_action( 'admin_menu', 'harborHEM_add_admin_menu' );
	add_action( 'admin_init', 'harborHEM_settings_init' );
	add_action( 'wp_login', 'load_entitlements' );

	register_activation_hook( __FILE__, 'harborHEM_activate');

	function harborHEM_activate() {

		global $wpdb;

		$wp_ = ( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

		dbDelta("CREATE TABLE {$wp_}harbor_entitlements (
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

		dbDelta("CREATE TABLE {$wp_}harbor_entitlements_refreshed (
			user_id int(11) NOT NULL,
			refreshed int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY  (user_id),
			UNIQUE KEY user_id (user_id)
			);");
	}

	function harborHEM_add_admin_menu() {
		add_options_page(
			'Harbor Entitlements',
			'Harbor Entitlements',
			'manage_options',
			'harbor_entitlements',
			'harborHEM_options_page'
		);
	}

	function harborHEM_settings_init() {
		register_setting( 'pluginPage', 'harborHEM_settings', 'harborHEM_sanitize_options');

		add_settings_section(
			'bypass_section',
			__( 'Bypass Entitlement Checking', 'wordpress' ),
			'bypass_section_callback',
			'pluginPage'
		);

		add_settings_field(
			'ent_bypass',
			__( 'Entitlement Bypass', 'wordpress' ),
			'render_ent_bypass_radio',
			'pluginPage',
			'bypass_section'
		);

		add_settings_field(
			'cha_bypass',
			__( 'Challenge Bypass', 'wordpress' ),
			'render_cha_bypass_radio',
			'pluginPage',
			'bypass_section'
		);

		add_settings_field(
			'comp_bypass',
			__( 'Comp Bypass', 'wordpress' ),
			'render_comp_bypass_text',
			'pluginPage',
			'bypass_section'
		);

		add_settings_section(
			'external_section',
			__( 'Use an External Entitlement Database', 'wordpress' ),
			'external_section_callback',
			'pluginPage'
		);

		add_settings_field(
			'use_prd_gatekeeper',
			__( 'PRD Gatekeeper', 'wordpress' ),
			'render_external_checkbox_prd',
			'pluginPage',
			'external_section'
		);

		add_settings_field(
			'use_xpd_fission',
			__( 'XPD Fission', 'wordpress' ),
			'render_external_checkbox_xpd',
			'pluginPage',
			'external_section'
		);

		add_settings_field(
			'use_cxd_gateway',
			__( 'CXD Web Service Gateway', 'wordpress' ),
			'render_external_checkbox_cxd',
			'pluginPage',
			'external_section'
		);

		add_settings_field(
			'expire_external_entitlements',
			__( 'Renewal Frequency', 'wordpress' ),
			'render_expire_select',
			'pluginPage',
			'external_section'
		);

		add_settings_section(
			'products_section',
			__( 'Configure Composite Products', 'wordpress' ),
			'products_section_callback',
			'pluginPage'
		);

		add_settings_field(
			'new_composite_product',
			__( 'Create Composite Product', 'wordpress' ),
			'render_new_product_text',
			'pluginPage',
			'products_section'
		);
	}

	function harborHEM_sanitize_options($options) {
			if (is_numeric($options['comp_bypass_new'])) {
				$options['comp_bypass'][] = intval($options['comp_bypass_new']);
			} elseif ( preg_match( '/^(\d+(,)*(\s)*)+$/', $options['comp_bypass_new'] ) ) {
				$bypass_array = explode( ',', preg_replace( '/\s+/', '', $options['comp_bypass_new'] ) );
				foreach ( $bypass_array as $uid ) { $options['comp_bypass'][] = intval( $uid ); }
			} else {
				global $wpdb;
				$sql = $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}users WHERE (user_email = %s);", $options['comp_bypass_new']);
				$user_id = $wpdb->get_var($sql);
				if ($user_id) { $options['comp_bypass'][] = intval($user_id); }
			}
			unset($options['comp_bypass_new']);
			if ($options['create_product']) {
				if (!$options['products']) { $options['products'] = array(); }
				$options['products'][$options['create_product']]['title'] = $options['create_product_title'];
				unset($options['create_product']);
			}
			return $options;
	}

	function render_ent_bypass_radio() {
		$options = get_option( 'harborHEM_settings' );
		?>
		<label><input type='radio' name='harborHEM_settings[ent_bypass]' <?php checked($options['ent_bypass'], 'all'); ?> value='all'> All users subscribed to everything</label><br/>
		<label><input type='radio' name='harborHEM_settings[ent_bypass]' <?php checked($options['ent_bypass'], 'login'); ?> value='login'> Logged in users subscribed to everything</label><br/>
		<label><input type='radio' name='harborHEM_settings[ent_bypass]' <?php checked($options['ent_bypass'], 'admin'); ?> value='admin'> Administrative users subscribed to everything</label><br/>
		<label><input type='radio' name='harborHEM_settings[ent_bypass]' <?php checked($options['ent_bypass'], 'none'); ?> value='none'> <b>Normal Entitlement Operation</b></label><br/>
		<?php
	}

	function render_cha_bypass_radio() {
		$options = get_option( 'harborHEM_settings' );
		?>
		<label><input type='radio' name='harborHEM_settings[cha_bypass]' <?php checked($options['cha_bypass'], 'all'); ?> value='all'> All users can access all protected content</label><br/>
		<label><input type='radio' name='harborHEM_settings[cha_bypass]' <?php checked($options['cha_bypass'], 'login'); ?> value='login'> Logged in users can access all protected content</label><br/>
		<label><input type='radio' name='harborHEM_settings[cha_bypass]' <?php checked($options['cha_bypass'], 'admin'); ?> value='admin'> Administrative users can access all protected content</label><br/>
		<label><input type='radio' name='harborHEM_settings[cha_bypass]' <?php checked($options['cha_bypass'], 'none'); ?> value='none'> <b>Normal Challenge Operation</b></label><br/>
		<?php
	}

	function render_comp_bypass_text() {
		$options = get_option( 'harborHEM_settings' );
		global $wpdb;
		echo "<input type='text' name='harborHEM_settings[comp_bypass_new]' value='' placeholder='User ID or Email Address'><br/>";
		if (isset($options['comp_bypass'])) {
			foreach ($options['comp_bypass'] as $c) {
				$email = $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->prefix}users WHERE (ID = %d);", $c));
				if ($email) {
					echo "<label><input type='checkbox' name='harborHEM_settings[comp_bypass][]' value='{$c}' checked>{$email} ({$c})</label><br/>";
				}
			}
		}
	}

	function render_external_checkbox_prd() {
		$options = get_option( 'harborHEM_settings' );
		?>
		<input type='checkbox' name='harborHEM_settings[use_prd_gatekeeper]' <?php checked( $options['use_prd_gatekeeper'], 1 ); ?> value='1'><br/>
		<?php
	}

	function render_external_checkbox_xpd() {
		$options = get_option( 'harborHEM_settings' );
		?>
		<input type='checkbox' name='harborHEM_settings[use_xpd_fission]' <?php checked( $options['use_xpd_fission'], 1 ); ?> value='1'><br/>
		<?php
	}

	function render_external_checkbox_cxd() {
		$options = get_option( 'harborHEM_settings' );
		?>
		<input type='checkbox' name='harborHEM_settings[use_cxd_gateway]' <?php checked( $options['use_cxd_gateway'], 1 ); ?> value='1'><br/>
		<?php
	}

	function render_expire_select() {
		$days = array(0,7,1,2,3,4,5,6,7,14,21,30);
		$options = get_option( 'harborHEM_settings' );
		?>
		<label><select name='harborHEM_settings[expire_external_entitlements]'>
			<?php
				foreach ($days as $d) {
					$secs = $d*86400;
					echo "<option value='".$secs."'";
					selected($options['expire_external_entitlements'], $secs);
					echo ">".$d."</option>";
				}
			?>
		</select> Days</label><br/>
		<?php
	}

	function external_section_callback() {
		echo __( 'If applicable, select an external source for entitlement data.', 'wordpress' );
	}

	function bypass_section_callback() {
		echo __( 'Grant specified users extended access to content. For comps, enter a valid user_id or email address.', 'wordpress' );
	}

	function products_section_callback() {
		echo __( 'Composite Products exist to allow a single club or membership purchase to entitle multiple publications.', 'wordpress' );
	}

	function render_new_product_text() {
		$options = get_option( 'harborHEM_settings' );
		$pubs = get_pubs();
		$channels = get_channels();
		//echo "<pre>".print_r($options['products'],1)."</pre>";
		echo "<style>
		.composite_product_table td { padding: 2px 5px; text-align: center; }
		.composite_product_table th { padding: 2px 2px 2px 32px; }
		</style>";
		echo "<input type='text' name='harborHEM_settings[create_product]' value='' placeholder='Product ID'>&nbsp;<input type='text' name='harborHEM_settings[create_product_title]' value='' placeholder='Product Name'><br/>";
		if (isset($options['products'])) {
			foreach ($options['products'] as $id => $data) {
				echo "<br/><label><input type='checkbox' name='harborHEM_settings[products][{$id}][title]' value='{$data['title']}' checked><b>{$id}:</b> {$data['title']}</label><br/>";
				echo "<table cellspacing=0 cellpadding=0 border=0 class='composite_product_table'>";
				echo "<tr><th></th>";
				foreach ($channels as $c) { echo '<td>'.$c.'</td>'; }
				echo "</tr>";
				foreach ($pubs as $p) {
					if ($p['active']) {
						echo "<tr><th><b>{$p[title]}</b></th>";
						foreach ($channels as $channel_code => $channel) {
							echo "<td><input type='checkbox' name='harborHEM_settings[products][{$id}][pubs][{$p[pub_id]}][]' value='{$channel}'";
							if (is_array($options['products'][$id]['pubs'][$p['pub_id']])) {
								if (in_array($channel, $options['products'][$id]['pubs'][$p['pub_id']])) { echo ' checked'; }
							}
							echo"></td>";
						}
						echo "</tr>";
					}
				}
				echo "</table>";
			}
		}
	}

	function harborHEM_options_page() {
		$plugin_data = get_plugin_data(__FILE__, 0, 0);
		$force_results = false;
		if ($_POST['force_update'] == 'force' && !empty($_POST['force_email'])) {
			$users = explode(',', $_POST['force_email']);
			$force_results = array();
			foreach ($users as $u) {
				$u = trim($u);
				if (!is_numeric($u)) {
					$user = get_user_by('email', $u);
					if ($user) { $u = $user->ID; }
				}
				if (is_numeric($u)) {
					$force_results[$u] = load_entitlements($u, true);
				}
			}
		}
		?>
		<div class="wrap">
			<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>
			<form action='options.php' method='post'>
				<?php
				settings_fields( 'pluginPage' );
				do_settings_sections( 'pluginPage' );
				submit_button();
				?>
			</form>
			<hr>
			<form method='post'>
			<h3>Force Update User Entitlements</h3>
			<p><label>Enter User Email<br/>
			<input type="text" name="force_email"></label></p>
			<p><input type="submit" value="Update" class="button"></p>
			<input type="hidden" name="force_update" value="force">
			<?php
				if (is_array($force_results)) {
					if (!empty($force_results)) {
						global $wpdb;
						foreach($force_results as $id => $entitlements) {
							$email = $wpdb->get_var("SELECT user_email FROM wp_users WHERE (ID = ".$id.");");
							echo '<p><b>'.$email.'</b> (id: '.$id.')<br/>';
							if (!empty($entitlements)) {
								foreach ($entitlements as $pub_id => $channels) {
									echo '<b>'.$pub_id.'</b><br/>';
									foreach($channels as $channel => $expires) {
										echo '&nbsp; '.$channel.': '.date('F d, Y', $expires).'<br/>';
									}
								}
								echo '</p>';
							} else {
								echo '&nbsp; No entitlements updated.</p>';
							}
						}
					}
				}
			?>
			</form>
			</hr>
		</div>
		<?php
	}

// ------------------------------------------------------------------------
// EXTERNAL ENTITLEMENT FUNCTIONS

	/*
	 * Is a user subscribed to a publication?
	 *
	 * @param string $pub_id  Required. Whatever value the entitlements function is saving as the array key.
	 * @param string $channel Optional. 'web', 'print', 'tablet'
	 *
	 * @return true if subscribed, false if not subscribed
	*/
	function subscribed($pub_id, $channel = 'web', $issue_id = 0) {

		_stop_cache();

		if (!$pub_id) { return false; }

		$options = get_option('harborHEM_settings');

		if ($options['cha_bypass'] == 'all') { return true; }

		if (!is_user_logged_in()) { return false; }

		if ($options['cha_bypass'] == 'login') { return true; }

		if ($options['cha_bypass'] == 'admin' && current_user_can( 'manage_options' )) { return true; }

		$user_id = get_current_user_id();

		if ( isset( $options['comp_bypass'] ) && is_array($options['comp_bypass']) ) {
			$comps = $options['comp_bypass'];
			if ( in_array($user_id, $comps) ) {
				return true;
			}
		}

		$entitlements = load_entitlements();

		if ( isset( $entitlements[$pub_id][$channel] ) && is_array( $entitlements ) ) {

			if ($entitlements[$pub_id][$channel] > time()) { return true; }

			$max_age = ($options['expire_external_entitlements']) ? $options['expire_external_entitlements'] : 0;

			if ($entitlements[$pub_id][$channel] > (time() - $max_age)) {

				$entitlements = load_entitlements($user_id, true);

				if ($entitlements[$pub_id][$channel] > time()) { return true; }

			}

		}

		return false;
	}

	/**
	 * Retrieve all of a user's entitlement information, update externally if necessary
	 *
	 * @param number $user_id Required. Wordpress wp_users->ID
	 * @param bool $force Optional. Will force check of external entitlements if active
	 * @param bool $parent_only Optional. Return will not include children of composite products
	 *
	 * @return array|bool
	 */
	function load_entitlements($user_id = false, $force = false, $parents_only = false) {

		_stop_cache();

		$options = get_option( 'harborHEM_settings' );

		if ( $options['ent_bypass'] == 'all' ) { return _get_fake_entitlements(); }

		$user_id = ( is_numeric( $user_id ) ) ? $user_id : get_current_user_id();

		if ( !is_numeric($user_id) || $user_id == 0 ) { return false; }

		if ( $options['ent_bypass'] == 'login' ) { return _get_fake_entitlements(); }

		if ( $options['ent_bypass'] == 'admin' && user_can($user_id, 'manage_options' ) ) { return _get_fake_entitlements(); }

		if ( isset( $options['comp_bypass'] ) && is_array( $options['comp_bypass'] ) ) {
			$comps = $options['comp_bypass'];
			if ( in_array( $user_id, $comps ) ) {
				return _get_fake_entitlements();
			}
		}

		$entitlements = _get_entitlement_array($user_id, $parents_only);

		if ( isset( $options['use_prd_gatekeeper'] ) || isset( $options['use_xpd_fusion'] ) || isset( $options['use_cxd_gateway'] ) ) {

			$max_age = ( $options['expire_external_entitlements'] ) ? $options['expire_external_entitlements'] : 0;
			$refresh = _get_refresh_time( $user_id ) + $max_age;

			if ( $refresh < time() || $force ) {

				if ( isset( $options['use_prd_gatekeeper'] ) ) { $prd_entitlements = _get_prd_entitlements( $user_id ); }
				if ( isset( $options['use_xpd_fusion'] ) ) { $xpd_entitlements = _get_xpd_entitlements( $user_id ); }
				if ( isset( $options['use_cxd_gateway'] ) ) { $cxd_entitlements = _get_cxd_entitlements( $user_id ); }

				$entitlements = _get_entitlement_array($user_id, $parents_only);

				// UPDATE ENTITLEMENTS at WHATCOUNTS
				$wce = ( $parents_only ) ? _get_entitlement_array( $user_id ) : $entitlements;
				do_action('harbor_entitlements_renewed', array('user_id' => $user_id, 'entitlements' => $wce));

			}

		}

		return $entitlements;
	}

	/**
	 * insert_entitlement into user's entitlement usermeta
	 *
	 * used to set entitlements for a user. used to add new entitlement or update existing
	 * pass in a length/interval to add to current time or a specific date to set
	 *
	 * @param $user_id - Required. user id of account the entitlement being added
	 * @param $pub_id - Required. publication/product they need access to.
	 * @param $channel - Required. channel from offer settings (web, print, tablet)
	 * @param $length - unix timestamp of the interval or length. will be added to current time to create expire date
	 * @param $date - optional. instead of $length you can pass a specific expire date to set
	 * @param $issue_id - optional. integer id of specific, entitled issue of a publication
	 * @param $parent_id - optional. id of entitlement row of parent, if composite product
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	function insert_entitlement($user_id, $pub_id, $channel, $length, $date = false, $issue_id = 0, $parent_id = 0) {

		_stop_cache();

		if (!is_numeric($user_id) || !$pub_id) { return false; }

		$expires = ($date) ? $date : $length + time() ;

		delete_entitlement($user_id, $pub_id, $channel, $issue_id);

		global $wpdb;

		$values = array(
			'user_id'	=> $user_id,
			'pub_id'	=> $pub_id,
			'channel'	=> $channel,
			'expires'	=> $expires,
			'issue_id'	=> $issue_id,
			'parent_id'	=> $parent_id,
		);
		$formats = array('%d', '%s', '%s', '%d', '%d', '%d');
		$result = $wpdb->insert($wpdb->prefix.'harbor_entitlements', $values, $formats);
		$last_insert = $wpdb->insert_id;

		$composite_products = get_composite_products();

		if (is_array($composite_products)) {
			if (array_key_exists($pub_id, $composite_products)) {
				$child_products = $composite_products[$pub_id]['pubs'];
				if (is_array($child_products)) {
					foreach ($child_products as $cp_id => $channels) {
						foreach ($channels as $channel) {
							insert_entitlement($user_id, $cp_id, $channel, $length, $date, $issue_id, $last_insert);
						}
					}
				}
			}
		}

		if (!$result) { return false; }

		return true;
	}

	function delete_entitlement($user_id = false, $pub_id = false, $channel = false, $issue_id = false) {

		_stop_cache();

		global $wpdb;

		if (!is_numeric($user_id) || !$pub_id) { return false; }

		$sql = $wpdb->prepare("SELECT c.id AS child_id, p.id AS parent_id FROM {$wpdb->prefix}harbor_entitlements p LEFT JOIN {$wpdb->prefix}harbor_entitlements c ON p.id = c.parent_id WHERE (p.user_id = %d) AND (p.pub_id = %s)", $user_id, $pub_id);
		if ($channel) { $sql .= $wpdb->prepare(' AND (p.channel = %s)', $channel); }
		if ($issue_id) { $sql .= $wpdb->prepare(' AND (p.issue_id = %d)', $issue_id); }
		$results = $wpdb->get_results($sql, ARRAY_A);

		$ids = array();
		foreach($results as $r) {
			if (!empty($r['parent_id'])) {
				$ids[] = $r['parent_id'];
			}
			if (!empty($r['child_id'])) {
				$ids[] = $r['child_id'];
			}
		}
		
		if (!empty($ids)) {
			$sql = "DELETE FROM {$wpdb->prefix}harbor_entitlements WHERE (id IN (".implode(',', $ids)."));";
			return $wpdb->query($sql);
		} else {
			return true;
		}
	}

	/**
	 * find all the users that have a valid entitlement for the given publication pub id.
	 *
	 * @param $pub_id - publication/subscription/product looking for
	 * @param $channel string, optional - web, print, tablet
	 * @param $issue_id int, optional - specific issue of publication
	 *
	 * @return array|bool - array of users or false if no users found
	 */
	function get_entitled( $pub_id, $channel = false, $issue_id = false, $now = false ){

		global $wpdb;

		if ( ! $pub_id ) { return false; }

		$now = ( is_int( $now ) ) ? $now : time();

		$sql = $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}harbor_entitlements WHERE (expires > %d) AND (pub_id = %s)", $now, $pub_id );
		if ( $channel ) { $sql .= $wpdb->prepare( ' AND (channel = %s)', $channel ); }
		if ( $issue_id ) { $sql .= $wpdb->prepare( ' AND (issue_id = %d)', $issue_id ); }

		$users = $wpdb->get_results($sql, ARRAY_A);

		return $users;
	}

	function get_simple_entitlements($user_id) {

		$entitlements = load_entitlements($user_id);

		$options = get_option('harborHEM_settings');
		$max_age = ($options['expire_external_entitlements']) ? $options['expire_external_entitlements'] : 0;

		$list = array();

		foreach ($entitlements as $pub_id => $channels) {
			foreach ($channels as $channel => $expire) {
				if (($expire + $max_age) > (time())) {
					$list[] = $pub_id;
				}
			}
		}

		return $list;
	}

	/**
	 * return composite products.
	 *
	 * @return array|bool - array of composte products or false if no users found
	 */
	function get_composite_products() {

		$options = get_option('harborHEM_settings');

		//$products = $options['products'];

		$products = false;
		if( isset( $options['products'] ) ){
			$products = $options['products'];
		}

		if (!$products) { return false; }

		return $products;
	}

// ------------------------------------------------------------------------
// PRIVATE INTERNAL ENTITLEMENT FUNCTIONS

	function _get_entitlement_array($user_id, $parents_only = false) {
		_stop_cache();
		global $wpdb;
		if (!is_numeric($user_id)) { return false; }
		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}harbor_entitlements WHERE (user_id = %d) ORDER BY parent_id, pub_id, channel, issue_id;", $user_id);
		$results = $wpdb->get_results($sql, ARRAY_A);
		if (!is_array($results)) { return false; }
		$entitlements = array();

		foreach ($results as $r) {
			if ( !$parents_only || $r['parent_id'] == 0 ) {
				if ( ! isset( $entitlements[$r['pub_id']][$r['channel']] ) || $entitlements[$r['pub_id']][$r['channel']] < $r['expires'] ) {
					$entitlements[$r['pub_id']][$r['channel']] = $r['expires'];
				}
			}
		}
		return $entitlements;
	}

	function _get_refresh_time($user_id) {
		_stop_cache();
		global $wpdb;
		if (!is_numeric($user_id)) { return false; }
		$sql = $wpdb->prepare("SELECT refreshed FROM {$wpdb->prefix}harbor_entitlements_refreshed WHERE (user_id = %d);", $user_id);
		$refreshed = $wpdb->get_var($sql);
		if (empty($refreshed)) { return 0; }
		return $refreshed;
	}

	function _set_refresh_time($user_id) {
		_stop_cache();
		global $wpdb;
		if (!is_numeric($user_id)) { return false; }
		$refreshed = time();
		$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}harbor_entitlements_refreshed (user_id, refreshed) VALUES (%d, %d)
			ON DUPLICATE KEY UPDATE refreshed = %d;", $user_id, $refreshed, $refreshed);
		$result = $wpdb->query($sql);
		if (!$result) { return false; }
		return true;
	}

	function _reset_entitlements($user_id, $entitlements) {
		_stop_cache();
		global $wpdb;
		if (!is_numeric($user_id) || !is_array($entitlements)) { return false; }

		$sql = $wpdb->prepare("DELETE FROM {$wpdb->prefix}harbor_entitlements WHERE (user_id = %d);", $user_id);
		$wpdb->query($sql);

		if ( empty($entitlements) ) { return true; }

		$reverse_entitlements = array();
		foreach ($entitlements as $pub_id => $channels) {
			foreach ($channels as $channel => $expires) {
				$reverse_entitlements[$expires][$pub_id][$channel] = 1;
			}
		}

		ksort($reverse_entitlements);

		foreach ($reverse_entitlements as $expires => $pub_ids) {
			foreach ($pub_ids as $pub_id => $channels) {
				foreach ($channels as $channel => $t) {
					insert_entitlement($user_id, $pub_id, $channel, 0, $expires);
				}
			}
		}

		return true;
	}

	function _get_prd_entitlements( $user_id ) {
		if ( ! class_exists( 'harborPRD' ) ) { return false; }
		_stop_cache();
		_set_refresh_time( $user_id );
		$harborPRD = harborPRD::getInstance();
		$entitlements = $harborPRD->get_entitlements( $user_id );
		if ( ! is_array( $entitlements ) || empty( $entitlements ) ) { return false; }
		_reset_entitlements( $user_id, $entitlements );
		return $entitlements;
	}

	function _get_xpd_entitlements( $user_id ) {
		if ( ! class_exists( 'Harbor_XPD_API_Gateway' ) ) { return false; }
		_stop_cache();
		_set_refresh_time( $user_id );
		$harborXPD = Harbor_XPD_API_Gateway::getInstance();
		$entitlements = $harborXPD->get_entitlements( $user_id );
		if ( ! is_array( $entitlements ) || empty( $entitlements ) ) { return false; }
		_reset_entitlements( $user_id, $entitlements );
		return $entitlements;
	}

	function _get_cxd_entitlements( $user_id ) {
		if ( ! class_exists( 'harborCXDWebServiceGateway' ) ) { return false; }
		_stop_cache();
		_set_refresh_time( $user_id );
		$harborCXD = harborCXDWebServiceGateway::getInstance();
		$entitlements = $harborCXD->get_entitlements( $user_id );
		if ( !is_array( $entitlements ) || empty( $entitlements ) ) { return false; }
		_reset_entitlements( $user_id, $entitlements );
		return $entitlements;
	}

	function _get_fake_entitlements() {
		$entitlements = array();
		$expiry = strtotime('+1 week');
		$channels = array(
			'print'		=> $expiry,
			'web'		=> $expiry,
			'tablet'	=> $expiry,
		);
		$pub_ids = get_pub_ids();
		foreach($pub_ids as $p) {
			$entitlements[$p] = $channels;
		}
		return $entitlements;
	}

	function _stop_cache() {
		if ( ! defined( 'DONOTCACHEDB' ) || DONOTCACHEDB == false ) {
			define('DONOTCACHEDB', true);
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) || DONOTCACHEOBJECT == false ) {
			define('DONOTCACHEOBJECT', true);
		}
	}
