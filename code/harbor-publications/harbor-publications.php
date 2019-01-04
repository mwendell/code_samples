<?php
/**
 * Plugin Name: Harbor Publications
 * Plugin URI: http://www.kwyjibo.com/
 * Description: Allows the creation of the table of contents pages for individual issues within a publication.
 * Version: 0.86
 * License: GPL
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */


/**
 * --------------------------------------------------------------
 * Register the Table of Contents post type
 * --------------------------------------------------------------
 */
function register_toc_post_type() {
	$labels = array(
		'name'					=> __('Harbor Publication Manager: Issues'),
		'menu_name'				=> __('Harbor Pubs'),
		'singular_name'			=> __('Issue'),
		'add_new'				=> __('Add New Issue'),
		'add_new_item'			=> __('Add New issue'),
		'edit_item'				=> __('Edit Issue'),
		'new_item'				=> __('New Issue'),
		'view_item'				=> __('View Issue'),
		'search_items'			=> __('Search Issues'),
		'not_found'				=> __('No Issues Found'),
		'not_found_in_trash'	=> __('No Issues Found in Trash')
	);
	$args = array(
		'labels'				=> $labels,
		'description'			=> 'Table of Contents post types are used to manage the list of articles that comprise a single issue of a specific publication.',
		'exclude_from_search'	=> true,
		'publicly_queryable'	=> true,
		'show_in_nav_menus'		=> false,
		'show_ui'				=> false,
		'show_in_menu'			=> false,
		'hierarchical'			=> true,
		'supports'				=> array('title', 'thumbnail'),
		//'rewrite'				=> array('slug' => 'toc'),
		//'taxonomies'			=> array('post_tag'),
	);
	register_post_type('toc', $args);
}
add_action('init',  'register_toc_post_type');

/**
 * --------------------------------------------------------------
 * Register the Issue taxonomy
 * --------------------------------------------------------------
 */
function register_publication_taxonomy() {
	$labels = array(
		'name'					=> __('Publications'),
		'menu_name'				=> __('Publications'),
		'singular_name'			=> __('Publication'),
		'all_items'				=> __('All Publications'),
		'edit_item'				=> __('Edit Publication'),
		'view_item'				=> __('View Publication'),
		'update_item'			=> __('Update Publication'),
		'add_new_item'			=> __('Add New Publication'),
		'new_item_name'			=> __('New Publication Name'),
		'search_items'			=> __('Search Publications'),
		'popular_items'			=> __('Popular Publications'),
		'add_or_remove_items'	=> __('Add or Remove Publications'),
		'choose_from_most_used'	=> __('Select from popular Publications'),
		'not_found'				=> __('No Publications Found'),
	);

	$rewrite = array(
		'slug'					=> 'publication',
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
		'label'					=> __('Publication'),
		'labels'				=> $labels,
		'public'				=> true,
		'show_ui'				=> true,
		'show_in_menu'			=> true,
		'show_in_nav_menus'		=> true,
		'show_tagcloud'			=> false,
		'show_in_quick_edit'	=> true,
		'meta_box_callback'		=> null,
		'show_admin_column'		=> true,
		'description'			=> 'Publication',
		'hierarchical'			=> true,
		'update_count_callback'	=> null,
		'query_var'				=> 'pubslug',
		'rewrite'				=> true,
		'capabilities'			=> $capabilities,
		'sort'					=> false,
	);
	register_taxonomy('publication', array('toc', 'post', 'harbor_products'), $args);
}
add_action('init', 'register_publication_taxonomy');

/**
 * --------------------------------------------------------------
 * Admin columns for Table of Contents post type
 * --------------------------------------------------------------
 */
function toc_type_columns( $taxonomies ) {
	$taxonomies[] = 'publication';
	return $taxonomies;
}
add_filter( 'manage_taxonomies_for_toc_columns', 'toc_type_columns' );

class harborPubs {

	// ------------------------------------------------------------------------
	// ACTIVATION AND SETUP

		private $_settings;
		private $_errors = array();
		private $_message = '';

		public function activate() {
			add_option('harbor_pubs', false);
		}

		public function __construct() {
			register_activation_hook(__FILE__, array($this,'activate'));
			add_action('admin_menu', array($this, 'save_issue'), 5);
			add_action('admin_menu', array($this, 'delete_issue'), 5);
			add_action('admin_menu', array($this, 'admin_menu'), 10);
			add_filter('query_vars', array($this, 'parameter_queryvars'));
			add_action( 'wp_enqueue_scripts', array( $this, 'pub_manager_enqueue_scripts' ) );
		}

		public function pub_manager_enqueue_scripts() {
			wp_enqueue_style('thickbox');
			wp_enqueue_script('thickbox');
		}

		public function parameter_queryvars( $qvars ) {
			$qvars[] = 'delete';
			$qvars[] = 'term_id';
			return $qvars;
		}

		public static function getInstance() {
			if (!self::$instance) { self::$instance = new self; }
			return self::$instance;
		}

	// ------------------------------------------------------------------------
	// MENU AND OPTIONS

		public function admin_menu() {
			add_menu_page (__('Harbor Publications Manager'), __('Harbor Pubs'), 'manage_options', 'harbor_pubs', array($this, 'load_page'), 'dashicons-book-alt', 9);
			add_submenu_page ('harbor_pubs', __('Harbor Publications Manager'), __('Edit Issues'), 'manage_options', 'harbor_pubs', array($this, 'load_page'));
			add_submenu_page ('harbor_pubs', __('Harbor Pubs Editor'), __('Add New Issue'), 'manage_options', 'harbor_pubs_editor', array($this, 'load_page'));
			add_submenu_page ('harbor_pubs', __('Harbor Pubs Options'), __('Manage Options'), 'manage_options', 'harbor_pubs_options', array($this, 'load_page'));
			add_submenu_page( 'harbor_pubs',  __('Publications'), __('Edit Publications'), 'manage_options', 'harbor_pubs_manage', array($this, 'load_page'));
			//add_media_page(__('Harbor Publications Manager'), __('Media'), 'manage_options', 'harbor_pubs_media', array($this, 'load_page') );
		}

		public function load_page() {
			$page = $_GET['page'];
			$post = ( isset( $_GET['id'] ) ? $_GET['id'] : '' );

			switch ( $page ) {
				case 'harbor_pubs_editor':
					$this->edit_issue($post);
					break;

				case 'harbor_pubs_options':
					$this->manage_options($post);
					break;

				case 'harbor_pubs_manage':
					$this->edit_pubs();
					break;

				case 'harbor_pubs':
				default:
					$this->select_issue();
					break;
			}
		}

	// ------------------------------------------------------------------------
	// SELECT ISSUE / EDIT PUBS

		public function select_issue() {

			global $wpdb;

			// This query loads all of the publications and their associated issues.
			// Publications are ordered by parent, and then alphabetically.
			// Issues within each publication are ordered NEWEST FIRST by volume, number, issue_date and finally post date.

			$sql = "SELECT p.ID, p.post_title, p.post_date, p.post_status, m1.meta_value AS issue_date, m2.meta_value AS issue_volume, m3.meta_value AS issue_number, m4.meta_value AS articles, k.name AS parent, t.name, t.term_id
				FROM wp_posts p
				JOIN wp_postmeta m1 ON p.ID = m1.post_id AND m1.meta_key = 'toc_issue_date'
				JOIN wp_postmeta m2 ON p.ID = m2.post_id AND m2.meta_key = 'toc_issue_volume'
				JOIN wp_postmeta m3 ON p.ID = m3.post_id AND m3.meta_key = 'toc_issue_number'
				JOIN wp_postmeta m4 ON p.ID = m4.post_id AND m4.meta_key = 'toc_articles'
				JOIN wp_term_relationships r ON p.ID = r.object_id
				JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.taxonomy = 'publication'
				JOIN wp_terms t ON x.term_id = t.term_id
				LEFT JOIN wp_terms k ON x.parent = k.term_id
				WHERE post_type = 'toc'
				ORDER BY parent, name, issue_volume DESC, issue_number DESC, issue_date DESC, post_date DESC;";
			$results = $wpdb->get_results($sql, ARRAY_A);

			?>

			<div class="wrap">

				<?php
				$plugin_data = get_plugin_data(__FILE__, 0, 0);
				echo '<h1>' . $plugin_data['Title'] . ' - Version ' . $plugin_data['Version'] .'</h1>';
				?>

				<style>
					.toc-table { position: relative; width: 100%; }

					.toc-table-header { background-color: #777; font-weight: bold; color: #fff !important; }
					.toc-table-header h3 { margin: 5px 0; color: #fff; }

					.toc-pub-header { background-color: #f6f6f6; cursor: pointer; text-align: left; color: #333; font-size: 1.1em; height: 3.3em; width: 100%; vertical-align: middle; padding-left: 10px; }
					.toc-pub-header:hover { background-color: #efefef; color: #0073aa; }
					.toc-pub-header span { position: relative; display: block; top: 50%; transform: translateY(-50%); }
					.toc-pub-header span a { top: 0 !important; }

					.cell { width: 10%; display: table-cell; text-align: center; vertical-align: middle; height: 3.3em; }
					.col-title { width: 40%; text-align: left; padding-left: 10px; }
					.toc-issue .col-title { padding-left: 30px; }

					.white { background-color: #fff; border-top: 1px solid #dedede; color: #333; }
					.white a { color: #0073aa; }
					.white:last-child { border-bottom: 1px solid #dedede; }

					.cell .a-button, .cell .a-button:active { margin: 0 20px; top: 1px; padding: 4px 12px; }

					.issues-hide { display: none; }
					.show-small { display: none; }

					@media (max-width: 900px) {
						.cell { display: block; width: 100%; text-align: left; height: 2.5em; padding: 12px 0 0 30px; margin: 0; }
						.show-small { display: block; }
						.hide-small { display: none; }
						.cell .a-button, .cell .a-button:active { float: right; right: 17px; top: -3px; }
						.toc-pub-header span { width: 90%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
					}

				</style>

				<script>
					jQuery(document).ready(function(){
						jQuery(".toc-pub-header").click(function(){
							jQuery(".issues-hide").slideUp(200);
							var pub = jQuery(this).data('name');
							if (jQuery(".issues-" + pub).css('display') == 'none') {
								jQuery(".issues-" + pub).slideDown(200);
							}
						})
					})
				</script>

				<?php
				$pub_parent = $results[0]['parent'];
				$last_pub_id = 0;
				$last_parent = $pub_parent;

				$table_header_before = "<div class='toc-table-header'>";
				$table_header_before .= "<div class='cell col-title'><h3>";
				$table_header_after = "</h3></div>";
				$table_header_after .= "<div class='cell col-updated hide-small'>Updated</div>";
				$table_header_after .= "<div class='cell col-view hide-small'>View</div>";
				$table_header_after .= "<div class='cell col-volume hide-small'>Volume</div>";
				$table_header_after .= "<div class='cell col-number hide-small'>Number</div>";
				$table_header_after .= "<div class='cell col-articles hide-small'>Articles</div>";
				$table_header_after .= "<div class='cell col-issue hide-small'>Issue Date</div>";
				$table_header_after .= "<div class='cell col-action hide-small'>&nbsp;</div>";
				$table_header_after .= "</div>";

				?>

				<div class="toc-table">

					<?php

					echo $table_header_before . $pub_parent . $table_header_after;

					foreach($results as $r) {
						$post_id = $r['ID'];
						$post_title = ($r['post_title']) ? stripslashes($r['post_title']) : '(Blank Title)';
						$post_date = date('Y-m-d', strtotime($r['post_date']));
						$post_perm = get_permalink($post_id);
						$post_status = $r['post_status'];
						$post_status = ($post_status == 'publish') ? '' : ' - ' . $post_status;
						$issue_date = date('F Y', strtotime($r['issue_date']));
						$issue_volume = $r['issue_volume'];
						$issue_number = $r['issue_number'];
						$articles = count(unserialize($r['articles']));
						$articles = (empty($articles)) ? '' : $articles ;
						$pub_name = $r['name'];
						$pub_id = $r['term_id'];
						$pub_parent = $r['parent'];

						if ($pub_id != $last_pub_id && $last_pub_id > 0) {
							echo "</div><!-- .issues-hide ".$pub_id." -->";
						}

						if ($pub_parent != $last_parent) {

							echo $table_header_before . $pub_parent . $table_header_after;

							$last_parent = $pub_parent;
						}


						if ($pub_id != $last_pub_id) {
							echo "<div class='toc-pub-header' data-name='".$pub_id."'><span>";
							echo "	<a href='/wp-admin/admin.php?page=harbor_pubs_editor&pub_id=".$pub_id."' class='page-title-action a-button'>Add New Issue</a>";
							echo "	&nbsp; &nbsp; &nbsp;<b>".$pub_name."</b>";
							echo "</span></div>";
							echo "<div class='issues-hide issues-".$pub_id."'>";
							$last_pub_id = $pub_id;
						}

						$edit = "<a href='/wp-admin/admin.php?page=harbor_pubs_editor&id=".$post_id."' title='Edit ".$post_title."'>".$post_title.$post_status."</a>";
						$view = "<a target='_blank' href='".$post_perm."' title='View ".$post_title."'>View Issue</a>";


						$confirm_msg = "This will delete the Table of Contents for this issue.\n\nNo articles will be deleted. Articles attached to this issue will\nbecome available to place in other issues.\n\nPress Yes or OK to delete the article.";

						$delete_url = admin_url('admin.php?page=harbor_pubs');
						$delete_url = wp_nonce_url($delete_url, 'delete_post', 'delete_toc_nonce');
						$delete_url = add_query_arg( 'delete', $post_id, $delete_url);
						$delete = "<a href='".$delete_url."' class='page-title-action a-button' onclick='return confirm(`".$confirm_msg."`)'>Delete&nbsp;Issue</a>";

						echo "<div id='post-".$post_id."' class='toc-issue white'>";
						echo "	<div class='cell col-title hide-small'>".$edit."</div>";
						echo "	<div class='cell col-updated hide-small'><small>".$post_date."</small></div>";
						echo "	<div class='cell col-volume hide-small'>".$view."</div>";
						echo "	<div class='cell col-volume hide-small'>".$issue_volume."</div>";
						echo "	<div class='cell col-number hide-small'>".$issue_number."</div>";
						echo "	<div class='cell col-articles hide-small'>".$articles."</div>";
						echo "	<div class='cell col-issue hide-small'>".$issue_date."</div>";
						echo "	<div class='cell col-action hide-small'>".$delete."</div>";
						echo "	<div class='cell show-small'>".$edit.$delete."</div>";
						echo "</div>";

					}

					echo "</div><!-- .issues-hide -->";

					echo $table_header_before . $table_header_after;

					?>

				</div><!-- .toc-table -->

			</div><!-- .wrap -->
			<?php
		}

		public function edit_pubs() {

			global $wpdb;
			$edit = $edit_pub_errors = false;
			$parents = array();

			if (isset($_REQUEST['_wpnonce'])) {
				if (wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_edit_pub')) {

					$this_term_id = (intval($_POST['term_id']) > 0) ? intval($_POST['term_id']) : false;

					//echo '<pre>'.print_r($_POST,1).'</pre>';

					if (!$_POST['name']) {
						$edit_pub_errors[] = 'Publication must have a name.';
					} else {
						$args['name'] = $_POST['name'];
					}

					$args['slug'] = ($_POST['slug']) ? sanitize_title($_POST['slug']) : sanitize_title($_POST['name']);

					if ($_POST['description']) { $args['description'] = $_POST['description']; }

					if (intval($_POST['parent_id']) > 0) { $args['parent'] = intval($_POST['parent_id']); }

					if ( empty( $edit_pub_errors ) ) {

						if ( !$this_term_id ) { // new
							$term_array = wp_insert_term($_POST['name'], 'publication', $args);
							$this_term_id = $term_array['term_id'];
						} else { // edit
							wp_update_term($this_term_id, 'publication', $args);
						}

						if ( isset( $_POST['pub_id'] ) ) {
							$_POST['pub_id'] = ( ! strlen( $_POST['pub_id'] ) ) ? null : $_POST['pub_id'];
							update_term_meta($this_term_id, 'pub_id', $_POST['pub_id']);
						}

						if ( isset( $_POST['sub_code'] ) ) {
							$_POST['sub_code'] = ( ! strlen( $_POST['sub_code'] ) ) ? null : $_POST['sub_code'];
							update_term_meta($this_term_id, 'sub_code', $_POST['sub_code']);
						}

						$active = ( isset( $_POST['active'] ) ) ? true : false;
						update_term_meta( $this_term_id, 'active', $active );

						$no_toc = ( isset( $_POST['no_toc'] ) ) ? true : false;
						update_term_meta( $this_term_id, 'no_toc', $no_toc );

						if ( isset( $_POST['pub_order'] ) ) {
							$_POST['pub_order'] = ( ! strlen( $_POST['pub_order'] ) ) ? null : $_POST['pub_order'];
							update_term_meta($this_term_id, 'pub_order', $_POST['pub_order']);
						}

						if ( isset( $_POST['img_logo'] ) ) {
							$_POST['img_logo'] = ( ! strlen( $_POST['img_logo'] ) ) ? null : $_POST['img_logo'];
							update_term_meta($this_term_id, 'img_logo', $_POST['img_logo']);
						}

						if ( isset( $_POST['img_cover'] ) ) {
							$_POST['img_cover'] = ( ! strlen( $_POST['img_cover'] ) ) ? null : $_POST['img_cover'];
							update_term_meta($this_term_id, 'img_cover', $_POST['img_cover']);
						}

						if ( isset( $_POST['pub_channels'] ) ) {
							$_POST['pub_channels'] = ( ! strlen( $_POST['pub_channels'] ) ) ? null : $_POST['pub_channels'];
							update_term_meta($this_term_id, 'pub_channels', implode(',', $_POST['pub_channels']));
						}

					}
				}

				if (wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_delete_pub')) {
					$this_term_id = (intval($_POST['term_id']) > 0) ? intval($_POST['term_id']) : false;
					$post_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(p.ID) as post_count FROM wp_posts p
							JOIN wp_term_relationships r ON p.ID = r.object_id
							JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.term_id = %d
							WHERE (post_type = 'toc');", $this_term_id));
				}
			}

			$sql = "SELECT t.term_id, t.name, t.slug, x.description, p.term_id AS parent_id, p.name AS parent_name, p.slug AS parent_slug, m1.meta_value AS pub_id, m2.meta_value AS sub_code, m3.meta_value AS active, m4.meta_value AS no_toc, m5.meta_value AS img_logo, m6.meta_value AS img_cover, m7.meta_value AS pub_channels, m8.meta_value AS pub_order
				FROM wp_terms t
				JOIN wp_term_taxonomy x ON t.term_id = x.term_id AND x.taxonomy = 'publication'
				LEFT JOIN wp_terms p ON p.term_id = x.parent
				LEFT JOIN wp_termmeta m1 ON t.term_id = m1.term_id AND m1.meta_key = 'pub_id'
				LEFT JOIN wp_termmeta m2 ON t.term_id = m2.term_id AND m2.meta_key = 'sub_code'
				LEFT JOIN wp_termmeta m3 ON t.term_id = m3.term_id AND m3.meta_key = 'active'
				LEFT JOIN wp_termmeta m4 ON t.term_id = m4.term_id AND m4.meta_key = 'no_toc'
				LEFT JOIN wp_termmeta m5 ON t.term_id = m5.term_id AND m5.meta_key = 'img_logo'
				LEFT JOIN wp_termmeta m6 ON t.term_id = m6.term_id AND m6.meta_key = 'img_cover'
				LEFT JOIN wp_termmeta m7 ON t.term_id = m7.term_id AND m7.meta_key = 'pub_channels'
				LEFT JOIN wp_termmeta m8 ON t.term_id = m8.term_id AND m8.meta_key = 'pub_order'
				ORDER BY parent_name, name;";

			echo '<div class="wrap">'; ?>

			<style>
				.pub-left { width: 30%; float: left; }
				.pub-right { width: 65%; float: right; }
				.pub-form label { position: relative; display: block; margin: 10px 0; padding-top: 5px; }
				.pub-form label div { position: relative; display: block; margin: 0; height: 30px; overflow: hidden; padding: 0; }
				.pub-form label input,
				.pub-form label select { position: absolute; left: 180px; top: 0; width: calc(100% - 182px) }
				.pub-form label textarea { width: 100%; height: 100px; margin-top: 5px; }
				.pub-form label i { display: block; position: absolute; left: 210px; top: 4px; }
			</style>

			<?php
			$plugin_data = get_plugin_data(__FILE__, 0, 0);
			echo '<h1>' . $plugin_data['Title'] . ' - Version ' . $plugin_data['Version'] .'</h1>';

			echo '<div class="pub-left">';

			echo '<h3>Select Publication</h3>';

			$results = $wpdb->get_results($sql, ARRAY_A);
			$results = $this->hierarchy_sort($results);
			echo '<ul>';
			$get_term_id = ( isset( $_GET['term_id'] ) ) ? $_GET['term_id'] : false;
			foreach ($results as $r) {
				if ($r['term_id'] == $get_term_id) { $edit = $r; }
				echo '<li><h4 style="margin-bottom: 6px; margin-top: 0;"><a href="admin.php?page=harbor_pubs_manage&term_id='.$r['term_id'].'">'.$r['name'].'</a> ('.$r['pub_id'].')</h4>';
				if ($r['is_parent']) {
					$parents[] = array($r['term_id'], $r['name']);
					if (!empty($r['children'])) {
						echo '<ul style="margin-left: 20px; margin-bottom: 10px;">';
						foreach ($r['children'] as $c) {
							if ( $c['term_id'] == $get_term_id ) { $edit = $c; }
							echo '<li><a href="admin.php?page=harbor_pubs_manage&term_id='.$c['term_id'].'">'.$c['name'].'</a> ('.$c['pub_id'].')</li>';
						}
						echo '</ul>';
					}
				}
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';

			echo '<div class="pub-right">';

			if ( $get_term_id ) {
				echo '<h3>Edit Publication <small style="float: right;">Term ID: '.$get_term_id.'</small></h3>';
			} else {
				echo '<h3>Add New Publication</h3>';
			}

			if ($edit_pub_errors) {
				echo '<div>';
				foreach($edit_pub_errors as $a) { echo '<p>'.$a.'</p>'; }
				echo '</div>';
			}

			echo "<form method='post' action='/wp-admin/admin.php?page=harbor_pubs_manage' class='pub-form'>";

			echo "<label>Name: <input type='text' name='name' value='".esc_attr($edit['name'])."' /></label>";
			echo "<label>Slug: <input type='text' name='slug' value='".esc_attr($edit['slug'])."' /></label>";

			echo "<label>Parent (if Child Publication): <select name='parent_id'>";
			echo "	<option value='none'>Select Parent</option>";
			foreach($parents as $p) {
				echo "	<option value='".$p[0]."'";
				if ($edit['parent_id'] == $p[0]) { echo 'selected'; }
				echo ">".esc_attr($p[1])."</option>";
			}
			echo "</select></label>";

			echo "<label>Pub ID: <input type='text' name='pub_id' value='".esc_attr($edit['pub_id'])."' /></label>";
			echo "<label>Default Subscription URL: <input type='text' name='sub_code' value='".esc_attr($edit['sub_code'])."' /></label>";

			$active = ($edit['active']) ? 'checked' : '';
			echo "<label>Active: <input type='checkbox' name='active' value='1' ".$active." style='width: 15px; top: 9px;'/></label>";

			$no_toc = ($edit['no_toc']) ? 'checked' : '';
			echo "<label>Single-Posts: <input type='checkbox' name='no_toc' value='1' ".$no_toc." style='width: 15px; top: 9px;'/> <i>Publication does not use Table of Contents (TOC) posts.</i></label>";

			echo "<label>Pub Order: <input type='text' name='pub_order' value='".esc_attr($edit['pub_order'])."'/></label>";

			$channels = get_channels();
			echo "<label>Available Channels:";
			if ( is_array( $channels ) ) {
				foreach ($channels as $c) {
					$c_pub = (strpos($edit['pub_channels'], $c) !== false) ? 'checked' : '';
					echo "<div><input type='checkbox' name='pub_channels[]' value='".$c."' ".$c_pub." style='width: 15px; top: 9px;'/> <i>".$c."</i></div>";
				}
			}
			echo "</label>";

			echo "<label>Logo Image: <input type='text' name='img_logo' value='".esc_attr($edit['img_logo'])."' /></label>";
			echo "<label>Cover Image: <input type='text' name='img_cover' value='".esc_attr($edit['img_cover'])."' /></label>";

			echo "<label style='height: 120px;'>Description: &nbsp; &nbsp; <small>Limited to 250 characters, including any HTML markup.</small><br/>";
			echo "<textarea name='description' maxlength='250'>";
			echo esc_attr($edit['description']);
			echo "</textarea></label>";

			echo "<hr/>";
			echo "<div style='text-align: right;'><input type='submit' value='Submit' class='button button-primary'></div>";
			if ( $get_term_id ) {
				echo "<input type='hidden' name='term_id' value='" . $get_term_id . "'>";
			}

			wp_nonce_field('harbor_edit_pub');

			echo '</form>';

			if ( intval( $get_term_id ) > 0 ) {
				$post_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(p.ID) as post_count FROM wp_posts p
							JOIN wp_term_relationships r ON p.ID = r.object_id
							JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.term_id = %d
							WHERE (post_type = 'toc');", $get_term_id));

				if ($post_count < 1) {

					echo '<br/><br/>';

					echo "<form method='post' action='/wp-admin/admin.php?page=harbor_pubs_manage' class='pub-form'>";

					echo "<hr/>";
					echo "<div style='padding-top: 5px;'><input type='submit' value='Delete' class='button' style='margin-top: -5px;'> &nbsp; DELETE ".strtoupper(esc_attr($edit['name']))."</div>";
					echo "<input type='hidden' name='term_id' value='".$get_term_id."'>";

					wp_nonce_field('harbor_delete_pub');

					echo '</form>';
				}
			}

			echo '</div>';
			echo '</div>';
		}

		public function delete_issue() {
			if (isset($_GET['delete_toc_nonce']) && wp_verify_nonce($_GET['delete_toc_nonce'], 'delete_post')) {
				if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
					$delete_id = $_GET['delete'];
					if ('toc' == get_post_type($delete_id)) {
						$error = wp_delete_post($delete_id);
						if ($error !== false) {
							add_action('admin_notices', array($this, 'notice_delete_success'));
						}
					}
				}
			}
		}

		public function notice_delete_success() {
			printf("<div class='updated notice'><p>Issue Table of Contents successfully moved to trash.</p></div>");
		}

		// this sort will only handle parent->child, it will not do parent->child->child, etc.
		public function hierarchy_sort($array) {
			$output = array();
			$sub_array = $array;
			$i = 0;
			foreach ($array as $a) {
				if (empty($a['parent_id'])) {
					$a['is_parent'] = '1';
					$a['children'] = array();
					$output[$i] = $a;
					foreach ($sub_array as $b) {
						if ($b['parent_id'] == $a['term_id']) {
							$b['is_parent'] = '0';
							$output[$i]['children'][] = $b;
						}
					}
					$i++;
				}
			}
			//echo '<pre>'.print_r($output,1).'</pre>';
			return $output;
		}

	// ------------------------------------------------------------------------
	// EDIT ISSUE

		public function edit_issue($post_id = 0) {

			global $wpdb;

			// setup defaults to avoid notices for non defined variables
			$pub_name = '';
			$pub_id = '';
			$post = new stdClass();
			$post->post_title = $post->post_content = $post->post_status = '';
			$toc_articles = false;
			$toc_featured = $toc_highlighted = $toc_sponsored = array();
			$toc_issue_pdf = '';

			if ($post_id > 0) {
				$post = get_post($post_id);
				$meta = get_post_meta($post_id);
				$pub_array = wp_get_post_terms($post_id, 'publication');
				$pub_id = $pub_array[0]->term_id;
				if (empty($pub_id)) { $pub_id = "0"; }
				$pub_name = $pub_array[0]->name;

				wp_enqueue_media(array('post' => $post_id));
				wp_enqueue_script('post');

				$toc_articles = get_post_meta($post->ID, 'toc_articles', true);
				foreach ($toc_articles as $key => $t) {
					$toc_articles[$key] = strval($t);
				}
				$toc_featured = explode(',', get_post_meta($post->ID, 'toc_featured', true));
				$toc_highlighted = explode(',', get_post_meta($post->ID, 'toc_highlighted', true));
				$toc_sponsored = explode(',', get_post_meta($post->ID, 'toc_sponsored', true));
				$toc_issue_pdf = get_post_meta($post->ID, 'toc_issue_pdf', true);

				if (!empty($toc_articles)) {
					$sql = "SELECT ID, post_title FROM wp_posts WHERE (ID IN (".implode(",", $toc_articles)."));";
					$titles = $wpdb->get_results($sql, OBJECT_K);
				} else {
					$titles = false;
				}
			} else {
				if ( isset( $_GET['pub_id'] ) && $_GET['pub_id'] > 0 ) {
					$pub_id = (int) $_GET['pub_id'];
					$sql = $wpdb->prepare("SELECT ID FROM wp_posts p
							JOIN wp_term_relationships r ON p.ID = r.object_id
							JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id
							JOIN wp_terms t ON x.term_id = t.term_id AND t.term_id = %d
							WHERE (p.post_type = 'toc') AND (p.post_status IN ('publish', 'draft'))
							ORDER BY p.post_date DESC LIMIT 1;", $pub_id);
					$most_recent_post_id = $wpdb->get_var($sql);
					$meta = get_post_meta($most_recent_post_id);
				} else {
					$meta = array();
				}
			}

			$harbor_pubs_options = get_option('harbor_pubs');
			$featured_input = ($harbor_pubs_options['featured'] == 'checkbox') ? 'checkbox' : 'radio';
			$show_sponsored = ($harbor_pubs_options['sponsored'] == '1') ? true : false;
			$show_post_ids = ($harbor_pubs_options['postids'] == '1') ? true : false;
			$show_volume = ($harbor_pubs_options['volnum'] == '1') ? true : false;

			?>

			<div class='wrap'>

				<?php
				$plugin_data = get_plugin_data(__FILE__, 0, 0);
				echo '<h1>' . $plugin_data['Title'] . ' - Version ' . $plugin_data['Version'] .'</h1>';
				?>

				<h3><?php echo $pub_name; ?></h3>

				<form method="post" enctype="multipart/form-data" name="<?php echo $post_id; ?>">
					<input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
					<input type="hidden" name="pub_id" value="<?php echo $pub_id; ?>">
					<div id="poststuff">
						<div id="post-body" class="metabox-holder columns-2">
							<div id="post-body-content">
								<div id="titlediv">
									<div id="titlewrap">
										<label class="screen-reader-text" id="title-prompt-text" for="title">Enter title here</label>
										<input type="text" name="post_title" size="30" value="<?php echo stripslashes($post->post_title); ?>" id="title" spellcheck="true" autocomplete="off" />
									</div><!-- /#titlewrap -->
								</div><!-- /#titlediv -->

								<script type='text/javascript'>
									jQuery(document).ready(function(){

										jQuery('.toc_add_post').click(function(){
											var new_post_id = jQuery(this).data('id');
											var new_post_title = jQuery(this).data('title');
											var current_posts = JSON.parse(jQuery('.toc_posts').val());
											var current_titles = JSON.parse(jQuery('.toc_titles').val());
											current_posts.push(String(new_post_id));
											current_titles.push(new_post_title);
											jQuery('.toc_posts').val(JSON.stringify(current_posts));
											jQuery('.toc_titles').val(JSON.stringify(current_titles));
											toc_render_table();
											jQuery('tr.row_' + new_post_id).slideUp();
										})

										jQuery('.toc_add_subhead').click(function(){
											var new_subhead = jQuery('.toc_subhead_text').val();
											if (!isNaN(new_subhead)) {
												alert('Subheadings may not contain all numbers. They must \ncontain at least one non-numeric character.\n\nPlease revise your subhead.');
												return;
											}
											var current_posts = JSON.parse(jQuery('.toc_posts').val());
											var current_titles = JSON.parse(jQuery('.toc_titles').val());
											current_titles.push("subheadline");
											current_posts.push(new_subhead);
											jQuery('.toc_posts').val(JSON.stringify(current_posts));
											jQuery('.toc_titles').val(JSON.stringify(current_titles));
											toc_render_table();
											jQuery('tr.row_' + new_post_id).slideUp();
										})

										jQuery(document).on('change', '.toc_chk', function(e){
											var action = jQuery(this).data('action');
											var post_id = jQuery(this).data('id');
											var type = jQuery(this).prop('type');
											if (type == 'radio') {
												jQuery('.toc_' + action).val(post_id);
											} else {
												store_chk_values(action, post_id);
											}
										})

										function store_chk_values(action, post_id) {
											var x = (jQuery('.toc_' + action).val()).split(',');
											x = x.filter(Number);
											var old_index = x.indexOf(String(post_id));
											if (old_index != -1) {
												x.splice(old_index, 1);
											} else {
												x.push(String(post_id));
											}
											jQuery('.toc_' + action).val(x.join());
										}


										jQuery(document).on('click', '.toc_action', function(e){
											var action = jQuery(this).data('action');
											var post_id = jQuery(this).data('id');
											switch (action) {
												case 'up': toc_move_up(post_id); break;
												case 'down': toc_move_down(post_id); break;
												case 'delete': toc_delete(post_id); break;
											}
											toc_render_table();
										})

										function toc_move_up(post_id) {
											var current_posts = JSON.parse(jQuery('.toc_posts').val());
											var current_titles = JSON.parse(jQuery('.toc_titles').val());
											var old_index = current_posts.indexOf(String(post_id));
											var new_index = old_index - 1;
											current_posts.move(old_index, new_index);
											current_titles.move(old_index, new_index);
											jQuery('.toc_posts').val(JSON.stringify(current_posts));
											jQuery('.toc_titles').val(JSON.stringify(current_titles));
										}

										function toc_move_down(post_id) {
											var current_posts = JSON.parse(jQuery('.toc_posts').val());
											var current_titles = JSON.parse(jQuery('.toc_titles').val());
											var old_index = current_posts.indexOf(String(post_id));
											var new_index = old_index + 1;
											current_posts.move(old_index, new_index);
											current_titles.move(old_index, new_index);
											jQuery('.toc_posts').val(JSON.stringify(current_posts));
											jQuery('.toc_titles').val(JSON.stringify(current_titles));
										}

										function toc_delete(post_id) {
											var current_posts = JSON.parse(jQuery('.toc_posts').val());
											var current_titles = JSON.parse(jQuery('.toc_titles').val());
											var old_index = current_posts.indexOf(String(post_id));
											if (old_index != -1) {
												current_posts.splice(old_index, 1);
												current_titles.splice(old_index, 1);
												jQuery('.toc_posts').val(JSON.stringify(current_posts));
												jQuery('.toc_titles').val(JSON.stringify(current_titles));
											}
											jQuery('tr.row_' + post_id).slideDown();
										}

										function toc_render_table() {
											jQuery('#toc_table > tbody').empty();
											var current_posts = JSON.parse(jQuery('.toc_posts').val());
											var current_titles = JSON.parse(jQuery('.toc_titles').val());
											var output = '';
											for (i = 0; i < current_posts.length; i++) {
												var id = current_posts[i];
												var title = current_titles[i];
												var last = (i == (current_posts.length - 1)) ? true : false;
												output += toc_build_table_row(i, id, title, last);
											}
											jQuery('#toc_table > tbody').html(output);
										}

										function toc_build_table_row(i, id, title, last) {
											var featured = (jQuery('.toc_featured').val()).split(',');
											var highlighted = (jQuery('.toc_highlighted').val()).split(',');
											var sponsored = (jQuery('.toc_sponsored').val()).split(',');
											htmlString = '<tr>';
											htmlString += '<td>';
											if (i > 0) {
												htmlString += '<div class="button toc_action" data-action="up" data-id="' + id + '">&#9650;</div>';
											} else {
											}
											htmlString += '</td><td>';
											if (!last) {
												htmlString += '<div class="button toc_action" data-action="down" data-id="' + id + '">&#9660;</div>';
											}
											htmlString += '</td>';
											htmlString += '<td><div class="button toc_action" data-action="delete" data-id="' + id + '">&#10008;</div></td>';
											if (title != "subheadline") {
												<?php if ($show_post_ids) { ?>
												htmlString += '<td align="center">' + id + '</td>';
												<?php } ?>
												htmlString += '<td>' + title + '</td>';

												htmlString += '<td align="center"><input type="<?php echo $featured_input; ?>" class="toc_chk" data-action="featured" data-id="' + id + '" name="featured[]" value="' + i + '"';
												if (featured.indexOf(id) > -1) { htmlString += ' checked'; }
												htmlString += '></td>';

												htmlString += '<td align="center"><input type="checkbox" class="toc_chk" data-action="highlighted" data-id="' + id + '" name="highlighted[]" value="' + i + '"';
												if (highlighted.indexOf(id) > -1) { htmlString += ' checked'; }
												htmlString += '></td>';

												<?php if ($show_sponsored) {?>
												htmlString += '<td align="center"><input type="checkbox" class="toc_chk" data-action="sponsored" data-id="' + id + '" name="sponsored[]" value="' + i + '"';
												if (sponsored.indexOf(id) > -1) { htmlString += ' checked'; }
												htmlString += '></td>';
												<?php } ?>
												htmlString += '</tr>';
											} else {
												<?php if ($show_post_ids) { ?>
												htmlString += '<td align="center">&nbsp;</td>';
												<?php } ?>
												htmlString += '<td colspan=3 style="border-bottom: 1px solid #ddd;"><b style="font-size: 108%;">' + id + '</b></td></tr>';
											}
											return htmlString;
										}

										Array.prototype.move = function (old_index, new_index) {
											while (old_index < 0) { old_index += this.length; }
											while (new_index < 0) { new_index += this.length; }
											if (new_index >= this.length) {
												var k = new_index - this.length;
												while ((k--) + 1) {
													this.push(undefined);
												}
											}
											this.splice(new_index, 0, this.splice(old_index, 1)[0]);
										};
									})
								</script>

								<?php

								$posts = array();
								$titles = array();
								$pubs_content_headers = array(
									'featured' 		=> 'Featured',
									'highlighted' 	=> 'Highlighted',
									'sponsored'		=> 'Sponsored'
								);
								$pubs_content_headers = apply_filters('harbor_pubs_content_headers', $pubs_content_headers);

								echo '<h3>Articles In This Issue</h3><ul>';
								echo '<table id="toc_table" style="width: 96%; margin: 0 2%">';
								echo '<thead><tr>';
								echo '<td width=12% align="center" colspan=3>Actions</td>';
								if ($show_post_ids) {
									echo '<td width=8% align="center">Post ID</td>';
								}
								echo '<td>Title</td>';
								echo '<td width=10% align="center">' . $pubs_content_headers['featured'] . '</td>';
								echo '<td width=10% align="center">' . $pubs_content_headers['highlighted'] . '</td>';
								if ($show_sponsored) {
									echo '<td width=10% align="center">' . $pubs_content_headers['sponsored'] . '</td>';
								}
								echo '</tr></thead>';
								echo '<tbody>';
								$i = 0;
								if ($toc_articles) {
									for ($i = 0; $i < count($toc_articles); $i++) {
										$this_postid = (!empty($toc_articles[$i])) ? $toc_articles[$i] : '';
										$this_title = (ctype_digit($toc_articles[$i])) ? get_the_title($toc_articles[$i]) : 'subheadline';
										$this_featured = (in_array($this_postid, $toc_featured)) ? 'checked' : '';
										$this_highlighted = (in_array($this_postid, $toc_highlighted)) ? 'checked' : '';
										$this_sponsored = (in_array($this_postid, $toc_sponsored)) ? 'checked' : '';

										$posts[] = $this_postid;
										$titles[] = $this_title;

										echo '<tr>';
										if (count($toc_articles) > 1) {
											echo '<td>';
											if ($i > 0) {
												echo '<div class="button toc_action" data-action="up" data-id="'.$this_postid.'">&#9650;</div>';
											}
											echo '</td><td>';
											if ($i < (count($toc_articles) - 1)) {
												echo '<div class="button toc_action" data-action="down" data-id="'.$this_postid.'">&#9660;</div>';
											}
											echo '</td>';
										} else {
											echo '<td></td><td></td>';
										}
										echo '<td><div class="button toc_action" data-action="delete" data-id="'.$this_postid.'">&#10008;</div></td>';
										if ($this_title != 'subheadline') {
											if ($show_post_ids) {
												echo '<td align="center">'.$this_postid.'</td>';
											}
											echo '<td style="padding-left: 5px;">'.$this_title.'</td>';
											echo '<td align="center"><input type="'.$featured_input.'" class="toc_chk" data-action="featured" data-id="'.$this_postid.'" name="featured[]" value="'.$i.'" '.$this_featured.'></td>';
											echo '<td align="center"><input type="checkbox" class="toc_chk" data-action="highlighted" data-id="'.$this_postid.'" name="highlighted[]" value="'.$i.'" '.$this_highlighted.'></td>';
											if ($show_sponsored) {
												echo '<td align="center"><input type="checkbox" class="toc_chk" data-action="sponsored" data-id="'.$this_postid.'" name="sponsored[]" value="'.$i.'" '.$this_sponsored.'></td>';
											}
										} else {
											if ($show_post_ids) {
												echo '<td>&nbsp;</td>';
											}
											echo '<td colspan=3 style="border-bottom: 1px solid #ddd; padding-left: 5px;"><b style="font-size: 108%;">'.$this_postid.'</b></td>';

										}
										echo '</tr>';
									}
								} else {
									echo "<tr><td colspan=99 style='padding: 20px; text-align: center;'><b>There are currently no articles in this table of contents.</b><br/><br/>Select articles from the 'Choose Articles' list below.<br/><br/>If no articles are shown below, revisit your posts individually<br/>and select the appropriate publication from the Publications<br/> meta-box in the right column.</td></tr>";
								}
								echo '</tbody></table>';

									$toc_featured_val = ($toc_featured ? implode(',', $toc_featured) : '' );
								$toc_highlighted_val = ($toc_highlighted ? implode(',', $toc_highlighted) : '' );
								$toc_sponsered_val = ($toc_sponsored ? implode(',', $toc_sponsored) : '' );

								echo "<input type='hidden' name='toc_i' class='toc_i' value='".$i."'>";
								echo "<input type='hidden' style='width: 100%;' name='toc_posts' class='toc_posts' value='".json_encode($posts)."'>";
								echo "<input type='hidden' style='width: 100%;' name='toc_titles' class='toc_titles' value='".json_encode($titles)."'>";
								echo "<input type='hidden' name='toc_featured' class='toc_featured' value='".$toc_featured_val."'>";
								echo "<input type='hidden' name='toc_highlighted' class='toc_highlighted' value='".$toc_highlighted_val."'>";
								echo "<input type='hidden' name='toc_sponsored' class='toc_sponsored' value='".$toc_sponsered_val."'>";

								echo '<h3>Choose Articles</h3>';

								$exclude = array();

								$sql = $wpdb->prepare("SELECT meta_value FROM wp_postmeta WHERE (meta_key = 'toc_articles') AND NOT (meta_value = '') AND (post_id IN (SELECT r.object_id FROM wp_term_relationships r JOIN  wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.term_id = %d));",$pub_id);
								$all_articles = $wpdb->get_results($sql, ARRAY_A);
								foreach ($all_articles as $a) {
									$posts = unserialize($a['meta_value']);
									foreach ($posts as $p) {
										$p = (int)$p;
										if ($p > 0) { $exclude[] = $p; }
									}
								}
								if (empty($exclude)) { $exclude = array(0);}
								if (empty($pub_id)) { $pub_id = "0"; }
								$sql = "SELECT p.ID, p.post_title, p.post_date, post_status FROM wp_posts p
									JOIN wp_term_relationships r ON p.ID = r.object_id
									JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.term_id = ".$pub_id."
									WHERE (p.post_type = 'post')
									AND (p.post_status IN ('publish', 'draft', 'pending', 'future'))
									AND NOT (p.ID IN (".implode(',', $exclude)."))
									ORDER BY p.post_date DESC
									LIMIT 20";

								$articles = $wpdb->get_results($sql, ARRAY_A);
								if ($articles) {
									echo '<style>.toc_articles td { font-size: 92%; }</style>';
									echo '<table class="toc_articles" style="width: 96%; margin: 0 2%;">';
									foreach ($articles as $a) {
										$post_id_text = ($show_post_ids) ? $a['ID'] : '';
										echo '<tr class="row_'.$a['ID'].'">';
										echo '<td width="10%"><div class="button toc_add_post" style="margin: 3px 5px; width: 92%; text-align: center;" data-id="'.$a['ID'].'" data-title="'.$a['post_title'].'">Add Post '.$post_id_text.'</div></td>';
										echo '<td style="padding-left: 5px;">'.$a['post_title'];
										if ($a['post_status'] != 'publish') { echo '&nbsp; &nbsp;<i>('.$a['post_status'].')</i>'; }
										echo '</td>';
										echo '<td width="10%" align=right><nobr>Posted '.$a['post_date'].'</nobr></td>';
										echo '</tr>';
									}
									echo '</table>';
								}

								echo '<h3>Add Subheads</h3>';
								echo '<table class="toc_subhead" style="width: 96%; margin: 0 2%;">';
								echo '<tr>';
								echo '<td width="10%"><div class="button toc_add_subhead" style="margin: 3px 5px; width: 92%; text-align: center;">Add This</div></td>';
								echo '<td><input type="text" name="toc_subhead_text" class="toc_subhead_text" style="width: 100%;"></td>';
								echo '</tr>';
								echo '</table>';

								echo '<br/>';
								echo '<hr/>';

								echo '<h3>Issue Description</h3>';
								echo '<div style="width: 95%; margin: 0 2% 0 3%;">';
								echo '<textarea name="post_content" style="width: 100%; height: 120px;">';
								echo stripslashes($post->post_content);
								echo '</textarea>';
								echo '</div>';

								?>
							</div><!-- /#post-body-content -->
							<div id="postbox-container-1" class="postbox-container">
								<div id="side-sortables" class="meta-box-sortables">

									<div id="submitdiv" class="postbox " >
										<div class="handlediv" title="Click to toggle"><br /></div>
										<h3 class='hndle'><span>Publish</span></h3>
										<div class="inside">
											<div id="submitpost" class="submitbox">
												<div id="minor-publishing">
													<div style="display:none;">
														<p class="submit"><input type="submit" name="save" id="save" class="button" value="Save"  /></p>
													</div>
													<div id="misc-publishing-actions">

														<?php
														$statuses = array('draft', 'publish');
														$status_options = "<select name='post_status' style='width: 180px'>";
														foreach ($statuses as $s) {
															if ($post->post_status == $s) {
																$status_options .= "<option value='".$s."' selected>".ucwords($s)."</option>";
															} else {
																$status_options .= "<option value='".$s."'>".ucwords($s)."</option>";
															}
														}
														$status_options .= "</select>";
														?>

														<div class="misc-pub-section misc-pub-post-status">
															<label for="post_status">Status:</label>
															<span id="post-status-display"><?php echo $status_options; ?></span>
														</div><!-- /.misc-pub-section -->

													</div><!-- /.misc-publishing-actions -->
													<div class="clear"></div>
												</div><!-- /#minor-publishing -->
												<div id="major-publishing-actions">
													<div id="publishing-action">
														<span class="spinner"></span>
														<input name="original_publish" type="hidden" id="original_publish" value="Publish" />
														<input type="submit" name="save" id="save" class="button button-primary button-large" value="Save"  />
													</div><!-- /#publishing-action -->
													<div class="clear"></div>
												</div><!-- /#major-publishing-actions -->
											</div><!-- /#submitpost -->
										</div><!-- /.inside -->
									</div><!-- /#submitdiv -->

									<div id="publicationdiv" class="postbox">

										<?php $dir = plugins_url(); ?>
										<script type="text/javascript" src="<?php echo $dir ?>/harbor-publication-manager/datepicker/date.js"></script>
										<script type="text/javascript" src="<?php echo $dir ?>/harbor-publication-manager/datepicker/jquery.datePicker.js"></script>
										<script type="text/javascript">
											jQuery(function() {
												jQuery('.harbor-datepicker').datePicker({startDate:'2000/01/01'});
											});
										</script>
										<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $dir ?>/harbor-publication-manager/datepicker/datePicker.css">

										<?php

										$toc_issue_date   = ( isset( $meta['toc_issue_date'][0] ) ) ? $meta['toc_issue_date'][0] : '';
										$toc_issue_volume = ( isset( $meta['toc_issue_volume'][0] ) ) ? $meta['toc_issue_volume'][0] : '';
										$toc_issue_number = ( isset( $meta['toc_issue_number'][0] ) ) ? $meta['toc_issue_number'][0] : '';

										$one_week_ago = date('Y-m-d', strtotime('-7 days'));

										$this_datepicker = ($toc_issue_date) ? date('Y-m-d', strtotime($toc_issue_date)) : $one_week_ago;

										?>

										<div class="handlediv" title="Click to toggle"><br /></div>
										<h3 class='hndle'><span>Issue Information</span></h3>
										<div class="inside">
											<?php
											echo '<label>Publication<br/>';
											wp_dropdown_categories('show_option_none=Select Publication&hide_empty=0&orderby=name&echo=1&taxonomy=publication&selected='.$pub_id.'&name=publication&class=widefat');
											echo '</label><br/>';
											echo "<input type='hidden' name='old_pub_id' value='".$pub_id."'>";

											echo '<label>Issue Date<br/><div class="date-box"><input type="text" name="toc_issue_date" value="'.$this_datepicker.'" class="widefat harbor-datepicker"/></div></label><br/>';
											if ($show_volume) {
												echo '<label>Issue Volume<br/><input type="text" name="toc_issue_volume" value="'.$toc_issue_volume.'" class="widefat"/></label><br/>';
												echo '<label>Issue Number<br/><input type="text" name="toc_issue_number" value="'.$toc_issue_number.'" class="widefat"/></label><br/>';
											}
											?>
										</div><!-- /.inside -->
									</div><!-- /#publicationdiv -->

									<div id="filediv" class="postbox ">
										<div class="handlediv" title="Click to toggle"><br></div>
										<h3 class="hndle"><span>Issue PDF</span></h3>
										<div class="inside">
											<?php
											if ($toc_issue_pdf) {
												echo '<p>Current File:<br/>';
												echo '<a href="' . DOWNLOAD_PATH . $toc_issue_pdf . '" target="_blank"><b>' . mb_strimwidth($toc_issue_pdf, 0, 35, '...') . '</b></a></p>';
												echo '<p><label><input type="checkbox" value="1" name="unlink_file" id="unlink_file"> ';
												echo 'Delete File</label></p>';
												echo '<hr/>';
											}
											?>

											<input type="file" id="toc_issue_file" name="toc_issue_file" value="" size="25" style="width: 100%;" />
										</div>
									</div><!-- /#filediv -->

									<div id="mastheaddiv" class="postbox">

										<script type='text/javascript'>
											jQuery(document).ready(function(){

												jQuery(document).on('click', '.toc_masthead_add', function(e){
													var title = jQuery('.toc_masthead_title').val();
													var body = jQuery('.toc_masthead_body').val();
													body = body.replace(/\\r\\n/g, '<br>').replace(/\n/g,'<br>').replace(/<br\/>/g,'<br>');
													var key = parseInt(jQuery('.toc_masthead_key').val());
													var new_vals = { 'title' : title, 'body' : body };
													var masthead = JSON.parse(jQuery('.toc_masthead').val());
													if (isNaN(key)) {
														masthead.push(new_vals);
													} else {
														masthead.splice(key, 1, new_vals);
													}
													jQuery('.toc_masthead').val(JSON.stringify(masthead));
													toc_render_masthead();
													jQuery('.toc_masthead_title').val('');
													jQuery('.toc_masthead_body').val('');
													jQuery('.toc_masthead_key').val('');
													jQuery('.toc_masthead_add').val('Add');
												})

												jQuery(document).on('click', '.mh_action', function(e){
													var action = jQuery(this).data('action');
													var key = jQuery(this).data('id');
													var masthead = JSON.parse(jQuery('.toc_masthead').val());
													switch (action) {
														case 'delete': mh_delete(masthead, key); break;
														case 'edit': mh_edit(masthead, key); break;
														default: mh_move(masthead, key, action); break;
													}
													toc_render_masthead();
												})

												jQuery(document).on('click', '.toc_masthead_edit', function(e){
													var key = jQuery(this).data('id');
													var masthead = JSON.parse(jQuery('.toc_masthead').val());
													var title = masthead[key]['title'];
													var body = masthead[key]['body'];
													body = body.replace(/<br>/g, "\n");
													jQuery('.toc_masthead_title').val(title);
													jQuery('.toc_masthead_body').val(body);
													jQuery('.toc_masthead_key').val(key);
													jQuery('.toc_masthead_add').val('Update');
												})

												function mh_move(masthead, key, action) {
													var new_index = (action == 'up') ? key - 1 : key + 1;
													masthead.move(key, new_index);
													jQuery('.toc_masthead').val(JSON.stringify(masthead));
												}

												function mh_delete(masthead, key) {
													if (key != -1) {
														masthead.splice(key, 1);
														jQuery('.toc_masthead').val(JSON.stringify(masthead));
													}
												}


												function toc_render_masthead() {
													jQuery('.toc_masthead_display').empty();
													var masthead = JSON.parse(jQuery('.toc_masthead').val());
													var output = '';
													for (i = 0; i < masthead.length; i++) {
														var title = masthead[i]['title'];
														var body = masthead[i]['body'];
														var last = (i < (masthead.length-1)) ? false : true;
														output += toc_build_masthead(i, title, body, last);
													}
													jQuery('.toc_masthead_title').val('');
													jQuery('.toc_masthead_body').val('');
													jQuery('.toc_masthead_key').val('');
													jQuery('.toc_masthead_add').val('Add');
													jQuery('.toc_masthead_display').html(output);
												}

												function toc_build_masthead(key, title, body, last) {
													htmlString = '<div class="masthead-block">';
													htmlString += ' <div class="masthead-control">';
													htmlString += '  <div class="tinybutton mh_action" data-action="delete" data-id="' + key + '">&#10008;</div></td>';
													if (!last) {
														htmlString += '  <div class="tinybutton mh_action" data-action="down" data-id="' + key + '">&#9660;</div></td>';
													}
													if (key > 0) {
														htmlString += '  <div class="tinybutton mh_action" data-action="up" data-id="' + key + '">&#9650;</div></td>';
													}
													htmlString += '  <div class="tinybutton toc_masthead_edit" data-id="' + key + '">&nbsp;Edit&nbsp;</div></td>';
													htmlString += ' </div>';
													htmlString += ' <div class="masthead-' + key + '" style="border-bottom: 1px solid #ddd; padding: 8px 0;">';
													htmlString += '  <b>' + title + '</b><br/>' + body;
													htmlString += ' </div>';
													htmlString += '</div>';
													return htmlString;
												}
											})
										</script>

										<div class="handlediv" title="Click to toggle"><br /></div>
										<h3 class='hndle'><span>Masthead</span></h3>
										<div class="inside">
											<style>
												div.masthead-control { height: 22px; clear: both; width: 100%; background-color: #ddd; padding: 0; border-top: 1px solid #bbb;  }
												div.masthead-control div { float: right; height: 19px; min-width: 19px; text-align: center; margin: 3px 6px 0 0; padding: 0 2px; background-color: #eee; cursor: pointer; color: #999; border-right: 1px solid #eee; }
												div.masthead-control div:hover { background-color: #fff; color: #333; border-right: 1px solid #bbb; }
											</style>
											<div class="toc_masthead_display">
												<?php
												$toc_masthead = (empty($meta['toc_masthead'][0])) ? array() : unserialize($meta['toc_masthead'][0]) ;
												$x = count($toc_masthead);
												foreach ($toc_masthead as $key => $m) {
													echo '<div class="masthead-block">';
													echo ' <div class="masthead-control">';
													echo '  <div class="tinybutton mh_action" data-action="delete" data-id="' . $key . '" title="Delete">&#10008;</div>';
													if ($key < ($x-1)) {
														echo '  <div class="tinybutton mh_action" data-action="down" data-id="' . $key . '" title="Move Down">&#9660;</div>';
													}
													if ($key > 0) {
														echo '  <div class="tinybutton mh_action" data-action="up" data-id="' . $key . '" title="Move Up">&#9650;</div>';
													}
													echo '  <div class="tinybutton toc_masthead_edit" data-id="' . $key . '" title="Edit">&nbsp;Edit&nbsp;</div>';
													echo ' </div>';
													echo ' <div class="masthead-' . $key . '" style="border-bottom: 1px solid #ddd; padding: 8px 0;">';
													echo '  <b>' . $m['title'] . '</b><br/>' . $m['body'];
													echo ' </div>';
													echo '</div>';
												}
												?>
											</div>
											<div>
												<label>Title<br/><input type="text" name="toc_masthead_title" class="toc_masthead_title widefat"/></label><br/>
												<label>Body<br/><textarea name="toc_masthead_body" class="toc_masthead_body widefat"/></textarea></label><br/>
												<input type='hidden' name='toc_masthead_key' class='toc_masthead_key' value=''>
												<input type="button" value="Save" class="button-primary toc_masthead_add" style="margin: 10px 0;"><br/>
												<input type='hidden' name='toc_masthead' class='toc_masthead' value='<?php echo json_encode($toc_masthead); ?>'>
											</div>
										</div><!-- /.inside -->
									</div><!-- /#mastheaddiv -->

									<div id="postimagediv" class="postbox ">

										<div class="handlediv" title="Click to toggle"><br></div>
										<h3 class="hndle"><span>Featured Image</span></h3>

										<script type="text/javascript">

											/*
											 * Attaches the image uploader to the input field
											 */

											jQuery(document).ready(function($){

												$('#cpi').each(function() {

													var cpi_image_frame;
													var p = $(this);

													//Choose/upload image
													p.find('.cpi-upload-button').click(function(e) {

														e.preventDefault();

														if ( cpi_image_frame ) {
															cpi_image_frame.open();
															return;
														}

														// Sets the media manager's title and button text
														cpi_image_frame = wp.media.frames.cpi_image_frame = wp.media({
															title: 'Select Featured Image',
															button: { text:  'Use as Featured Image' }
														});

														// Runs when an image is selected
														cpi_image_frame.on('select', function() {

															// Grabs the attachment selection and creates a JSON representation of the model.
															var media_attachment = cpi_image_frame.state().get('selection').first().toJSON();

															var media_id = media_attachment.id;
															var media_thumbnail = media_attachment.sizes.thumbnail.url;

															// Sends the attachment URL to our custom image input field.
															p.find('.cpi-upload-id').val(media_id);
															p.find('.cpi-upload-thumbnail').html('<img src="' + media_thumbnail + '">');

														});

														// Opens the media library frame
														cpi_image_frame.open();
													});

													// Button to unset current image
													p.find('.cpi-upload-clear').click(function(e) {

														e.preventDefault();

														p.find('.cpi-upload-id').val('');
														p.find('.cpi-upload-thumbnail').empty();

													});
												});
											});
										</script>

										<?php
										$thumbnail_id = get_post_thumbnail_id($post_id);
										$url = wp_get_attachment_url($thumbnail_id);
										$upload_link = esc_url( get_upload_iframe_src( 'image', $post_id ) );
										?>

										<input type="hidden" name="remove_thumbnail" id="remove_thumbnail" value="false" />
										<p class="remove_thumbnail_note" style="display: none; padding: 0 12px"></p>

										<div class="inside" id='cpi'>
											<div class="cpi-upload-thumbnail">

												<?php

												if($thumbnail_id) {
													echo wp_get_attachment_image($thumbnail_id);
												}

												?>

											</div>

											<input type="button" class="button cpi-button cpi-upload-button" value="<?php _e( 'Choose Image ', 'cpi-textdomain' )?>" />

											<input type="button" class="button cpi-button cpi-upload-clear" value="&#215;" />

											<input type="hidden" class="cpi-upload-id" name="thumbnail_id" value="<?php echo $thumbnail_id; ?>" />


											<?php //echo _wp_post_thumbnail_html($thumbnail_id, $post_id); ?>
										</div>
									</div><!-- /#postimagediv -->

								</div><!-- /#side-sortables -->
							</div><!-- /#postbox-container-1 -->
						</div><!-- /#post-body -->
					</div><!-- /#poststuff -->
					<?php wp_nonce_field('updateTOC','toc-nonce'); ?>
				</form>

			</div><!-- /.wrap -->
			<?php
		}

		public function save_issue() {

			if (isset($_POST['save'])) {
				if ( isset($_REQUEST['toc-nonce']) && wp_verify_nonce($_REQUEST['toc-nonce'],'updateTOC')) {

					global $wpdb;

					date_default_timezone_set('America/New_York');

					$post_id = $_POST['post_id'];
					$post_title = $_POST['post_title'];
					$post_status = $_POST['post_status'];
					$post_content = $_POST['post_content'];
					$pub_id = $_POST['publication'];
					$toc_issue_date = $_POST['toc_issue_date'];
					$toc_issue_volume = $_POST['toc_issue_volume'];
					$toc_issue_number = $_POST['toc_issue_number'];
					$toc_masthead_json = str_replace('\n', '', $_POST['toc_masthead']);
					$toc_masthead_json = str_replace('\"', '"', $_POST['toc_masthead']);
					$toc_masthead = json_decode($toc_masthead_json, true);

					$remove_thumbnail = ($_POST['remove_thumbnail'] == 'true') ? true : false;

					$publication = get_term_by('id', $pub_id, 'publication');

					$toc_articles = json_decode(str_replace('\"', '"', $_POST['toc_posts']), true);

					$toc_articles = array_filter($toc_articles);
					$toc_articles = array_values($toc_articles);

					$toc_featured = explode(',', $_POST['toc_featured']);
					$toc_highlighted = explode(',', $_POST['toc_highlighted']);
					$toc_sponsored = explode(',', $_POST['toc_sponsored']);

					$toc_featured = array_filter($toc_featured, function($x) use($toc_articles) { return in_array($x, $toc_articles); } );
					$toc_highlighted = array_filter($toc_highlighted, function($x) use($toc_articles) { return in_array($x, $toc_articles); } );
					$toc_sponsored = array_filter($toc_sponsored, function($x) use($toc_articles) { return in_array($x, $toc_articles); } );

					$toc_featured = implode(',', $toc_featured);
					$toc_highlighted = implode(',', $toc_highlighted);
					$toc_sponsored = implode(',', $toc_sponsored);

					$post_slug = $publication->slug . '-' . sanitize_title($post_title);

					if (empty($post_id) || $post_id == 0) {

						$post = array(
							'post_content'		=> $post_content,
							'post_title'		=> $post_title,
							'post_status'		=> $post_status,
							'post_type'			=> 'toc',
							'post_name'			=> $post_slug,
						);

						$post_id = wp_insert_post($post, false);

					} else {

						// Following updates TOC post with wp_update_post() instead of db query
						$post = array(
							'ID'				=> $post_id,
							'post_content'		=> $post_content,
							'post_title'		=> $post_title,
							'post_status'		=> $post_status,
							'post_type'			=> 'toc',
							'post_name'			=> $post_slug,
							'post_date'			=> date('Y-m-d H:i:s'),
							'post_date_gmt'		=> gmdate('Y-m-d H:i:s')
						);
						wp_update_post($post);

					}

					if ($post_id > 0) {

						wp_set_post_terms($post_id, $pub_id, 'publication', false);

						update_post_meta($post_id, 'toc_issue_date', $toc_issue_date);
						update_post_meta($post_id, 'toc_issue_volume', $toc_issue_volume);
						update_post_meta($post_id, 'toc_issue_number', $toc_issue_number);
						update_post_meta($post_id, 'toc_masthead', $toc_masthead);
						update_post_meta($post_id, 'toc_articles', $toc_articles);
						update_post_meta($post_id, 'toc_featured', $toc_featured);
						update_post_meta($post_id, 'toc_highlighted', $toc_highlighted);
						update_post_meta($post_id, 'toc_sponsored', $toc_sponsored);

						if ($remove_thumbnail) {
							delete_post_meta($post_id, '_thumbnail_id');
						}

						if (is_numeric($_POST['thumbnail_id'])) {
							set_post_thumbnail($post_id, $_POST['thumbnail_id']);
						} else {
							delete_post_meta($post_id, '_thumbnail_id');
						}

						if (isset($_POST['unlink_file']) && $_POST['unlink_file'] == 1) {
							$toc_issue_pdf = get_post_meta($post_id, 'toc_issue_pdf', true);
							unlink(DOWNLOAD_PATH . $toc_issue_pdf);
							delete_post_meta($post_id, 'toc_issue_pdf');
						}

						if (!empty($_FILES['toc_issue_file']['name'])) {

							$supported_types = array('application/pdf');

							$arr_file_type = wp_check_filetype(basename($_FILES['toc_issue_file']['name']));
							$uploaded_type = $arr_file_type['type'];

							if (in_array($uploaded_type, $supported_types)) {

								//$dir = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/private/products/';

								$filename = basename($_FILES['toc_issue_file']['name']);

								$sucess = move_uploaded_file($_FILES['toc_issue_file']['tmp_name'], DOWNLOAD_PATH . $filename);

								if ($_FILES['toc_issue_file']['error'] == 0) {
									update_post_meta($post_id, 'toc_issue_pdf', $filename);
								}
							}

						}

						clean_post_cache($post_id);

					}

					header("Location: /wp-admin/admin.php?page=harbor_pubs_editor&id=".$post_id);
					exit();
				}
			}
		}

	// ------------------------------------------------------------------------
	// OPTIONS

		public function manage_options() {

			$plugin_data = get_plugin_data(__FILE__, 0, 0);

			if (isset($_REQUEST['_wpnonce'])) {
				if (wp_verify_nonce($_REQUEST['_wpnonce'],'harbor_pubs_options')) {
					update_option('harbor_pubs', $_POST['harbor_pubs']);
				}
			}
			?>

			<script type='text/javascript'>
				jQuery(document).ready(function(){
					jQuery('.channels').change(function(){
						var channels = new Array();
						jQuery.each(jQuery("input[name='channels[]']:checked"), function(){
							var chan_code = jQuery(this).val();
							var chan_name = jQuery(this).data('name');
							chan = { code : chan_code, name : chan_name };
							channels.push(chan);
						});
						channels = JSON.stringify(channels);
						jQuery('#channels_hidden').val(channels);
					})
				})
			</script>

			<?php

			$harbor_pubs_options = get_option('harbor_pubs');

			echo '<div class="wrap">';
			echo '<h1>'.__($plugin_data['Title']).' - Version '.__($plugin_data['Version']).'</h1>';
			echo '<h3>'.__('Edit Plugin Options').'</h1>';
			echo '<form action="/wp-admin/admin.php?page=harbor_pubs_options" method="post" id="harbor-pubs-options">';
			echo '<table class="form-table">';

			echo '<tr valign="top">';
			echo '<th scope="row"><label for="harbor_pubs_featured">Featured Articles</label></th>';
			echo '<td>';
			echo '<label><input type="radio" name="harbor_pubs[featured]" value="radio" ';
			if ( isset( $harbor_pubs_options['featured'] ) && $harbor_pubs_options['featured'] == 'radio' ) { echo 'checked'; }
			echo '/>&nbsp;Radio Button (select only one featured article)</label><br/>';
			echo '<label><input type="radio" name="harbor_pubs[featured]" value="checkbox" ';
			if ( isset( $harbor_pubs_options['featured'] ) && $harbor_pubs_options['featured'] == 'checkbox' ) { echo 'checked'; }
			echo '/>&nbsp;Checkboxes (select multiple featured articles)</label><br/>';
			echo '</td>';
			echo '</tr>';

			echo '<tr valign="top">';
			echo '<th scope="row"><label for="harbor_pubs_sponsored">Sponsored Articles</label></th>';
			echo '<td>';
			echo '<label><input type="radio" name="harbor_pubs[sponsored]" value="1" ';
			if ( isset( $harbor_pubs_options['sponsored'] ) && $harbor_pubs_options['sponsored'] == '1' ) { echo 'checked'; }
			echo '/>&nbsp;Show Sponsored Articles Column</label><br/>';
			echo '<label><input type="radio" name="harbor_pubs[sponsored]" value="0" ';
			if ( isset( $harbor_pubs_options['sponsored'] ) && $harbor_pubs_options['sponsored'] == '0' ) { echo 'checked'; }
			echo '/>&nbsp;Hide Sponsored Articles Column</label><br/>';
			echo '</td>';
			echo '</tr>';

			echo '<tr valign="top">';
			echo '<th scope="row"><label for="harbor_pubs_postids">Post IDs</label></th>';
			echo '<td>';
			echo '<label><input type="radio" name="harbor_pubs[postids]" value="1" ';
			if ( isset( $harbor_pubs_options['postids'] ) && $harbor_pubs_options['postids'] == '1' ) { echo 'checked'; }
			echo '/>&nbsp;Show Post IDs on Editing Page</label><br/>';
			echo '<label><input type="radio" name="harbor_pubs[postids]" value="0" ';
			if ( isset( $harbor_pubs_options['postids'] ) && $harbor_pubs_options['postids'] == '0') { echo 'checked'; }
			echo '/>&nbsp;Hide Post IDs on Editing Page</label><br/>';
			echo '</td>';
			echo '</tr>';

			echo '<tr valign="top">';
			echo '<th scope="row"><label for="harbor_pubs_volume">Issue Volume & Number</label></th>';
			echo '<td>';
			echo '<label><input type="radio" name="harbor_pubs[volnum]" value="1" ';
			if ( isset( $harbor_pubs_options['volnum'] ) && $harbor_pubs_options['volnum'] == '1') { echo 'checked'; }
			echo '/>&nbsp;Show Issue Volume & Number</label><br/>';
			echo '<label><input type="radio" name="harbor_pubs[volnum]" value="0" ';
			if ( isset( $harbor_pubs_options['volnum'] ) && $harbor_pubs_options['volnum'] == '0') { echo 'checked'; }
			echo '/>&nbsp;Hide Issue Volume & Number</label><br/>';
			echo '</td>';
			echo '</tr>';

			if ( isset( $harbor_pubs_options['channels'] ) ) {
				$channels = array();
				$channels_json = json_decode( stripslashes( $harbor_pubs_options['channels'] ), true );

				foreach ( $channels_json as $ch ) {
					$channels[ $ch['code'] ] = $ch['name'];
				}

				echo '<tr valign="top">';
				echo '<th scope="row"><label for="harbor_pubs_channels">Available Channels</label></th>';
				echo '<td>';
				echo '<label><input type="checkbox" name="channels[]" class="channels" value="P" data-name="print" ';
				if ( isset( $channels['P'] ) && $channels['P'] ) { echo 'checked'; }
				echo '/>&nbsp;Print</label><br/>';
				echo '<label><input type="checkbox" name="channels[]" class="channels" value="W" data-name="web" ';
				if ( isset( $channels['W'] ) && $channels['W'] ) { echo 'checked'; }
				echo '/>&nbsp;Web</label><br/>';
				echo '<label><input type="checkbox" name="channels[]" class="channels" value="T" data-name="tablet" ';
				if ( isset( $channels['T'] ) && $channels['T'] ) { echo 'checked'; }
				echo '/>&nbsp;Tablet</label><br/>';
				echo '</td>';
				echo '</tr>';

				echo "<input type='hidden' name='harbor_pubs[channels]' id='channels_hidden' value='".stripslashes($harbor_pubs_options['channels'])."'>";

			}

			echo '<tr valign="top">';
			echo '<th scope="row"><label for="harbor_pubs_pub_accordion">Use Publication Accordion</label></th>';
			echo '<td>Coming Soon';
			echo '</td>';
			echo '</tr>';

			echo '<tr valign="top">';
			echo '<th scope="row"><label for="harbor_pubs_annual_accordion">Use Annual Accordion</label></th>';
			echo '<td>Coming Soon';
			echo '</td>';
			echo '</tr>';

			echo '</table>';
			echo '<p class="submit"><input type="submit" name="Submit" class="button button-primary" value="'.__('Update Options &raquo;', 'harbor-prd').'" /></p>';


			wp_nonce_field('harbor_pubs_options');


			echo '</form>';
			echo '</div>';
		}

}

// Instantiate our class
$harborPubs = new harborPubs();

// ----------------------------------------------------------------------------
// HELPER FUNCTIONS

	/**
	 * Returns the latest cover image for a specific publication
	 *
	 * @param int $term_id OR string $pub_id
	 *
	 * Use wp_get_attachment_image to display cover
	 */
	function get_latest_cover($x) {
		global $wpdb;
		$term_id = (is_numeric($x)) ? $x : get_term_id($x) ;
		if ( empty($term_id) ) {
			return;
		}
		$sql = "SELECT m.meta_value FROM wp_posts p JOIN wp_postmeta m ON p.ID = m.post_id AND m.meta_key = '_thumbnail_id' JOIN wp_term_relationships r ON m.post_id = r.object_id JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id JOIN wp_terms t ON x.term_id = t.term_id WHERE p.post_type = 'toc' AND p.post_status = 'publish' AND t.term_id = {$term_id} ORDER BY p.post_date DESC LIMIT 1";
		$cover = $wpdb->get_var($sql);
		if (!$cover) {
			$sql = "SELECT meta_value FROM wp_termmeta WHERE (term_id = {$term_id}) AND (meta_key = 'img_cover')";
			$cover = $wpdb->get_var($sql);
		}
		return $cover;
	}

	/**
	 * Returns the latest post id for a specific publication
	 *
	 * @param int $term_id OR string $pub_id
	 */
	function get_latest_post_id($x) {
		global $wpdb;
		$term_id = (is_numeric($x)) ? $x : get_term_id($x) ;
		$sql = "SELECT p.ID FROM wp_posts p JOIN wp_term_relationships r ON p.ID = r.object_id JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id JOIN wp_terms t ON x.term_id = t.term_id WHERE p.post_type = 'toc' AND p.post_status = 'publish' AND t.term_id = {$term_id} ORDER BY p.post_date DESC LIMIT 1";
		$post_id = $wpdb->get_var($sql);
		return $post_id;
	}

	/**
	 * Returns the term_id for a specific publication, based on the pub_id
	 *
	 * @param string $pub_id
	 */
	function get_term_id($pub_id) {
		global $wpdb;
		$sql = $wpdb->prepare("SELECT term_id FROM wp_termmeta WHERE (meta_key = 'pub_id') AND (meta_value = '%s') ORDER BY meta_id DESC LIMIT 1", $pub_id);
		$term_id = $wpdb->get_var($sql);
		return $term_id;
	}

	/**
	 * Returns the publication and issue title for a specific post
	 *
	 * @param int $post_id
	 */
	function get_pub_info($post_id) {
		global $wpdb;
		$output = false;
		$toc_id = get_toc_id($post_id);
		if (!empty($toc_id)) {
			$sql = "SELECT p.ID AS 'toc_id', p.post_title AS 'issue', p.post_name AS 'slug', t.name AS 'pub', t.slug AS 'pub_slug', t.term_id AS 'term_id', m1.meta_value AS 'prefix', m1.meta_value AS 'pub_id', m2.meta_value AS 'active', m3.meta_value AS 'no_toc', a.name AS 'parent_name', a.slug AS 'parent_slug'
				FROM wp_posts p
				JOIN wp_term_relationships r ON p.ID = r.object_id
				JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.taxonomy = 'publication'
				JOIN wp_terms t ON x.term_id = t.term_id
				JOIN wp_termmeta m1 ON t.term_id = m1.term_id AND m1.meta_key = 'pub_id'
				LEFT JOIN wp_termmeta m2 ON t.term_id = m2.term_id AND m2.meta_key = 'active'
				LEFT JOIN wp_termmeta m3 ON t.term_id = m3.term_id AND m3.meta_key = 'no_toc'
				LEFT JOIN wp_terms a ON a.term_id = x.parent
				WHERE (p.ID = ".$toc_id.");";
		} else {
			$sql = "SELECT '' AS 'toc_id', p.post_title AS 'issue', p.post_name AS 'slug', t.name AS 'pub', t.slug AS 'pub_slug', t.term_id AS 'term_id', m1.meta_value AS 'prefix', m1.meta_value AS 'pub_id', m2.meta_value AS 'active', m3.meta_value AS 'no_toc', a.name AS 'parent_name', a.slug AS 'parent_slug'
				FROM wp_posts p
				JOIN wp_term_relationships r ON p.ID = r.object_id
				JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.taxonomy = 'publication'
				JOIN wp_terms t ON x.term_id = t.term_id
				JOIN wp_termmeta m1 ON t.term_id = m1.term_id AND m1.meta_key = 'pub_id'
				LEFT JOIN wp_termmeta m2 ON t.term_id = m2.term_id AND m2.meta_key = 'active'
				LEFT JOIN wp_termmeta m3 ON t.term_id = m3.term_id AND m3.meta_key = 'no_toc'
				LEFT JOIN wp_terms a ON a.term_id = x.parent
				WHERE (p.ID = ".$post_id.");";
		}
		$output = $wpdb->get_row($sql, ARRAY_A);
		return $output;
	}

	/**
	 * Returns the parent group of the publication for a specific post
	 *
	 * @param int $post_id
	 */
	function get_pub_parent($post_id) {
		global $wpdb;
		$output = false;
		$toc_id = get_toc_id($post_id);
		if ($toc_id) {
			$sql = "SELECT x.parent FROM wp_term_relationships r JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.taxonomy = 'publication' WHERE (r.object_id = ".$toc_id.");";
			$output = $wpdb->get_var($sql);
		}
		return $output;
	}

	/**
	 * Returns the TOC for a specific post
	 *
	 * @param int $post_id
	 */
	function get_toc($post_id) {
		global $wpdb;
		$output = false;
		if (is_numeric($post_id)) {
			$sql = "SELECT post_id, meta_value FROM wp_postmeta WHERE (meta_key = 'toc_articles') AND ((meta_value LIKE '%\"{$post_id}\"%') OR (meta_value LIKE '%i:{$post_id};%'));";  //i:27517;
			$results = $wpdb->get_results($sql);
			foreach ($results as $r) {
				$articles = unserialize($r->meta_value);
				$post_id_string = (string)$post_id;
				if (in_array($post_id_string, $articles, true)) { break; }
			}
			if (is_array($articles)) {
				$output .= '<ul class="toc" data-post-id='.$post_id.'>';
				foreach ($articles as $a){
					if (!is_numeric($a)) {
						$output .= '<li class="toc_subheadline"><h4>'.$a.'</h4></li>';
					} else if ($a == $post_id) {
						$output .= '<li class="toc_current">'.get_the_title($a).'</li>';
					} else {
						$permalink = apply_filters( 'harbor_pubs_toc_link', get_permalink( $a ) );
						$output .= '<li><a href="'.get_permalink($a).'">'.get_the_title($a).'</a></li>';
					}
				}
				$output .= '</ul>';
			}
		}
		return $output;
	}

	/**
	 * Returns the ID of the issue containing this specific post
	 *
	 * @param int $post_id
	 */
	function get_toc_id($post_id) {
		global $wpdb;
		$sql = 'SELECT m.post_id FROM wp_postmeta m JOIN wp_posts p ON p.ID = m.post_id WHERE (m.meta_key = \'toc_articles\') AND ((m.meta_value LIKE \'%"'.$post_id.'"%\') OR (meta_value LIKE \'%i:'.$post_id.';%\')) AND (p.post_type = \'toc\') AND (p.post_status IN (\'publish\',\'draft\')) ORDER BY FIELD (p.post_status,\'publish\',\'draft\') LIMIT 1;';
		$toc_id = $wpdb->get_var($sql);
		return $toc_id;
	}

	function show_prev_next($post_id, $args) {

		$output = false;

		$defaults = array(
			'class_cont'	=> 'pagination-single',
			'class_prev'	=> 'small-6 columns',
			'class_next'	=> 'small-6 columns text-right',
			'label_prev'	=> 'Previous',
			'label_next'	=> 'Next',
			'toc_id'        => false,
		);

		$args = wp_parse_args($args, $defaults);

		$urls = get_prev_next( $post_id, $args['toc_id'] );

		if (empty($urls)) { $args['class_cont'] .= ' prev_next_empty'; }

		if (is_array($urls)) {
			$prev = ($urls['prev']) ? "<a href='".$urls['prev']."'>".$args['label_prev']."</a>" : "";
			$next = ($urls['next']) ? "<a href='".$urls['next']."'>".$args['label_next']."</a>" : "";
			$output = "<div class='".$args['class_cont']."'>";
			$output .= "<div class='".$args['class_prev']."'>".$prev."</div>";
			$output .= "<div class='".$args['class_next']."'>".$next."</div>";
			$output .= "</div>";
		}

		echo $output;
	}

	function get_prev_next( $post_id, $toc_id = false ) {
		global $wpdb;
		$output = array();

		if( $toc_id ){
			$articles = get_post_meta( $toc_id, 'toc_articles', true );
		} else {
			$sql = $wpdb->prepare("SELECT meta_value FROM wp_postmeta WHERE (meta_key = 'toc_articles') AND (meta_value LIKE '%%%d%%')", $post_id);
			$results = $wpdb->get_results($sql);

			foreach ($results as $r) {
				$articles = unserialize($r->meta_value);
				$post_id_string = (string)$post_id;
				if (in_array($post_id_string, $articles, true)) { break; }
			}
		}

		if (is_array($articles)) {
			foreach ($articles as $key => $a) {
				if (!is_numeric($a)) { unset($articles[$key]); }
			}
			$articles = array_values($articles);
			foreach ($articles as $key => $a) {
				if ($a == $post_id) {
					$output['prev'] = ($key-1 > -1) ? get_permalink($articles[$key-1]) : false ;
					$output['next'] = ($key+1 < count($articles)) ? get_permalink($articles[$key+1]) : false ;
					break;
				}
			}

		}

		$output = apply_filters( 'harbor_pubs_get_prev_next', $output );

		return $output;
	}

	function first_article_link($post_id) {
		global $wpdb;
		$sql = $wpdb->prepare("SELECT meta_value FROM wp_postmeta WHERE (meta_key = 'toc_articles') AND (post_id = %d);", $post_id);
		$articles = unserialize($wpdb->get_var($sql));
		if (is_array($articles)) {
			foreach ($articles as $key => $a) {
				if (!is_numeric($a)) { unset($articles[$key]); }
			}
			$articles = array_values($articles);
			$url = get_permalink($articles[0]);
			return $url;
		} else {
			return false;
		}
	}

	// array of featured images for all publications
	function get_latest_covers($prefixes, $urls = false) {
		global $wpdb;
		$covers = array();
		foreach ($prefixes as $p) {
			$sql = "SELECT m.meta_value FROM wp_posts p ";
			if ($urls) {
				$sql .= "JOIN wp_postmeta i ON p.ID = i.post_id AND i.meta_key = '_thumbnail_id' ";
				$sql .= "JOIN wp_postmeta m ON i.meta_value = m.post_id AND m.meta_key = '_wp_attached_file' ";
			} else {
				$sql .= "JOIN wp_postmeta m ON p.ID = m.post_id AND m.meta_key = '_thumbnail_id' ";
			}
			$sql .= "WHERE p.post_type = 'toc' AND p.post_status = 'publish' ORDER BY p.post_date DESC LIMIT 1;";
			$covers[] = $wpdb->get_var($sql);
		}
		return $covers;
	}

	// returns TOC info for most recent issue of this pub
	function get_latest_magazine_issue($prefix) {
		global $wpdb;
		$sql = "SELECT ID, post_title, post_name FROM wp_posts p WHERE p.post_type = 'toc' AND p.post_status = 'publish' ORDER BY p.post_date DESC LIMIT 1;";
		$issue = $wpdb->get_row($sql, ARRAY_A);
		return $issue;
	}

	function get_pub_parent_slug($pub_id) {
		global $wpdb;
		$sql = $wpdb->prepare("SELECT t.slug FROM wp_terms t JOIN wp_term_taxonomy x ON x.parent = t.term_id WHERE (x.term_id = (SELECT m.term_id FROM wp_termmeta m WHERE m.meta_key = 'pub_id' AND m.meta_value = %s));", $pub_id);
		$slug = $wpdb->get_var($sql);
		$slug = (empty($slug)) ? false : $slug;
		return $slug;
	}

	// returns title based on prefix (pub_id)
	// is it a report or a publication? based on prefix (pub_id)
	function is_report($pub_id) {
		global $wpdb;
		$parent = $wpdb->get_var("SELECT parent FROM wp_term_taxonomy WHERE (term_id = (SELECT term_id FROM wp_termmeta WHERE meta_key = 'pub_id' AND meta_value = '".$pub_id."'));");
		if ($parent == '1493') { $report = true; }
		else { $report = false; }
		return $report;
	}

	// deprecated functions
	function get_magazine_slug($pub_id) { return get_pub_slug($pub_id); }
	function get_magazine_array() { return get_pubs(); }
	function get_magazine_prefix($post_id) { return get_pub_id($post_id); }
	function get_magazine_prefixes($exclude) { return get_pub_ids($exclude); }
	function get_magazine_title($pub_id) { return get_pub_title($pub_id); }

	// returns slug for a specific prefix (pub_id)
	function get_pub_slug($input) {
		$slug = $sql = false;
		global $wpdb;
		if (is_numeric($input)) { // term_id
			$sql = $wpdb->prepare("SELECT slug FROM wp_terms WHERE (term_id = %d);", $input);
		} else { // pub_id
			$sql = $wpdb->prepare("SELECT t.slug FROM wp_terms t JOIN wp_termmeta m ON t.term_id = m.term_id AND m.meta_key = 'pub_id' WHERE (m.meta_value = %s);", $input);
		}
		if ($sql) { $slug = $wpdb->get_var($sql); }
		if (!$slug && function_exists('get_composite_products')) {
			$cp = get_composite_products();
			if (is_array($cp)) {
				if (array_key_exists($pub_id, $cp)) {
					$slug = sanitize_title($cp[$pub_id]['title']);
				}
			}
		}
		return $slug;
	}

	// returns array of publication data
	function get_pubs( $include_composites = false, $order_by = 'title' ) {
		$valid_order_by = array( 'pub_id', 'pub_order', 'slug', 'term_id', 'title' );
		if ( ! in_array( $order_by, $valid_order_by ) ) { $order_by = 'title'; }
		global $wpdb;
		$sql = "SELECT m1.meta_value AS prefix, m1.meta_value AS pub_id, m2.meta_value AS active, m3.meta_value AS pub_order, t.slug AS slug, t.name AS title, 'true' AS online, t.term_id, x.description, x.parent, p.slug AS parent_slug
			FROM wp_terms t
			JOIN wp_term_taxonomy x ON t.term_id = x.term_id AND x.taxonomy = 'publication'
			JOIN wp_termmeta m1 ON t.term_id = m1.term_id AND m1.meta_key = 'pub_id'
			LEFT JOIN wp_termmeta m2 ON t.term_id = m2.term_id AND m2.meta_key = 'active'
			LEFT JOIN wp_termmeta m3 ON t.term_id = m3.term_id AND m3.meta_key = 'pub_order'
			LEFT JOIN wp_terms p ON p.term_id = x.parent
			ORDER BY " . $order_by . ";";
		$pubs = $wpdb->get_results($sql, ARRAY_A);

		if ($include_composites && function_exists ('get_composite_products')) {
			$composites = get_composite_products();
			if (is_array($composites)) {
				foreach ($composites as $key => $c) {
					$pubs[] = array(
						'prefix'		=> $key,
						'pub_id'		=> $key,
						'active'		=> 1,
						'slub'			=> sanitize_title_with_dashes($c['title']),
						'title'			=> $c['title'],
						'term_id'		=> false,
						'description'	=> false,
						'parent'		=> false,
						'parent_slug'	=> false,
					);
				}
			}
		}

		return $pubs;
	}

	function get_pub_title($pub_id) {
		global $wpdb;
		$sql = $wpdb->prepare("SELECT term_id FROM wp_termmeta WHERE (meta_key = 'pub_id') AND (meta_value = %s) LIMIT 1", $pub_id);
		$sql = "SELECT name FROM wp_terms WHERE (term_id = (".$sql."));";
		$title = $wpdb->get_var($sql);
		if (!$title && function_exists('get_composite_products')) {
			$cp = get_composite_products();
			if (is_array($cp)) {
				if (array_key_exists($pub_id, $cp)) {
					$title = $cp[$pub_id]['title'];
				}
			}
		}
		return $title;
	}

	// return pub_id for specified post
	function get_pub_id($post_id) {
		$pub_id = '';
		$term = get_the_terms($post_id, 'publication');
		if ( $term && ! is_wp_error($term) ) {
			$pub_id = get_term_meta($term[0]->term_id, 'pub_id', true);
		}
		return $pub_id;
	}

	// return true if no_toc is false for this publication
	function single_post_pub($id, $type = 'pub_id') {
		global $wpdb;
		$no_toc = false;
		switch ($type) {
			case 'post_id':
				$term = get_the_terms($id, 'publication');
				$no_toc = get_term_meta($term[0]->term_id, 'no_toc', true);
				break;
			case 'term_id':
				$no_toc = get_term_meta($id, 'no_toc', true);
				break;
			case 'pub_id':
				$sql = $wpdb->prepare("SELECT term_id FROM wp_termmeta WHERE (meta_key = 'pub_id') AND (meta_value = %s) LIMIT 1;", $id);
				return $sql;
				$term_id = $wpdb->get_var($sql);
				$no_toc = get_term_meta($term_id, 'no_toc', true);
				break;
		}
		$no_toc = ($no_toc) ? true : false;
		return $no_toc;
	}

	// returns array of pub_ids (nee prefixes)
	function get_pub_ids($exclude = false) {
		global $wpdb;
		$sql = "SELECT m.meta_value AS pub_id FROM wp_termmeta m JOIN wp_term_taxonomy x ON m.term_id = x.term_id AND m.meta_key = 'pub_id' WHERE (x.taxonomy = 'publication')";
		if (is_array($exclude)) {
			$sql .= " AND (x.term_id NOT IN ('".implode("','", $exclude)."')) AND (m.meta_value NOT IN ('".implode("','", $exclude)."'))";
		}
		$pub_ids = $wpdb->get_col($sql);
		return $pub_ids;
	}

	/**
	 * Returns the latest post id for a specific publication
	 *
	 * @param int $term_id
	 *
	 * Use wp_get_attachment_image to display cover
	 */
	function get_newest_issue($term_id) {
		if (is_numeric($term_id)) {
			$single_post_pub = get_term_meta($term_id, 'no_toc', true);
			global $wpdb;
			if ($single_post_pub) { // use articles instead of TOC posts
				$sql = "SELECT p.ID, p.post_date AS 'toc_issue_date' FROM wp_posts p JOIN wp_term_relationships r ON p.ID = r.object_id JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id JOIN wp_terms t ON x.term_id = t.term_id WHERE p.post_status = 'publish' AND t.term_id = {$term_id} ORDER BY p.post_date DESC LIMIT 1";
			} else {
				$sql = "SELECT p.ID, m.meta_value AS 'toc_issue_date' FROM wp_posts p JOIN wp_postmeta m ON p.ID = m.post_id AND m.meta_key = 'toc_issue_date' JOIN wp_term_relationships r ON p.ID = r.object_id JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id JOIN wp_terms t ON x.term_id = t.term_id WHERE p.post_type = 'toc' AND p.post_status = 'publish' AND t.term_id = {$term_id} ORDER BY m.meta_value DESC LIMIT 1";
			}
			$results = $wpdb->get_row($sql, ARRAY_A);
			return $results;
		} else {
			return false;
		}
	}

	function get_channels() {

		$harbor_pubs_options = get_option('harbor_pubs');

		$channels = array();
		if ( isset( $harbor_pubs_options['channels'] ) ) {
			$channels_json = json_decode( stripslashes( $harbor_pubs_options['channels'] ), true );
			if ( is_array( $channels_json ) ){
				foreach( $channels_json as $ch ) { $channels[$ch['code']] = $ch['name']; }
			}
		}
		if ( empty( $channels ) ) { $channels = false; }

		return $channels;
	}