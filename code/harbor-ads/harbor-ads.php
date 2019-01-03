<?php
/**
 * Plugin Name: Harbor Text Ads
 * Plugin URI: http://www.kwyjibo.com/
 * Description: A Plugin that shows text ads inside a post.
 * Version: 0.5
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

/**
 * harborTextAds is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */

/**
 * --------------------------------------------------------------
 * Register the Text Ad post type
 * --------------------------------------------------------------
 */

function register_text_ad_post_type() {
	$labels = array(
		'name'					=> __('Text Ads'),
		'singular_name'			=> __('Text Ad'),
		'add_new'				=> __('Add New Text Ad'),
		'add_new_item'			=> __('Add New Text Ad'),
		'edit_item'				=> __('Edit Text Ad'),
		'new_item'				=> __('New Text Ad'),
		'view_item'				=> __('View Text Ad'),
		'search_items'			=> __('Search Text Ads'),
		'not_found'				=> __('No Text Ads Found'),
		'not_found_in_trash'	=> __('No Text Ads Found in Trash')
		);
	$args = array(
		'labels'				=> $labels,
		'description'			=> 'Text Ad post types are used by the Harbor Text Ad Manager, Harbor Automated Email Manager, andin other places throughout the Harbor System.',
		'exclude_from_search'	=> true,
		'publicly_queryable'	=> false,
		'show_in_nav_menus'		=> false,
		'show_ui'				=> true,
		'show_in_menu'			=> 'edit.php?post_type=uc',
		'hierarchical'			=> false,
		'supports'				=> array('title', 'editor', 'thumbnail'),
		'rewrite'				=> array('slug' => 'text_ad')
		);
	register_post_type('text_ad', $args);
}


add_action('init',  'register_text_ad_post_type');

/**
 * --------------------------------------------------------------
 * Admin columns for Text Ad post type
 * --------------------------------------------------------------
 */

function text_ad_columns($columns){
	$columns = array(
		'cb'			=> '<input type=\'checkbox\' />',
		'title'			=> 'Text Ad Title',
		'author'		=> 'Author',
		'date'			=> 'Date'
	);
		return $columns;
}
add_filter('manage_edit-text_ad_columns', 'text_ad_columns');

/**
 * --------------------------------------------------------------
 * Display Meta box on Text Ad options page
 * --------------------------------------------------------------
 */

function text_ad_metabox() {
	//add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
	add_meta_box('text_ad_meta', 'Text Ad Button and Link Cofiguration', 'text_ad_meta_options', 'text_ad', 'normal', 'high');
}
add_action('add_meta_boxes', 'text_ad_metabox');

function text_ad_meta_options() {
	global $post;
	echo '<input type="hidden" name="text_ad_nonce" id="text_ad_nonce" value="'.wp_create_nonce(plugin_basename(__FILE__)).'"/>';

	$text_ad_link = get_post_meta($post->ID, 'text_ad_link', true);
	$text_ad_button = get_post_meta($post->ID, 'text_ad_button', true);
	$text_ad_config = get_post_meta($post->ID, 'text_ad_config', true);

	$checked[button] = ($text_ad_config) ? false : 'checked';
	$checked[$text_ad_config] = ($text_ad_config) ? 'checked' : false;

	echo '<div style="width: 100%; position: relative; overflow: hidden;">';

	echo '<div style="width: 48%; float: right;">';
	echo '<b>Text Ad Tips</b><br/>';
	echo '<ul>';
	echo '<li>Always use absolute links. Text Ads may be used in the email builder, and links must work from off site.</li>';
	echo "<li>Always add an Ad Link URL even if your link is in the body of your text and you don't plan to have a button or Read More style link. The Ad Link URL is also applied to the featured image (when displayed).</li>";
	echo '<li>Keep any formatting used within the body of the ad very simple.</li>';
	echo '<li>Add a featured image.</li>';
	echo '</ul>';
	echo '</div>';

	echo '<div style="width: 50%; float: left;">';
	echo '<label><b>Ad Link URL</b><br/><input type="text" name="text_ad_link" value="'.$text_ad_link.'" class="widefat" /></label>';
	echo '<label><b>Button/Link Text</b><br/><input type="text" name="text_ad_button" value="'.$text_ad_button.'" class="widefat" /></label>';
	echo '<b>Button Display Style</b><br/><label><input type="radio" name="text_ad_config" value="button" '.$checked[button].' />&nbsp;Button<br/></label><label><input type="radio" name="text_ad_config" value="readmore" '.$checked[text].' />&nbsp;Text Link (ie: Read More)<br/></label><label><input type="radio" name="text_ad_config" value="none" '.$checked[none].' />&nbsp;None (link in body text)<br/></label>';
	echo '</div>';
	echo '</div>';

}

/**
 * --------------------------------------------------------------
 * Save Meta box data
 * --------------------------------------------------------------
 */

function save_text_ad_meta($post_id, $post) {

	if (!wp_verify_nonce( $_POST['text_ad_nonce'], plugin_basename(__FILE__))) { return $post->ID; }
	if (!current_user_can('edit_post', $post->ID)) { return $post->ID; }

	$text_ad_meta['text_ad_link'] = $_POST['text_ad_link'];
	$text_ad_meta['text_ad_button'] = $_POST['text_ad_button'];
	$text_ad_meta['text_ad_config'] = $_POST['text_ad_config'];
	
	foreach ($text_ad_meta as $key => $value) {
		if ($post->post_type == 'revision') { return; }
		if(!$value) {
			delete_post_meta($post->ID, $key);
		} else {
			update_post_meta($post->ID, $key, $value);
		}
	}

}

add_action('save_post', 'save_text_ad_meta', 1, 2); // save the custom fields


class harborTextAds{

	public function setActions(){
		add_shortcode('text_ad', array($this,'text_ad'));
	}

	public function dashboard() {
		add_menu_page('Harbor Text Ads', 'Harbor Text Ads', 2, __FILE__, array($this,'options'));
	}

	function __construct() {
		$this->setActions();

		register_activation_hook(__FILE__, array( $this, 'activate'));

		add_action('admin_menu', array($this, 'dashboard'));

	}

	public function activate() {
		global $wpdb;
		$sql = "CREATE TABLE IF NOT EXISTS wp_harbor_text_ads (
			id int NOT NULL auto_increment,
			type tinyint NOT NULL,
			term_id int NOT NULL,
			ads varchar(50) NOT NULL,
			PRIMARY KEY  (id)
			);";
		if ($wpdb->query($sql) === false) {
			echo 'There was an error creating the new table.';
		}

	}


	private function _get_h($parents) {

		global $wpdb;

		$order = ($parents == '0') ? 'ASC' : 'DESC' ;

		$sql = "SELECT t.term_id, t.name, x.parent FROM wp_term_taxonomy x INNER JOIN wp_terms t ON x.term_id = t.term_id AND x.taxonomy LIKE 'category' WHERE (x.parent IN (".$parents.")) AND (t.name NOT LIKE '%zuber%') AND NOT (t.name = 'Text Ads') ORDER BY x.parent, t.name ".$order.";";

		$results = $wpdb->get_results($sql, 'ARRAY_A');

		return $results;

	}

	public function options() {

		global $wpdb;
		$error = $message = false;

		//echo '<pre>'.print_r($_POST, true).'</pre>';

		if (!empty($_POST) && check_admin_referer('updateTextAdOptions','text-ad-option-nonce')) {
			$both = $_POST['ads'];
			$has_global_default = array(false, false);
			foreach ($both as $type => $ads) {
				foreach ($ads as $key => $a) {
					$has_global_default[$type] = ($key == 0) ? true : $has_global_default[$type];
					if ($a && is_int($key)) {
						$test = explode(',', $a);
						$final = array();
						foreach ($test as $t) {
							$t = intval($t);
							if ($t) { $final[] = $t; }
						}
						$values[] = "(".$type.", ".$key.",'".implode(',',$final)."')";
					}
				}
			}
			
			if ($values && $has_global_default[0]) {
				$sql = "DELETE FROM wp_harbor_text_ads";
				$deletequery = $wpdb->query($sql);
				$sql = "INSERT INTO wp_harbor_text_ads (type, term_id, ads) VALUES ".implode(', ',$values).";";
				$insertquery = $wpdb->query($sql);
				if (false === $insertquery || false === $deletequery) {
					$error = 'There was an error inserting your new Text Ad values.';
				} else {
					$message = 'Your Text Ad values have been updated.';
				}
			} else {
				$error = 'No changes were submitted.';
			}
		}


		$stored = array();
		$sql = "SELECT type, term_id, ads FROM wp_harbor_text_ads;";
		$results = $wpdb->get_results($sql, 'ARRAY_A');
		foreach ($results as $r) {
			$stored[$r[type]][$r[term_id]] = $r[ads];
		}

		?>

		<style>
			div.tab-panel { position: relative; display: block; width: 57%; float: left; margin-top: 50px; border-radius: 0 10px 10px 10px; border: 1px solid #aaa; padding: 10px; }
			ul.tabs { position: absolute; top: -52px; left: -1px; }
			ul.tabs li { position: absolute; display: block; height: 23px; top: 0; border: 1px solid #aaa; border-bottom: none; background-color: #f1f1f1; border-radius: 10px 10px 0 0; padding: 10px 10px 5px; width: 260px; text-align: center; font-size: 120%; font-weight: bold; color: #0074a2; z-index: 999; }
			ul.tabs li:first-child { left: 278px; }
			ul.tabs li.inactive { top: -4px; height: 26px; background-color: #e1e1e1; color: #999; cursor: pointer; z-index: 998; }
			
			table.on-posts { display: block; }
			table.on-index { display: none; }

			form { width: 100%; }
			div.help { display: block; width: 40%; float: right;}
			table.text-ads { border-collapse: collapse; width: 98%; margin: 0 auto; }
			table.text-ads input { text-align: center; }
			table.text-ads tr.header { background-color: #666; color: #fff; font-weight: bold; font-size: 110%; text-align: center; }

			/*table.text-ads tr.hide { display: none; }*/
			
			table.text-ads td { text-align: center; padding: 2px; position: relative; }
			table.text-ads td:first-child { text-align: left; width: 90%; }
			table.text-ads tr.header td { padding: 7px 10px; }
			table.text-ads td.level-0 { font-weight: bold; color: #0074a2; font-size: 110%; }
			table.text-ads td.level-1 { padding-left: 20px; font-weight: bold; }
			table.text-ads td.level-2 { padding-left: 30px; }
			table.text-ads td.level-3 { padding-left: 40px; font-size: 90%; }
			table.text-ads td.level-4 { padding-left: 50px; font-size: 80%; }
			table.text-ads td.level-5 { padding-left: 60px; font-size: 70%; }
			table.text-ads td.note { font-size: 80%; color: #666; text-align: center; padding: 0 30px; }
			table.text-ads table.selector { position: absolute; top: 29px; left: 3px; width: 170px; height: auto; color: #fff; background-color: #0074a2; z-index: 999; box-sizing: border-box; border-collapse: collapse; box-shadow: 2px 2px 2px rgba(0,0,0,0.3); cursor: pointer; }
			table.text-ads table.selector tr:hover { background-color: rgba(255,255,255,0.3); }
			table.text-ads table.selector td { padding: 4px 10px; text-align: left; }
			table.text-ads table.selector td:first-child { font-weight: bold; width: 20%; }
			table.text-ads .deletor { position: absolute; color: #0074a2; right: 5px; top: 7px; z-index: 999; cursor: pointer; }
			table.text-ads .deletor:hover { color: #e00; }

			table.ad-posts { border-collapse: collapse; width: 98%; margin: 0 auto; }
			table.ad-posts tr.header { background-color: #666; color: #fff; font-weight: bold; font-size: 110%; }
			table.ad-posts td { padding: 7px 10px; }
			table.ad-posts td:first-child { width: 60px; text-align: center; }
			table.ad-posts td.id { font-size: 110%; font-weight: bold; color: #0074a2; }
			table.ad-posts td.title { font-weight: bold; }
			table.ad-posts td.content { cursor: pointer; }

			@media (max-width: 1200px) {
				div.tab-panel { display: block; width: 98%; float: left;}
				div.help { display: block; width: 98%; float: left;}
			}
		</style>

		<?php

		echo "<div class='wrap'>";
		echo "<h2>Harbor Ads</h2>";
		if ($message) { echo "<div class='updated'><p>".$message."</p></div>"; }
		if ($error) { echo "<div class='error'><p>".$error."</p></div>"; }
		
		echo "<hr/>";

		$level = 0;
		$ids = 0;
		$all = array();
		$global_default = array($stored[0][0], $stored[1][0]);

		while ($next = $this->_get_h($ids)) {
			
			$ids = array_map(function($x){ return $x['term_id']; }, $next);
			$ids = implode(',',$ids);

			foreach($next as $n) {
				$n['level'] = $level;
				$parent = intval($n[parent]);
				if ($parent > 0) {
					$search_ids = array_map(function($x){ return $x['term_id']; }, $all);
					$search_key = array_search($n[parent], $search_ids);
					$start = array_slice($all, 0, ($search_key+1));
					$start[] = $n;
					$end = array_slice($all, ($search_key+1));
					$all = array_merge($start,$end);
				} else if (is_int($parent)) {
					$all[] = $n;
				}
			}

			$level++;

		}

		echo "<div class='tab-panel'>";

		echo "<ul class='tabs'>";
		echo "<li class='inactive' data-tab='on-index'>Ads on Category Index Pages</li>";
		echo "<li data-tab='on-posts'>Ads in Page & Post Content</li>";
		echo "</ul>";
		
		echo "<form method='post'>";
	
		$labels = array();
		$labels['global_default'] = array('Global Default for Page & Post Ads', 'Global Default for Index Page Ads');
		$labels['table_class'] = array('on-posts', 'on-index');

		$types = array(0, 1);

		foreach ($types as $t) {

			echo "<table class='text-ads ".$labels['table_class'][$t]."'>";
				if ($t == 0) {
					echo "<tr>";
					echo "<td class='level-0'>".$labels['global_default'][$t]."</td>";
					echo "<td><input type='text' name='ads[".$t."][0]' id='ads[".$t."][0]' value='".$stored[$t][0]."' class='ads' autocomplete='off'></td>";
					echo "<td class='note'>REQUIRED</td>";
					echo "</tr>";
				} else {
					echo "<tr><td colspan=3><b>There are no default values for Category Index Page Text Ads.</b><br/>When displayed within Category Index Pages, Text Ads will be supressed if no Text Ad Post ID is specified for that Category.</td></tr>";
				}
				echo "<tr><td colspan=3><hr/></td></tr>";
				echo "<tr class='header'>";
				echo "<td>Category</td>";
				echo "<td>Text Ad Post ID</td>";
				if ($t == 0) {
					echo "<td>Default If Blank</td>";
				}
				echo "</tr>";
				echo "<tr><td colspan=3></td></tr>";

				foreach($all as $key => $a) {
					$default[$t][0] = $global_default[$t];
					$default[$t][$a[level]+1] = ($stored[$t][$a[term_id]]) ? $stored[$t][$a[term_id]] : $default[$t][$a[level]];
					$actual = ($stored[$t][$a[term_id]]) ? $stored[$t][$a[term_id]] : $default[$t][$a[level]+1];

					echo "<tr class='hide hide-".$a[parent]."'>";
					echo "<td class='level-".$a[level]."'>".$a[name]." <!-- <span class='expander' data-expandid=".$a[term_id].">open</span> --></td>";
					echo "<td><input type='text' name='ads[".$t."][".$a[term_id]."]' id='ads[".$t."][".$a[term_id]."]' class='ads' value='".$stored[$t][$a[term_id]]."' autocomplete='off'></td>";
					if ($t == 0) {
						echo "<td><input type='text' readonly value='".$actual."'></td>";
					}
					echo "</tr>";
				}

				echo "<tr><td colspan=3><hr/></td></tr>";
				echo "<tr><td colspan=3><input type='submit' value='Update Ads'></td></tr>";
			echo "</table>";

		}

		wp_nonce_field('updateTextAdOptions','text-ad-option-nonce');

		echo "</form>";

		echo '</div>'; //tab-panel

		echo '</div>'; //wrap

			//$posts = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_content FROM wp_posts p JOIN wp_term_relationships r ON p.ID = r.object_id JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id JOIN wp_terms t ON t.term_id = x.term_id AND t.name = 'Text Ads' WHERE (p.post_status = 'publish');", 'ARRAY_A');

			//$posts = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_content FROM wp_posts p JOIN wp_term_relationships r ON p.ID = r.object_id JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id JOIN wp_terms t ON t.term_id = x.term_id AND t.name = 'Text Ads';", 'ARRAY_A');

			$posts = $wpdb->get_results("SELECT ID, post_title, post_content FROM wp_posts WHERE (post_type = 'text_ad') AND (post_status = 'publish');", 'ARRAY_A');

			if ($posts) {
				$help .= "<h3>Available Ads</h3>";
				$help .= "<table class='ad-posts'>";
				$help .= "<tr class='header'><td>Ad ID</td><td>Title</td><td>Content</td></tr>";
				foreach ($posts as $p) {
					$content = strip_tags($p[post_content]);
					$help .= "<tr>";
					$help .= "<td class='id'>".$p[ID]."</td>";
					$help .= "<td class='title'>".$p[post_title]."</td>";
					$help .= "<td class='content' title='".$content."'>".substr($content,0,38)."...</td>";
					$help .= "</tr>";
					
					$selector .= "<tr data-adid='".$p[ID]."'>";
					$selector .= "<td class='id'>".$p[ID]."</td>";
					$selector .= "<td class='title'>".substr($p[post_title],0,15)."</td>";
					$selector .= "</tr>";

				}
				$help .= "</table>";
				$help .= "<hr/>";
			}

			?>

			<script>
				jQuery(document).ready(function(){
					jQuery('.ads').focus(function(){
						jQuery('.selector').remove();
						jQuery('.deletor').remove();
						var thisInput = jQuery(this);
						var selector = "<table class='selector'><?php echo $selector; ?></table>"
						var deletor = "<span class='deletor dashicons dashicons-no' title='clear all'></span>";
						jQuery(selector).insertAfter(this);
						jQuery(deletor).insertAfter(this);
						jQuery('.deletor').hover(function(){
							thisInput.animate({'color':'#fff'},500);
						}, function(){
							thisInput.animate({'color':'#000'},500);
						})
						jQuery('.selector tr').click(function(){
							var adid = jQuery(this).data('adid')
							thisInput.val(adid);
							jQuery('.selector, .deletor').fadeOut();
						})
						jQuery('.deletor').click(function(){
							thisInput.val('');
							jQuery('.selector, .deletor').fadeOut();
						})
					})
					jQuery('.ads').blur(function(){
						jQuery('.selector, .deletor').fadeOut();
					})
					jQuery('ul.tabs li.inactive').live('click', function(){
						jQuery('ul.tabs li').addClass('inactive');
						jQuery(this).removeClass('inactive');
						var activate = 'table.'+jQuery(this).data('tab');
						//alert(activate);
						jQuery('table.text-ads').hide();
						jQuery(activate).show();
					})
					/*jQuery('tr.hide-0').show();
					jQuery('.expander').click(function(){
						var expandid = jQuery(this).data('expandid');
						jQuery('tr.hide-'+expandid).toggle();
					})*/
				})
			</script>

			<?php

			$help .= "<h3>Using Text Ads</h3>";
			$help .= "<p>[text_ad]</p>";
			$help .= "<p>Placing the [text_ad] shortcode into any Page or Post will display the designated ad in that spot. If no ad is designated for a specific category, the display will default to the ad chosen for the parent category. If the parent has no ad, the display will default to the parent of the parent, and so forth, until the Global Default ad is displayed. The form allows you to change the ad for any category, and also shows the default ad for each category.</p>";
			$help .= "<p>Click on Post ID field to reveal a drop-down allowing you to select from the available Text Ads. Clicking on one of the listed ads will populate the box, clicking anywhere else on the page will close the drop-down. Click on the X to clear the value in the selected field.</p>";
			$help .= "<h4>Multiple Ads</h4>";
			$help .= "<p>Ads may be designated as a single Post ID (1824), or as a comma-delimited set of Post IDs (23,954,2262). When a list of Post IDs is used, the plugin  will cycle through the ads if multiple shortcodes are used within a single Page or Post. For example, if three ads are stored here, and four shortcodes are placed within a post, the plugin will show the first ad, the second ad, the third ad, and then the first ad again, respectively. The ads will always be displayed in the order listed, therefore Pages and Posts with a single shortcode will only ever use the first ad listed here.</p>";
			$help .= "<p>The drop-down selector does not support multiple ads. Multiple Ad lists should be typed in manually.</p>";
			$help .= "<h4>Using Shortcode Arguments in Pages & Posts</h4>";
			$help .= "<p>If the page or Post in which you insert your shortcode is assigned to multiple Categories, the plugin will display the ad associated with the Category with the lowest ID number. If this will not work in your situation, you can modify the ad that is displayed by inserting an argument in your shortcode.</p>";
			$help .= "<p>[text_ad use_post='7672']</p>";
			$help .= "<p>The 'use_post' argument allows you to insert a specific text ad, via the Post ID. The 'use_post' argument is, however, inflexible and primarily only for use in special circumstances.</p>";
			$help .= "<p>[text_ad use_category='23']</p>";
			$help .= "<p>The 'use_category' argument allows the display of ads assigned to a specific Wordpress category, expecially useful when a page or post is assigned to multiple categories and the default ad display is unsatisfactory. The major benefit of using this argument is that the shortcode will respect changes made to that category in the plugin settings.</p>";
			$help .= "<p>Note that the arguments above are mutually exclusive, with priority going to the more specific 'use_post' argument if both are inserted.</p>";
			//$help .= "<p></p>";
			$help .= "<hr/>";


		echo "<div class='help'>".$help."</div>";

	}

	//Text Ads for insertion into posts
	function text_ad($attr, $content = '') {

		static $count = 0;

		global $wpdb;

		// test for default
		$default = $wpdb->get_var("SELECT ads FROM wp_harbor_text_ads WHERE term_id = 0;");

		if ($default) { // table exists, and there is a default ad... continue

			$attr = shortcode_atts(array(
				'use_category'	=> 'unset',
				'use_post'		=> 'unset'
				), $attr);

			$term_id = $attr['use_category'];
			$ads = $attr['use_post'];

			if ($ads != 'unset') { // editor has given us a specific ad. move on.

				

			} else if ($term_id != 'unset') { // editor has given us a term_id, almost as good. get the post_id.

				$term_id = intval($term_id);

				$sql = $wpdb->prepare("SELECT ads FROM wp_harbor_text_ads WHERE (term_id = %d) AND (type = 0);", $term_id);

				$ads = $wpdb->get_var($sql);

			} else { // no term, no post, need to figure it out ourselves.

				$host = &get_post($attr['post_id']);
				$post_id = intval($host->ID);

				$sql = $wpdb->prepare("SELECT x.term_id, x.parent, a.ads FROM wp_term_relationships r JOIN wp_term_taxonomy x ON r.term_taxonomy_id = x.term_taxonomy_id AND x.taxonomy = 'category' LEFT JOIN wp_harbor_text_ads a ON x.term_id = a.term_id AND a.type = 0 WHERE r.object_id = %d ORDER BY x.term_id ASC LIMIT 1;", $post_id);

				$result = $wpdb->get_row($sql, 'ARRAY_A');

				if (!$ads = $result[ads]) {
					while (!$ads) {
						$parent = $result[parent];
						if ($parent > 0) {
							$sql = $wpdb->prepare("SELECT x.parent, a.ads FROM wp_term_taxonomy x LEFT JOIN wp_harbor_text_ads a ON x.term_id = a.term_id AND a.type = 0 WHERE x.term_id = %d ORDER BY x.term_id ASC LIMIT 1", $parent);
						} else {
							$sql = "SELECT ads FROM wp_harbor_text_ads WHERE (term_id = 0) AND (type = 0) LIMIT 1;";
						}
						$result = $wpdb->get_row($sql, 'ARRAY_A');
						$ads = $result[ads];
					}
				}

				$ads = $result[ads];

			}

			$ad_array = explode(',',$ads);
			$total = count($ad_array);

			$ad = &get_post(intval($ad_array[$count % $total]));

			$post_content = $ad->post_content;

			$image = get_the_post_thumbnail($ad->ID, 'medium');

			$meta = get_post_custom($ad->ID);

			//echo '<pre>'.print_r($meta, true).'</pre>';

			switch ($meta[text_ad_config][0]) {
				case 'button':
					$link = '<a class="button" href="'.$meta[text_ad_link][0].'">'.$meta[text_ad_button][0].'</a>';
					break;
				case 'readmore':
					$txt = ($meta[text_ad_button][0]) ? $meta[text_ad_button][0] : 'Read More →';
					$link = '<p><a href="'.$meta[text_ad_link][0].'">'.$txt.'</a></p>';
					break;
				default:
					$link = '';
					break;
			}


			$count++;

			$style = "<style>
			table.inline-text-ad {
				border-left: none;
				border-right: none;
				}

			.inline-text-ad h1 {
				font-size: 18px !important;
				font-weight: bold !important;
				}

			.inline-text-ad p {
				font-size: 1.0rem;
				}
			</style>";

			if ($image) {
				return $style.'<table class="inline-text-ad"><tr><td valign=middle>'.$image.'</td><td valign=middle>'.$post_content.$link.'</td></tr></table>';
			} else {
				return $style.'<div class="inline-text-ad">'.$post_content.$link.'</div>';
			}

		} else {

			return false;

		}
	
	}

}

function index_text_ad($cat_id) {
	global $wpdb;

	static $count = 0;

	$sql = $wpdb->prepare("SELECT ads FROM wp_harbor_text_ads WHERE (term_id = %d) AND (type = 1) LIMIT 1;", $cat_id);
	$ads = $wpdb->get_var($sql);

	if (!$ads) {
		return false;
	} else {
		$ad_array = explode(',',$ads);
		$total = count($ad_array);

		$ad = &get_post(intval($ad_array[$count % $total]));

		$post_content = $ad->post_content;

		$count++;

		return '<div class="index-text-ad">'.$post_content.'</div>';
	}
}

// Instantiate our class
$harborTextAds = new harborTextAds();
?>
