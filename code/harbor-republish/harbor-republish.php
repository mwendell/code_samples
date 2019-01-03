<?php
/**
 * Plugin Name: Harbor Republish
 * Description: Republish a post on a specified date.
 * Version: 0.6
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

/**
 * --------------------------------------------------------------
 * Display Settings page that shows "report"
 * --------------------------------------------------------------
 */
function harbor_republish_admin_menu(){
	add_options_page(__("Harbor Republish", 'republish-post'), __("Harbor Republish", 'republish-post'), 'manage_options', 'harborrepublishpost', 'harbor_replublish_settings_page');
}
add_action( 'admin_menu', 'harbor_republish_admin_menu' );

function harbor_replublish_settings_page(){

	global $wpdb;

	$timezone_string = get_option('timezone_string');
	$timezone_string = (empty($timezone_string)) ? 'America/New_York' : $timezone_string;
	date_default_timezone_set($timezone_string);

	$now = time();

	$sql = "SELECT m.post_id, p.post_title, m.meta_value, p.post_date FROM wp_postmeta m INNER JOIN wp_posts p ON m.post_id = p.ID WHERE (m.meta_key = 'republish_date') AND (m.meta_value > ". $now ." ) ORDER BY m.meta_value ASC;";

	$results = $wpdb->get_results( $sql );

	$plugin_data = get_plugin_data(__FILE__, 0, 0);

	$html = '<div class="wrap">' . "\n";
	$html .= '<h2>' . __( 'Harbor Republish Post' , 'republish-post' ) . ' - Version ' . $plugin_data['Version'] . '</h2>' . "\n";


	$html .= '<div>
		<table class="wp-list-table widefat fixed striped">
			<thead><h3>Posts Set to Republish</h3>
			
				<tr>
					<th>Post</th>
					<th>Current Post Date</th>
					<th>Will Be Republished On...</th>
				</tr></thead><tbody>';

	foreach( $results as $repub_post ){

		$url = '/wp-admin/post.php?post=' . $repub_post->post_id . '&action=edit';
		$repub_date = date( 'Y-m-d H:i:s', $repub_post->meta_value );

		$html .= '<tr>';
		$html .= '<td><a href="' . $url . '" target="_blank">' . $repub_post->post_title . '</a></td>';
		$html .= '<td>' . $repub_post->post_date . '</td>';
		$html .= '<td>' . $repub_date . '</td>';
		$html .= '</tr>';
	}


	$html .= "
			</tbody>
		</table>
	</div>";

	$html .= '</div>';

	echo $html;
}

/**
 * --------------------------------------------------------------
 * Display Meta box on Post page
 * --------------------------------------------------------------
 */
function republish_metabox() {
	//add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
	add_meta_box('republish_meta', __('Schedule a Republish Date'), 'republish_meta_options', 'post', 'side', 'default');
}
add_action('add_meta_boxes', 'republish_metabox');

function republish_meta_options() {
	global $post;

	$timezone_string = get_option('timezone_string');

	$timezone_string = (empty($timezone_string)) ? 'America/New_York' : $timezone_string;

	date_default_timezone_set($timezone_string);

	$now = time();

	$republish_date = get_post_meta($post->ID, 'republish_date', true);
	$republish_history = explode(',', get_post_meta($post->ID, 'republish_history', true));

	if ($republish_date > $now) {
		$month = date('m', $republish_date); // two digit month
		$day = date('d', $republish_date); // two digit date
		$year = date('Y', $republish_date); // four digit year
		$hour = date('H', $republish_date); // two digit hour
		$minute = date('i', $republish_date); // two digit minute
		$label = __('Current Republish Date');
		$help = __('To cancel republishing, clear the fields above.');
	} else {
		$month = $day = $hour = $minute = $year = '';
		$label = __('Add a Republish Date');
		$help = __('Fill in the fields above to add a republish date.');
	}

	$months = array('01-Jan','02-Feb','03-Mar','04-Apr','05-May','06-Jun','07-Jul','08-Aug','09-Sep','10-Oct','11-Nov','12-Dec');
	$month_options = "<option value=''>Select</option>";
	foreach ($months as $key => $m) {
		$month_options .= "<option value='".($key+1)."'";
		$month_options .= (($key+1) == intval($month)) ? " selected" : "" ;
		$month_options .= ">".$m."</option>";
	}

	$day_options = "<option value=''></option>";
	foreach (range(1,31) as $d) {
		$day_options .= "<option value='".$d."'";
		$day_options .= (intval($d) == intval($day)) ? " selected" : "" ;
		$day_options .= ">".$d."</option>";
	}

	$ty = intval(date('Y'));
	$year_options = "<option value=''></option>";
	foreach (range($ty,$ty+5) as $y) {
		$year_options .= "<option value='".$y."'";
		$year_options .= (intval($y) == intval($year)) ? " selected" : "" ;
		$year_options .= ">".$y."</option>";
	}

	$hour_options = "<option value=''></option>";
	foreach (range(1,23) as $h) {
		$hour_options .= "<option value='".$h."'";
		$hour_options .= (intval($h) == intval($hour)) ? " selected" : "" ;
		$hour_options .= ">".$h."</option>";
	}

	$minute_options = "<option value=''></option>";
	foreach (range(0,59) as $m) {
		$minute_options .= "<option value='".$m."'";
		$minute_options .= (intval($m) == intval($minute)) ? " selected" : "" ;
		$minute_options .= ">".$m."</option>";
	}

	echo "<style type=text/css>
		div.republish-date {
			width: 100%;
			position: relative;
			overflow: hidden;
		}

		div.republish-date select,
		div.republish-date input {
			border: 1px solid #ddd;
			-webkit-box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
			box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
			background-color: #fff;
			color: #333;
			-webkit-transition: .05s border-color ease-in-out;
			transition: .05s border-color ease-in-out;
			font-size: 12px;
		}

		div.republish-date select {
			height: 21px;
			line-height: 14px;
			padding: 0;
			vertical-align: top;
		}

		div.republish-date input,
		div.republish-date select {
			padding: 1px;
		}

		div.republish-date select.r-hour,
		div.republish-date select.r-day,
		div.republish-date select.r-minute {
			width: 3.5em;
		}
		div.republish-date select.r-year {
			width: 6em;
		}

		div.republish-date select:focus,
		div.republish-date input:focus {
			border-color: #5b9dd9;
			-webkit-box-shadow: 0 0 2px rgba(30,140,190,.8);
			box-shadow: 0 0 2px rgba(30,140,190,.8);
		}
	</style>";

	echo '<div class="republish-date" style="">';
	echo '<input type="hidden" name="republish_nonce" id="republish_nonce" value="'.wp_create_nonce(plugin_basename(__FILE__)).'"/>';

	if ($_SESSION['republish_error']){
		echo "<p class='republish-error'>".$_SESSION['republish_error']."</p>";
		unset($_SESSION['republish_error']);
	}

	echo '<label><b>'.$label.'</b>&nbsp; <small>('.timezone_name($timezone_string).')</small></label><br/>
		<select class="r-month" name="republish_month" />'.$month_options.'</select>
		<select class="r-day" name="republish_day" />'.$day_options.'</select>,
		<select class="r-year" name="republish_year" />'.$year_options.'</select><br/>at
		<select class="r-hour" name="republish_hour" />'.$hour_options.'</select>:
		<select class="r-minute" name="republish_minute" />'.$minute_options.'</select><br/>';
	echo '<small>'.$help.'</small><br/>';

	$republish_history = array_filter($republish_history);

	if ($republish_history) {
		echo '<hr/><div><label><b>'.__('Publication History').'</b></label><br/>';
		foreach($republish_history as $h) {
			if (is_numeric($h)) {
				echo date('F j, Y @ g:i a', $h).'<br/>';
			}
		}
		echo '</div>';
	}

	echo '</div>';
}

/**
 * --------------------------------------------------------------
 * Save Meta box data
 * --------------------------------------------------------------
 */
function save_republish_meta($post_id, $post) {

	if (!wp_verify_nonce( $_POST['republish_nonce'], plugin_basename(__FILE__))) { return $post->ID; }
	if (!current_user_can('edit_post', $post->ID)) { return $post->ID; }

	$timezone_string = get_option('timezone_string');

	$timezone_string = (empty($timezone_string)) ? 'America/New_York' : $timezone_string;

	date_default_timezone_set($timezone_string);

	$now = time();

	$month = twodigits($_POST['republish_month']);
	$day = twodigits($_POST['republish_day']);
	$year = $_POST['republish_year'];
	$hour = twodigits($_POST['republish_hour']);
	$minute = twodigits($_POST['republish_minute']);

	$republish_date_string = $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute;

	$republish_date = strtotime($republish_date_string);

	if (($republish_date > ($now - 86400)) && intval($month) > 0) {
		update_post_meta($post->ID, 'republish_date', $republish_date);
	} else {
		$_SESSION['republish_error'] = 'Invalid date entered';
		delete_post_meta($post->ID, 'republish_date');
	}
}
add_action('save_post', 'save_republish_meta', 1, 2); // save the date!

function twodigits($x) {
	$x = str_pad($x, 2, '0', STR_PAD_LEFT);
	return $x;
}

function timezone_name($tz) {
	$zones = array(
		'America/Puerto_Rico'	=> 'Atlantic',
		'America/Halifax'		=> 'Atlantic',
		'America/New_York'		=> 'Eastern',
		'America/Chicago'		=> 'Central',
		'Canada/Saskatchewan'	=> 'Central',
		'America/Denver'		=> 'Mountain',
		'America/Phoenix'		=> 'Arizona',
		'America/Los_Angeles'	=> 'Pacific',
		'America/Anchorage'		=> 'Alaska',
		'America/Adak'			=> 'Aleutians',
		'Pacific/Honolulu'		=> 'Hawaii',
		'America/Tijuana'		=> 'Mex. Northwest',
		'America/Mazatlan'		=> 'Mex. Pacific',
		'America/Chihuahua'		=> 'Mex. Pacific',
		'America/Mexico_City'	=> 'Mex. Central',
		'America/Matamoros'		=> 'Mex. Central',
		'America/Monterrey'		=> 'Mex. Central',
		'America/Merida'		=> 'Mex. Central',
		'America/Cancun'		=> 'Mex. Southeast',
	);
	$name = ($zones[$tz]) ? $zones[$tz] : $tz;
	$name = (strpos($name, '/') !== false) ? substr($name, strpos($name, '/') + 1) : $name; 
	return $name;

	//http://php.net/manual/en/timezones.america.php
	//http://stackoverflow.com/questions/4755704/php-timezone-list
}
