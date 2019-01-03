<?php
/**
 * Plugin Name: Harbor Profiles
 * Plugin URI: http://www.kwyjibo.com
 * Description: Display download and purchase habits for individual user.
 * Version: 0.7
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

/**
 * harborProfileManager is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */

class harborProfileManager {

	function __construct(){
		add_action('admin_menu', array($this, 'dashboard'));
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	public function dashboard() {
		if (function_exists('add_submenu_page')) {
			add_submenu_page( 'users.php', __('Harbor Profile Manager'), __('Harbor Profile Manager'), 'manage_options', 'harbor-profile-manager', array($this, 'options') );
		}
	}

	public function options() {
		global $wpdb; ?>

		<style>
			#userquery { height: 29px; }
			#profiletables { position: relative; width: 100%; }
			#profiletables table { width: 48%; border-collapse: collapse; }
			#tableusers { float: left; }
			#tableusers tr:hover { cursor: pointer; background-color: rgba(0,0,0,0.05) }
			#tableusers tr.selected { background-color: #fff; font-weight: bold; }
			#tableusers td { padding: 3px 8px; }
			#tabletransactions { float: right; }
			#tabletransactions td { padding: 8px; }
			#tabletransactions td.date-cell { font-weight: bold; border-bottom: 1px solid #ccc; background-color: #fff; }

			.type-icon:before { display: inline-block; font: normal 20px/1 'dashicons'; color: #0a0; color: #0085ba; -webkit-font-smoothing: antialiased; vertical-align: top; }
			.type-download:before { content: "\f316"; }
			.type-subscribe:before { content: "\f465"; }
			.type-unsubscribe:before { content: "\f465"; color: #a00; }
			.type-order:before { content: "\f174"; }
			.type-cancelorder:before { content: "\f174"; color: #a00; }
			.type-request:before { content: "\f183"; }
			.type-topic-subscribe:before { content: "\f529"; }
			.type-topic-unsubscribe:before { content: "\f542"; color: #a00; }
			.type-pub:before { content: "\f331"; }
			.type-view:before { content: "\f472"; }

		</style>

		<div class="wrap">

			<?php $plugin_data = get_plugin_data(__FILE__, 0, 0); ?>

			<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>

			<form id='searchform'>
				<input type='text' id='userquery' placeholder='Search Users'>&nbsp;
				<input type='submit' id='search' class='button-primary' value='Search'>&nbsp;
				<span id='searchstatus'></span>
			</form>
			<br clear='all' />
			<div id="profiletables">
				<table id='tableusers'></table>
				<table id='tabletransactions'></table>
			</div>

		</div><!-- wrap -->

		<?php
	}


}

// Instantiate our class
$harborProfileManager = new harborProfileManager();

add_action( 'admin_footer', 'my_action_javascript' ); // Write our JS below here

function my_action_javascript() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		jQuery('#searchform').submit(function(e){
	        e.preventDefault();
			jQuery('#searchstatus').html('Searching...');
			var q = jQuery('#userquery').val();
			if (q.length > 1) {
				var data = {
					'action': 'my_action',
					'whatever': q
				};

				jQuery.post(ajaxurl, data, function(response) {
					var output = '';
					var searchstatus = '';
					var obj = jQuery.parseJSON(response);
					jQuery.each(obj, function(i,x) {
						output += '<tr data-user-id='+x.ID+'><td><i>'+x.ID+'</i></td><td><b>'+ x.last + ', '+ x.first + '</b></td><td>' + x.email + '</td></tr>'
					})
					
					if (output == '') { searchstatus = 'Nothing Found'; }
					jQuery('#searchstatus').html(searchstatus);
					jQuery('#tableusers').html(output);
				});
			} else {
				jQuery('#searchstatus').html('Search for 2 or more letters.');
				jQuery('#tableusers').html('');
				jQuery('#tabletransactions').html('');

			}
			jQuery('#tableusers').on("click", "tr", function() {
				jQuery(this).siblings().removeClass('selected');
				jQuery(this).addClass('selected');
				var user_id = jQuery(this).data('user-id');
				var data2 = {
					'action': 'other_action',
					'whatever': user_id
				};

				jQuery.post(ajaxurl, data2, function(responze) {
					var label = {
						download:"Download Free Report",
						subscribe:"Subscribe to Newsletter",
						unsubscribe:"Unsubscribe from Newsletter",
						order:"Shop Order",
						cancelorder:"Cancel Shop Order",
						request:"Request Information",
						topicsubscribe:"Subscribe to a Topic",
						topicunsubscribe:"Unsubscribe from a Topic",
						pub:"Subscribe to a Publication",
						view:"View Report"
					};
					var output = '';
					var previous_date = '';
					var obj = jQuery.parseJSON(responze);
					if (jQuery.isEmptyObject(obj)) {
						output += '<tr><td>No transactions found for user '+ user_id +'.</td></tr>';
					} else {
						jQuery.each(obj, function(i,x) {
							if (x.date != previous_date) {
								output += '<tr><td colspan=3 class="date-cell">'+ x.date + '</td></tr>';
							}
							
							output += '<tr title="' + label[x.type.replace(/-/g, "")] + '">';
							output += '<td><span class="type-icon type-' + x.type + '"><span></td>';
							output += '<td>' + x.item + '</td>';
							output += '<td><i>' + label[x.type.replace(/-/g, "")] + '</i></td>';
							output += '</tr>';
							previous_date = x.date;
						})
					}
					jQuery('#tabletransactions').html(output);
				});
			});
		});
	});
	</script> <?php
}

add_action( 'wp_ajax_my_action', 'my_action_callback' );

function my_action_callback() {
	global $wpdb; 

	$q = $_POST["whatever"];

	/*$query = "SELECT u.ID, u.user_email AS email, f.meta_value AS first, l.meta_value AS last
			FROM wp_users u
			JOIN wp_usermeta f ON u.ID = f.user_id AND f.meta_key = 'first_name'
			JOIN wp_usermeta l ON u.ID = l.user_id AND l.meta_key = 'last_name'
			WHERE
			(u.ID = '".$q."') OR
			(u.user_email LIKE '%".$q."%') OR
			(u.user_nicename LIKE '%".$q."%') OR
			(u.display_name LIKE '%".$q."%') OR
			(f.meta_value LIKE '%".$q."%') OR
			(l.meta_value LIKE '%".$q."%')
			ORDER BY last, first, email;";*/

	// MUCH FASTER
	$query = "SELECT u.ID, u.user_email AS email, f.meta_value AS first, l.meta_value AS last
			FROM wp_users u
			JOIN wp_usermeta f ON u.ID = f.user_id AND f.meta_key = 'first_name'
			JOIN wp_usermeta l ON u.ID = l.user_id AND l.meta_key = 'last_name'
			WHERE
			(u.ID = '".$q."') OR
			(u.user_email LIKE '%".$q."%') OR
			(u.user_nicename LIKE '%".$q."%') OR
			(u.display_name LIKE '%".$q."%')
			ORDER BY last, first, email
			LIMIT 1;";

	$results = $wpdb->get_results($query);

	echo json_encode($results);
	die();
}

add_action( 'wp_ajax_other_action', 'other_action_callback' );

function other_action_callback() {
	global $wpdb; 

	$has_orders = $wpdb->get_var("SHOW TABLES LIKE 'wp_harbor_orders';");

	$q = $_POST["whatever"];

	if (!empty($has_orders)) {
		$query = "SELECT t.itemId AS item, o.product_name AS product, p.post_title AS company, t.asid, DATE_FORMAT(t.dt,'%M %e, %Y') AS date, y.type
				FROM wp_harbor_transactions t
				JOIN wp_harbor_transaction_types y ON t.typeId = y.id
				LEFT JOIN wp_harbor_orders o ON t.itemId = o.id
				LEFT JOIN wp_posts p ON t.itemId = p.ID
				WHERE (t.user_id = ".$q.")
				ORDER BY t.dt DESC;";
	} else {
		$query = "SELECT t.itemId AS item, p.post_title AS company, t.asid, DATE_FORMAT(t.dt,'%M %e, %Y') AS date, y.type
				FROM wp_harbor_transactions t
				JOIN wp_harbor_transaction_types y ON t.typeId = y.id
				LEFT JOIN wp_posts p ON t.itemId = p.ID
				WHERE (t.user_id = ".$q.")
				ORDER BY t.dt DESC;";
	}

	//m.first_name, m.last_name, m.phone, m.address, m.address2, m.city, m.state, m.zip_code, m.country, m.sub_email_newsletter, m.sub_week_in_review, m.sub_publisher_spotlight, m.confirm_token

	$results = $wpdb->get_results($query);

	$filtered = array();

	foreach ($results as $key => $r) {
		if ( 'order' == $r->type || 'cancel-order' == $r->type ) {
			$filtered[$key]['item'] = apply_filters('harbor_transaction_order_product_name', $r->product, $r->item );
		} else if ( 'request' == $r->type || 'cancel-order' == $r->type ) {
			$filtered[$key]['item'] = $r->company;
		} else {
			$filtered[$key]['item'] = $r->item;
		}
		$filtered[$key]['asid'] = $r->asid;
		$filtered[$key]['date'] = $r->date;
		$filtered[$key]['type'] = $r->type;
	}/**/

	echo json_encode($filtered);
	die();
}

?>
