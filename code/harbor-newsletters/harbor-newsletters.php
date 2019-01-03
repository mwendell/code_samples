<?php
/**
 * Plugin Name: Harbor Newsletters
 * Plugin URI: http://www.kwyjibo.com
 * Description: Create and launch automated newsletter campaigns based on site content
 * Version: 0.5
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

class harborAutoNewsManager {

	function __construct(){
		register_activation_hook( __FILE__, array( $this, 'activate'));
		add_action('admin_menu', array($this, 'admin_menu'));
	}

	public function admin_menu() {
		add_options_page('Harbor Automated Newsletter Manager', 'Newsletter Automator', 8, basename(__FILE__), array(&$this, 'options'));
	}

	public function activate() {
		global $wpdb;
		$sql = "CREATE TABLE IF NOT EXISTS wp_harbor_auto_newsletter (
			id int NOT NULL auto_increment,
			field_name varchar(50) NOT NULL,
			ads varchar(2000) NOT NULL,
			PRIMARY KEY  (id)
			);";
		if ($wpdb->query($sql) === false) {
			echo 'There was an error creating the wp_harbor_auto_newsletter table.';
		}
		$sql = "CREATE TABLE IF NOT EXISTS wp_harbor_auto_newsletter_titles (
			id int NOT NULL auto_increment,
			field_name varchar(50) NOT NULL,
			newsletter_title(1000) NOT NULL,
			PRIMARY KEY  (id)
			);";
		if ($wpdb->query($sql) === false) {
			echo 'There was an error creating the wp_harbor_auto_newsletter_titles table.';
		}
	}

	public function options() {
		global $wpdb;
		
		$hex_color = array();
		$featured_articles = array();
		$company_articles = array();
		$image_url = get_site_url();

		$settings = get_option('harbor_autonewsmanager');

		$wc_settings = get_option('whatcounts-framework');
		$fields = $wc_settings['fields'];
		$fields = new ArrayObject($fields);
		$fields->ksort();

		$hex_color = ($settings['hex_color']) ? $settings['hex_color'] : $_POST['hex_color'];
		$featured_articles = ($settings['featured_articles']) ? $settings['featured_articles'] : $_POST['featured_articles'];
		$company_articles = ($settings['company_articles']) ? $settings['company_articles'] : $_POST['company_articles'];
		$image_url = ($settings['image_url']) ? $settings['image_url'] : $image_url;

		if (!empty($_POST) && check_admin_referer('updateNewsOptions','news-option-nonce')) {

			$new_settings = array(
				'hex_color'			=> $_POST['hex_color'],
				'featured_articles'	=> $_POST['featured_articles'],
				'company_articles'	=> $_POST['company_articles'],
				'image_url'			=> $_POST['image_url']
			);

			if (get_option('harbor_autonewsmanager') !== false) {
				update_option('harbor_autonewsmanager',$new_settings);
			} else {
				add_option('harbor_autonewsmanager',$new_settings,null,'no');
			}
			
			$settings = get_option('harbor_autonewsmanager');

			$hex_color = ($settings['hex_color']) ? $settings['hex_color'] : $_POST['hex_color'];
			$featured_articles = ($settings['featured_articles']) ? $settings['featured_articles'] : $_POST['featured_articles'];
			$company_articles = ($settings['company_articles']) ? $settings['company_articles'] : $_POST['company_articles'];
			$image_url = ($image_url) ? $image_url : $_POST['image_url'];

			echo "<div class='updated'>";
			echo "<p>Newsletter Manager Settings Updated</p>";
			echo "</div>";

		}

		$directory = dirname(dirname(dirname(dirname(__FILE__))));
		$files = array();
		$files[3] = scandir($directory.'/newsletters/am/');
		$files[1] = scandir($directory.'/newsletters/pm/');
		$files[2] = scandir($directory.'/newsletters/weekend/');

		?>

		<style>
			table { border-collapse: collapse; }
			table input.hex_color, table input.article_count { width: 120px; text-align: center; }
			table tr.header { background-color: #666; color: #fff; font-weight: bold; font-size: 110%; text-align: center; }
			table tr.header td { padding: 7px 10px; }
			table tr td { text-align: center; }
			table tr td:first-child { text-align: left; }
			table td:first-child { padding-right: 30px; }
			table td.level-1 { padding-left: 10px; font-weight: bold; color: #0074a2; font-size: 110%; }
			table td.level-2 { padding-left: 15px; font-weight: bold; }
			table td.level-3 { padding-left: 20px; }
			table td.level-4 { padding-left: 25px; font-size: 90%; }
			table td.level-5 { padding-left: 30px; font-size: 80%; }
			table td.level-6 { padding-left: 35px; font-size: 70%; }
			div.swatch { margin: auto; height: 25px; width: 25px; outline: 1px solid #ddd; background-color: #fff; }
			table td.ad_count { width: 40px; text-align: center; }
			table td hr { width: 100%; }
			#editor { position: fixed; top: 40px; left: 50%; margin-left: -335px; width: 380px; height: auto; background-color: #fff; padding: 0 17px 17px 17px; box-shadow: 10px 10px 10px rgba(0,0,0,0.3); display: none; z-index: 999; }
			#scrim { position: fixed; top: 0; bottom: 0; left: 0; right: 0; background-color: rgba(0,0,20,0.5); display: none; }
			#editor table td { text-align: center; }
			#editor h3 { color: #fff; font-weight: bold; background-color: #1e8cbe; padding: 17px 17px 10px 17px; margin: -17px -17px 10px -17px; }
		</style>

		<div class="wrap">

		<div id="scrim"></div>
		<form id='editor'>
			<h3 style='margin-top: 0;'>Edit Ads</h3>
			<table>
				<tr>
					<td></td>
					<td>Text Ad</td>
					<td>Open X</td>
					<td>Text Ad Post ID or Open X Zone ID</td>
				</tr>
			<?php for ($x = 0; $x < 15; $x++) {?>
				<tr>
					<td style='text-align: right;'><?php echo $x+1; ?>.</td>
					<td><input type='radio' name='src_<?php echo $x; ?>' id='src_<?php echo $x; ?>_textad' value='textad'></td>
					<td><input type='radio' name='src_<?php echo $x; ?>' id='src_<?php echo $x; ?>_openx' value='openx'></td>
					<td><input type='text' name='ad_<?php echo $x; ?>' id='ad_<?php echo $x; ?>' value='' style='width: 230px'></td>
					<input type='hidden' name='url_<?php echo $x; ?>' id='url_<?php echo $x; ?>' value=''>
				</tr>
			<?php } ?>
			</table><br/>
			<div style='width: 100%;'>
			<input type='submit' class='button-primary' value='Submit Changes' style='float: right;'>
			<input type='button' class='button close-editor' value='Close'>
			<input type='hidden' class='field_name' value=''>
			</div>
		</form>

		<?php $plugin_data = get_plugin_data(__FILE__, 0, 0); ?>
		<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>

			<form method='post' class='settings'>
			<table>
			<tr><td colspan=9><hr/></td></tr>
			<tr>
			<td class='level-1'>Image Source URL</td>
			<td colspan=8 align='left'><input type='text' class='image_url' name='image_url' value='<?php echo $image_url; ?>' style='float: left; width: 350px;'></td>
			</tr>
			<tr><td colspan=9><hr/></td></tr>
			<tr class='header'>
				<td valign='bottom'>Topic</td>
				<td valign='bottom'>Hex<br/>Color Value</td>
				<td valign='bottom'></td>
				<td valign='bottom'>Number of<br/>Feature<br/>Articles</td>
				<td valign='bottom'>Number of<br/>Company<br/>Articles</td>
				<td valign='bottom'>Maximum<br/>Ad<br/>Slots</td>
				<td valign='bottom'>Number<br/>of Ads<br/>Assigned</td>
				<td valign='bottom'></td>
				<td valign='bottom'>View</td>
			</tr>

			<?php
			$ad_count = array();
			$results = $wpdb->get_results('SELECT field_name, ads FROM wp_harbor_auto_newsletter ORDER BY field_name;', ARRAY_A);
			foreach ($results as $r) {
				$i = 0;
				$ads = unserialize($r[ads]);
				foreach ($ads[ad] as $a) {
					if ($a) { $i++; }
				}
				$ad_count[$r[field_name]] = $i;
			}


			echo "<tr><td colspan=9><hr/></td></tr>";

			$deez = array('default', 'daily_1', 'daily_2', 'daily_3', 'daily_4', 'daily_5');
			$label = array('Default Values', 'Daily (Monday)', 'Daily (Tuesday)', 'Daily (Wednesday)', 'Daily (Thursday)', 'Daily (Friday)');

			foreach ($deez as $key => $d) {

				$ad_slots = ($featured_articles[$d] + $company_articles[$d]) - 1;
				$ad_slots = ($ad_slots < 1) ? '' : $ad_slots;
				echo "<tr>";
				$note = ($key) ? "" :  "<br><small style='font-weight: normal; color: #999;'>* will use defaults" ;
				echo "<td class='level-1'>".$label[$key].$note."</td>";
				echo "<td><input type='text' class='hex_color' name='hex_color[".$d."]' value='".$hex_color[$d]."'></td>";
				echo "<td><div class='swatch' style='background-color: #".$hex_color[$d]."'></div></td>";
				echo "<td><input type='text' class='article_count' name='featured_articles[".$d."]' value='".$featured_articles[$d]."'></td>";
				if ($key) {
					echo "<td><input type='hidden' name='company_articles[".$d."]' value='0'></td>";
				} else {
					echo "<td><input type='text' class='article_count' name='company_articles[".$d."]' value='".$company_articles[$d]."'></td>";
				}
				echo "<td class='ad_count'>".$ad_slots."</td>";
				echo "<td class='ad_count'><b>".$ad_count[$d]."</b></td>";
				echo "<td><input type='button' class='button edit_ads' data-name='".$d."' data-label='".$label[$key]."' value='Edit Ads'></td>";
				echo "<td></td>";
				echo "</tr>";

				if (!$key) {
					echo "<tr><td colspan=9><hr/></td></tr>";
				}

			}

			
			echo "<tr><td colspan=9><hr/></td></tr>";

			foreach ($fields as $f) {
				$primary = end(explode('_', $f[name]));
				$depth = intval(count(explode('_', $f[name])));
				$dir = "am/";
				if ($depth == 1) { $dir = "pm/"; }
				if ($depth == 2) { $dir = "weekend/"; }
				$ad_slots = ($featured_articles[$f[name]] + $company_articles[$f[name]]) - 1;
				$ad_slots = ($ad_slots < 1) ? '' : $ad_slots;
				$ct = ($ad_count[$f[name]]) ? '<b>'.$ad_count[$f[name]].'</b>' : "<small style='color: #999;'>*</small>";
				echo "<tr>";
				echo "<td class='level-".$depth."'>".$f[label]."</td>";
				echo "<td><input type='text' class='hex_color' name='hex_color[".$f[name]."]' value='".$hex_color[$f[name]]."'></td>";
				echo "<td><div class='swatch' style='background-color: #".$hex_color[$f[name]]."'></div></td>";
				echo "<td><input type='text' class='article_count' name='featured_articles[".$f[name]."]' value='".$featured_articles[$f[name]]."'></td>";
				echo "<td><input type='text' class='article_count' name='company_articles[".$f[name]."]' value='".$company_articles[$f[name]]."'></td>";
				echo "<td class='ad_count'>".$ad_slots."</td>";
				echo "<td class='ad_count'>".$ct."</td>";
				echo "<td><input type='button' class='button edit_ads' data-name='".$f[name]."' data-label='".$f[label]."' value='Edit Ads'></td>";
				if (in_array($f[name].'.html', $files[$depth])) {
					echo "<td><input type='button' class='button' value='Most Recent' onClick=location.href='/newsletters/".$dir.$f[name].".html'></td>";
				} else {
					echo "<td><small style='color: #999;'>NOT SENT</small></td>";
				}
				echo "</tr>";
			}
			
			echo "<tr><td colspan=9><hr/></td></tr>";

			?>
			</table>
			<?php
				echo "<div>";
				echo "<input type='submit' value='Update Settings'>";
				wp_nonce_field('updateNewsOptions','news-option-nonce');
				echo "</div>";
			?>
			</form>

		</div>

		<?php
	}

}

// Instantiate our class
$harborAutoNewsManager = new harborAutoNewsManager();

add_action('admin_footer', 'autonews_javascript');

function autonews_javascript() { ?>

	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		jQuery('.close-editor').click(function(e){
			jQuery('#scrim').hide();
			jQuery('#editor').hide();
			for (x = 0; x < 15; x++) {
				jQuery('#src_' + x + '_textad').prop('checked', false);
				jQuery('#src_' + x + '_openx').prop('checked', false);
				jQuery('#ad_' + x).val('');
				jQuery('#url_' + x).val('');
			}
		});

		jQuery('.edit_ads').click(function(e){
			var name = jQuery(this).data('name');
			var label = jQuery(this).data('label');
			var data = {
				'action': 'get_ads',
				'name': name
			};

			jQuery.post(ajaxurl, data, function(response) {
				var obj = jQuery.parseJSON(response);
				jQuery.each(obj, function(i,x) {
					if (i == 'src') {
						jQuery.each(x, function(n,z) {
							jQuery('#'+i+'_'+n+'_'+z).prop('checked', true);
						});
					} else {
						jQuery.each(x, function(n,z) {
							jQuery('#'+i+'_'+n).val(z);
						});
					}
				});
			});
			jQuery('#editor h3').text('Edit '+label+' Ads');
			jQuery('#editor .field_name').val(name);
			jQuery('#scrim').show();
			jQuery('#editor').show();
		});

		jQuery('#editor').submit(function(e){
	        e.preventDefault();

			var name = jQuery('.field_name').val();

			var src = new Array();
			var ad = new Array();
			var url = new Array();

			for (x = 0; x < 15; x++) {
				src[x] = jQuery('input[name=src_' + x + ']:checked').val();
				ad[x] = jQuery('#ad_' + x).val();
				url[x] = jQuery('#url_' + x).val();
			}

			var data = {
				'action': 'put_ads',
				'name': name,
				'src': src,
				'ad': ad,
				'url': url
			};

			jQuery.post(ajaxurl, data, function(response) {});
			jQuery('#scrim').hide();
			jQuery('#editor').hide();
			for (x = 0; x < 15; x++) {
				jQuery('#src_' + x + '_textad').prop('checked', false);
				jQuery('#src_' + x + '_openx').prop('checked', false);
				jQuery('#ad_' + x).val('');
				jQuery('#url_' + x).val('');
			}
		});
	});
	</script>

<?php }

add_action( 'wp_ajax_get_ads', 'get_ads_callback' );

function get_ads_callback() {
	global $wpdb; 
	$name = $_POST["name"];
	$query = "SELECT ads FROM wp_harbor_auto_newsletter WHERE (field_name = '".$name."');";
	$results = $wpdb->get_results($query, ARRAY_A);
	$results = unserialize($results[0][ads]);
	echo json_encode($results);
	die();
}

add_action( 'wp_ajax_put_ads', 'put_ads_callback' );

function put_ads_callback() {
	global $wpdb; 
	$name = $_POST["name"];
	$update['src'] = $_POST["src"];
	$update['ad'] = $_POST["ad"];
	$update['url'] = $_POST["url"];
	$update = serialize($update);
	$check = $wpdb->get_var("SELECT id FROM wp_harbor_auto_newsletter WHERE (field_name = '".$name."');");
	if ($check > 0) {
		$response = $wpdb->query("UPDATE wp_harbor_auto_newsletter SET ads = '".$update."' WHERE (field_name = '".$name."');");
	} else {
		$response = $wpdb->query("INSERT INTO wp_harbor_auto_newsletter (field_name, ads) VALUES ('".$name."', '".$update."');");
	}
	die();
}
