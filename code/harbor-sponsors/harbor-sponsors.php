<?php
/**
 * Plugin Name: Harbor Sponsors
 * Plugin URI: http://www.kwyjibo.com/
 * Description: Manage sponsorships of reports, articles, text-ads, etc.
 * Version: 0.1.1
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

class harborSponsors {

	// ACTIVATION AND SETUP

		private $_default_post_types = array('post', 'text_ad', 'page', 'harbor_downloads', 'harbor_fr_online');

		public function activate() {
			add_option('harbor_sponsors', false);
			add_option('harbor_category_sponsorships', true);
		}

		public function __construct() {
			register_activation_hook(__FILE__, array($this,'activate'));
			add_action( 'init', array($this, 'register_sponsor_taxonomy'), 10 );
			add_action( 'admin_menu', array($this, 'sponsor_admin_menu'), 10 );
			add_action( 'admin_menu', array($this, 'sponsor_post_metabox'), 10 );
			add_action( 'save_post', array($this, 'save_post_sponsor_data'), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'sponsor_manager_admin_enqueue' ) );
		}

		public function sponsor_manager_admin_enqueue( $page ) {
			if ( strpos( $page, 'harbor-sponsors' ) === false ) { return; }
			wp_enqueue_style( 'harbor_sponsor_manager_css', plugins_url( 'harbor-sponsor-manager.css', __FILE__ ) );
			wp_enqueue_script( 'harbor_sponsor_manager_js', plugins_url( 'harbor-sponsor-manager.js', __FILE__ ) );
		}

		public static function getInstance() {
			if (!self::$instance) { self::$instance = new self; }
			return self::$instance;
		}

	// REGISTER SPONSOR TAXONOMY

		function register_sponsor_taxonomy() {

			$options = get_option('harbor_sponsors');
			$post_types = (isset($options['sponsored_post_types'])) ? $options['sponsored_post_types'] : $this->_default_post_types;

			$labels = array(
				'name'					=> __('Sponsors'),
				'menu_name'				=> __('Sponsors'),
				'singular_name'			=> __('Sponsor'),
				'all_items'				=> __('All Sponsors'),
				'edit_item'				=> __('Edit Sponsor'),
				'view_item'				=> __('View Sponsor'),
				'update_item'			=> __('Update Sponsor'),
				'add_new_item'			=> __('Add New Sponsor'),
				'new_item_name'			=> __('New Sponsor Name'),
				'search_items'			=> __('Search Sponsors'),
				'popular_items'			=> __('Popular Sponsors'),
				'add_or_remove_items'	=> __('Add or Remove Sponsors'),
				'choose_from_most_used'	=> __('Select from popular Sponsors'),
				'not_found'				=> __('No Sponsors Found'),
			);

			$rewrite = array(
				'slug'					=> 'sponsor',
				'with_front'			=> false,
				'hierarchical'			=> false,
				'ep_mask'				=> 'EP_NONE' ,
			);

			$capabilities = array(
				'manage_terms'			=> 'manage_categories',
				'edit_terms'			=> 'manage_categories',
				'delete_terms'			=> 'manage_categories',
				'assign_terms'			=> 'edit_posts',
			);

			$args = array(
				'label'					=> __('Sponsor'),
				'labels'				=> $labels,
				'public'				=> true,
				'show_ui'				=> true,
				'show_in_menu'			=> false,
				'show_in_nav_menus'		=> false,
				'show_tagcloud'			=> false,
				'show_in_quick_edit'	=> false,
				'meta_box_callback'		=> null,
				'show_admin_column'		=> false,
				'description'			=> 'Sponsor',
				'hierarchical'			=> false,
				'update_count_callback'	=> null,
				'query_var'				=> 'sponsor',
				'rewrite'				=> false,
				'capabilities'			=> $capabilities,
				'sort'					=> false,
			);
			register_taxonomy('sponsor', $post_types, $args);
		}

	// ADD SPONSOR DATA TO POSTS

		public function sponsor_post_metabox() {

			$options = get_option('harbor_sponsors');
			$post_types = (isset($options['sponsored_post_types'])) ? $options['sponsored_post_types'] : $this->_default_post_types;

			add_meta_box('sponsor_selection_metabox', __('Sponsor'), array($this, 'display_sponsor_metabox'), $post_types, 'side', 'low');
		}

		public function display_sponsor_metabox( $post ) {

			$sponsors = get_terms('sponsor', 'hide_empty=0'); 

			echo "<select name='harbor_sponsor' id='select_harbor_sponsor'>";
				$names = wp_get_object_terms($post->ID, 'sponsor'); 
				echo "<option class='theme-option' value='' ";
				if (!count($names)) { echo "selected"; }
				echo ">Select Sponsor</option>";
				foreach ($sponsors as $sponsor) {
					echo "<option class='theme-option' value='" . $sponsor->slug . "'"; 
					if (!is_wp_error($names) && !empty($names) && !strcmp($sponsor->slug, $names[0]->slug)) { echo " selected"; }
					echo ">" . $sponsor->name . "</option>\n"; 
				}
			echo "</select>";
		}

		public function save_post_sponsor_data( $post_id ) {

			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) { return $post_id; }

			if ( !isset($_POST['harbor_sponsor']) ) { return $post_id; }

			$post = get_post($post_id);
			$sponsor = $_POST['harbor_sponsor'];
			wp_set_object_terms( $post_id, $sponsor, 'sponsor' );

			return $sponsor;
		}

	// MENU

		public function sponsor_admin_menu() {
			add_menu_page (__('Harbor Sponsor Manager'), __('Harbor Sponsors'), 'manage_options', 'harbor_sponsors', array($this, 'load_page'), 'dashicons-awards', 9);
			add_submenu_page ('harbor_sponsors', __('Harbor Sponsor Editor'), __('Edit Sponsors'), 'manage_options', 'edit_sponsors', array($this, 'load_page'));
			add_submenu_page ('harbor_sponsors', __('Sponsor Plugin Options'), __('Plugin Options'), 'manage_options', 'plugin_options', array($this, 'load_page'));
			remove_submenu_page('harbor_sponsors','harbor_sponsors');
		}

		public function load_page() {
			$page = $_GET['page'];
			$post = ( isset( $_GET['id'] ) ? $_GET['id'] : '' );

			wp_enqueue_media();

			switch ( $page ) {
				case 'plugin_options':
					$this->plugin_options($post);
					break;
				case 'edit_sponsors':
				default:
					$this->edit_sponsors($post);
					break;
			}
		}

	// EDIT SPONSORS

		public function edit_sponsors() {

			global $wpdb;
			$edit = $edit_sponsor_errors = false;
			$parents = array();

			$edit_sponsor_id = ( array_key_exists('term_id', $_GET) ) ? intval($_GET['term_id']) : false;

			if ( isset($_REQUEST['_wpnonce']) ) {

				if (wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_edit_sponsor')) {

					$this_term_id = (intval($_POST['term_id']) > 0) ? intval($_POST['term_id']) : false;

					//echo '<pre>'.print_r($_POST,1).'</pre>';

					if (!$_POST['name']) {
						$edit_sponsor_errors[] = 'Sponsor must have a name.';
					} else {
						$args['name'] = $_POST['name'];
					}

					$args['slug'] = ($_POST['slug']) ? sanitize_title($_POST['slug']) : sanitize_title($_POST['name']);

					if (empty($edit_sponsor_errors)) {

						if (!$this_term_id) { // new
							$term_array = wp_insert_term($_POST['name'], 'sponsor', $args);
							$this_term_id = $term_array['term_id'];
						} else { // edit
							wp_update_term($this_term_id, 'sponsor', $args);
						}

						if (array_key_exists('sponsor_url', $_POST)) {
							update_term_meta($this_term_id, 'sponsor_url', $_POST['sponsor_url']);
						}

						if (array_key_exists('sponsor_logo', $_POST)) {
							update_term_meta($this_term_id, 'sponsor_logo', $_POST['sponsor_logo']);
						}

						$active = (array_key_exists('active', $_POST)) ? true : false;
						update_term_meta($this_term_id, 'active', $active);

					}
				}

				if ( wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_delete_sponsor') ) {

					if ( !isset($_POST['term_id']) ) { return false; }

					if ( !intval($_POST['term_id']) ) { return false; }

					wp_delete_term( $_POST['term_id'], 'sponsor' );

				}

				if ( wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_sponsor_categories') ) {

					if ( !isset($_POST['harbor_category_sponsorships']) ) { return false; }

					$harbor_category_sponsorships = array_filter($_POST['harbor_category_sponsorships']);

					update_option('harbor_category_sponsorships', $harbor_category_sponsorships);


				}

			}

			echo '<div class="wrap">'; ?>

			<?php
			$plugin_data = get_plugin_data(__FILE__, 0, 0);
			echo '<h1>' . $plugin_data['Title'] . ' - Version ' . $plugin_data['Version'] .'</h1>';

			if ($edit_sponsor_errors) {
				echo '<div class="harbor_sponsor_notice">';
				foreach($edit_sponsor_errors as $a) { echo '<p>'.$a.'</p>'; }
				echo '</div>';
			}

			echo '<div class="harbor_sponsor_cols">';

			echo '<div class="harbor_sponsor_float_box">';

			echo '<h3>Current Sponsors</h3>';

			echo '<hr/>';

			$results = get_terms( array( 'taxonomy' => 'sponsor', 'hide_empty' => false, 'count' => true ) );

			$edit = array(
				'name'		=> false,
				'slug'		=> false,
				'url'		=> false,
				'active'	=> false,
				'logo'		=> false,
				'logo_url'	=> false,
				'count'		=> false,
			);

			echo '<ul>';
			foreach ($results as $r) {
				if ($r->term_id == $edit_sponsor_id) {
					$edit['name'] = esc_attr($r->name);
					$edit['slug'] = esc_attr($r->slug);
					$edit['url'] = esc_attr(get_term_meta( $edit_sponsor_id, 'sponsor_url', true ));
					$edit['active'] = (!empty(get_term_meta( $edit_sponsor_id, 'active', true ))) ? true : false;
					$edit['logo'] = intval(get_term_meta( $edit_sponsor_id, 'sponsor_logo', true ));
					$edit['logo_url'] = ($edit['logo']) ? esc_attr(wp_get_attachment_url($edit['logo'])) : false ;
					$edit['count'] = $r->count;
				}
				echo '<li><b><a href="admin.php?page=edit_sponsors&term_id='.$r->term_id.'">'.$r->name.'</a></b> (<a href="/wp-admin/edit.php?sponsor='.$r->slug.'">'.$r->count.'</a>)</li>';
			}
			echo '</ul>';
			echo '<p><small>Sponsor post count is approximate and does not include category sponsorships.</small></p>';
			echo '</div>';

			echo "<div class='harbor_sponsor_float_box'>";
			echo "<form method='post' action='/wp-admin/admin.php?page=edit_sponsors' class='harbor_sponsor_addedit'>";

			if ($edit_sponsor_id) {
				echo '<h3>Edit Sponsor <small style="float: right;">Term ID: '.$edit_sponsor_id.'</small></h3>';
				$label = "Submit Changes";
			} else {
				echo '<h3>Add New Sponsor</h3>';
				$label = "Add New Sponsor";
			}

			echo '<hr/>';

			echo "<label>Name: <input type='text' name='name' value='" . $edit['name'] . "' /></label>";
			echo "<label>Slug: <input type='text' name='slug' value='" . $edit['slug'] . "' /></label>";
			echo "<label>Sponsor URL: <input type='text' name='sponsor_url' value='" . $edit['url'] . "' /></label>";

			$active = ($edit['active']) ? 'checked' : '';
			echo "<label>Active: <input type='checkbox' name='active' value='1' ".$active." style='width: 15px; top: 9px;'/></label>";

			echo "<label>Sponsor Logo:";
			echo "<img id='harbor_sponsor_logo_image' src='" . $edit['logo_url'] . "' />";
			echo '<input id="harbor_sponsor_logo_button" type="button" class="button" value="' . __( 'Edit Sponsor Logo', 'harbor_sponsor' ) .'" /><br/>';
			if ( !empty($edit['logo_url']) ) {
				echo '<input id="harbor_sponsor_logo_delete_button" type="button" class="button" value="' . __( 'Delete Sponsor Logo', 'harbor_sponsor' ) .'" /><br/>';
			}
			echo "</label>";

			echo "<input type='hidden' name='sponsor_logo' id='sponsor_logo' value='" . $edit['logo'] . "' />";
			echo "<input type='hidden' name='term_id' value='".$edit_sponsor_id."' />";

			echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="' . __( $label.' &raquo;', 'harbor_sponsor' ) . '" /></p>';


			wp_nonce_field('harbor_edit_sponsor');

			echo '</form>';

			if ($edit_sponsor_id > 0) {

				echo "<p>";

				echo "<hr/>";

				echo "<form method='post' action='/wp-admin/admin.php?page=edit_sponsors'>";

				echo "<div style='padding-top: 5px;'><input type='submit' value='Delete' class='button' style='margin-top: -5px;' onclick='return confirm(\"Are you sure you want to delete this sponsor?\");'> &nbsp; DELETE ".strtoupper( $edit['name'] )."</div>";
				
				echo "<input type='hidden' name='term_id' value='".$edit_sponsor_id."'>";

				echo "</p>";

				wp_nonce_field('harbor_delete_sponsor');

				echo '</form>';

			}

			echo '</div>';

			echo '<div class="harbor_sponsor_float_box">';

			echo '<h3>Category Sponsorships</h3>';

			echo '<hr/>';

			echo "<form method='post' action='/wp-admin/admin.php?page=edit_sponsors' class='harbor_sponsor_addedit'>";

				$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
				$harbor_category_sponsorships = get_option('harbor_category_sponsorships');
				$all_sponsors = get_terms('sponsor', 'hide_empty=0');

				$sponsorships = ( $harbor_category_sponsorships ) ? $harbor_category_sponsorships : array();

				foreach ($categories as $c) {
					echo '<label>'.$c->name;

					echo "<select name='harbor_category_sponsorships[".$c->term_id."]'>";
						echo "<option value='0'></option>";
						echo "<option value='X'>Remove Sponsorship</option>";
						foreach ($all_sponsors as $s) {
							$selected = false;
							if ( isset($sponsorships[$c->term_id]) ) {
								if ( $sponsorships[$c->term_id] == $s->term_id ) {
									$selected = " selected";
								}
							}
							echo "<option value='" . $s->term_id . "'" . $selected . ">" . $s->name . "</option>\n"; 
						}
					echo "</select>";

					echo '</label>';
				}

				echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="' . __( 'Update Category Sponsorships &raquo;', 'harbor_sponsor' ) . '" /></p>';

				wp_nonce_field('harbor_sponsor_categories');

			echo '</form>';

			echo '</div>';

			echo '</div>'; // .harbor_sponsor_cols
			echo '</div>'; // .wrap
		}

	// PLUGIN OPTIONS

		public function plugin_options() {

			$plugin_data = get_plugin_data(__FILE__, 0, 0);

			if (isset($_REQUEST['_wpnonce'])) {
				if (wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_sponsors_options')) {
					unset($_POST['_wpnonce']);
					unset($_POST['_wp_http_referer']);
					unset($_POST['Submit']);
					update_option('harbor_sponsors', $_POST);
				}
			}

			$options = get_option('harbor_sponsors');

			echo '<div class="wrap">';
			echo '<h1>'.__($plugin_data['Title']).' - Version '.__($plugin_data['Version']).'</h1>';
			echo '<h3>'.__('Edit Plugin Options').'</h1>';
			echo '<form action="/wp-admin/admin.php?page=plugin_options" method="post" id="harbor-sponsors-options" class="harbor_sponsor_float_box harbor_sponsor_settings">';

			$types = get_post_types( array(), 'objects' );

			$selected_post_types = (isset($options['sponsored_post_types'])) ? $options['sponsored_post_types'] : $this->_default_post_types;

			?>
			<p>
				Which post types allow Sponsorship:<br>
				<table width='100%'><tr><td valign='top'>
						<?php
						$col = ceil(count($types)/4);
						$i = 0;
						foreach($types as $t => $object) { $i++;
							echo "<label><input type='checkbox' name='sponsored_post_types[]' value='".$t."'";
							if (in_array($t, $selected_post_types)) { echo " checked"; }
							echo ">&nbsp;".$object->label." (".$t.")</label><br/>";
							if ($i == $col) {
								echo "</td><td valign='top'>";
								$i = 0;
							}
						}
						?>
					</td></tr></table>
			</p>
			<?php

			echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="'.__('Update Options &raquo;', 'harbor-prd').'" /></p>';

			wp_nonce_field('harbor_sponsors_options');

			echo '</form>';
			echo '</div>';
		}

}

// instantiate our class
$harborSponsors = new harborSponsors();

// ----------------------------------------------------------------------------
// HELPER FUNCTIONS

	function harbor_format_sponsor_array( $sponsor ) {

		if ( is_wp_error($sponsor) || empty($sponsor) || !$sponsor->name ) { return false; }

		$active = get_term_meta( $sponsor->term_id, 'active', true );

		if ( !$active ) { return false; }

		$url = get_term_meta( $sponsor->term_id, 'sponsor_url', true );
		$logo_id = get_term_meta( $sponsor->term_id, 'sponsor_logo', true );
		$logo_img = ( $logo_id ) ? wp_get_attachment_image_src($logo_id, 'full') : false ;

		$output['name'] = $sponsor->name;

		if ( $url ) { $output['url'] = $url; }
		if ( $logo_id ) { $output['logo_id'] = $logo_id; }
		if ( is_array($logo_img) ) {
			$output['logo_img'] = $logo_img[0];
			$output['logo_width'] = $logo_img[1];
			$output['logo_height'] = $logo_img[2];
		}

		return $output;

	}

	function harbor_get_post_sponsor( $post_id ) {

		if ( !is_numeric($post_id) ) { return false; }

		$sponsor = wp_get_object_terms( $post_id, 'sponsor' );

		if ( empty($sponsor) ) { return false; }

		$sponsor = ( is_array($sponsor) ) ? $sponsor[0] : $sponsor ;

		return harbor_format_sponsor_array( $sponsor );

	}

	function harbor_get_category_sponsor( $term_id ) {

		if ( !is_numeric($term_id) ) { return false; }

		$harbor_category_sponsorships = get_option('harbor_category_sponsorships');
		$sponsorships = ( $harbor_category_sponsorships ) ? $harbor_category_sponsorships : array();

		$sponsor = ( isset($sponsorships[$term_id]) ) ? get_term( $sponsorships[$term_id], 'sponsor' ) : false ;

		return harbor_format_sponsor_array( $sponsor );

	}
