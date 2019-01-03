<?php
/**
 * Template Name: Page Offsite Order Confirmation
 */

// UPDATE 2017-09-18

session_start();

get_header();

define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTCACHEOBJECT', true);

$order_id = $_GET['oid'];
$pub_id = $_SESSION["order-".$order_id]["pub_id"];
$channel = $_SESSION["order-".$order_id]["channel"];
$keycode = $_SESSION["order-".$order_id]["keycode"];
$ordersummary = $_SESSION["order-".$order_id]["summary"];
$issue_number = $_SESSION["order-".$order_id]["issue"];

echo '<!-- '.print_r($_SESSION["order-".$order_id],1).'-->';

echo "<div class='row'>";
echo "<div class='large-8 medium-8 columns' role='main' id='maincol'>";

while ( have_posts() ) {
	the_post();

	$dev = (preg_match('/dev\./', $_SERVER['HTTP_HOST'])) ? true : false;

	$pub_name = get_pub_title($pub_id);

	$parent = get_pub_parent_slug($pub_id);
	if (!$parent) { $parent = $pub_id; }

	$post_slug = "order-confirmation-".strtolower($keycode);
	$post_content = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$post_slug."') AND (post_type = 'uc');");

	if (!$post_content) {
		$post_slug = "order-confirmation-".$parent."-".$channel;
		if (preg_match('/^CF\d\d\d$/', $pub_id)) { $post_slug = 'order-confirmation-cf-single-issue'; }
		$post_content = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = '".$post_slug."') AND (post_type = 'uc');");
	}

	if ($post_content) {

		$search_array = array('%%productname%%', '%%siteurl%%', '%%ordersummary%%');
		$replace_array = array($pub_name, site_url(), $ordersummary);

		$post_content = str_replace($search_array, $replace_array, $post_content);

		if ($dev) { $dev = '<b>DISPLAYING: ' . $post_slug . '</b><br/><br/>'; }

	} else {

		$post_content = $wpdb->get_var("SELECT post_content FROM wp_posts WHERE (post_name = 'order-confirmation-error') AND (post_type = 'uc');");

		if ($dev) { $dev = '<b>COULD NOT FIND: ' . $post_slug . '</b><br/>KEYCODE WAS '.$keycode.'<br/><br/>'; }

	}

	echo "<article ".get_post_class()." id='post-".get_the_ID()."'>";
	echo "<div class='entry'>";
	echo $dev;
	echo wpautop($post_content);
	echo "</div>";
	echo "</article>";

}

echo "</div><!-- end div.large-8 medium-8 columns -->";

get_sidebar();

echo "</div>";

get_footer();
