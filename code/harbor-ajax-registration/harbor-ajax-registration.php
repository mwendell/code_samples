<?php
/**
 * Plugin Name: Harbor AJAX Registration
 * Plugin URI: http://www.kwyjibo.com
 * Description: Register user in Harbor via AJAX call
 * Version: 0.2.7
 * License: GPL
 * Author: Michael Wendell
 * Author URI: http://www.kwyjibo.com
 */

// INITIALIZE SCRIPTS AND ACTIONS
// =========================================================================

	function ajax_ofie_init() {

		$theme = get_template_directory_uri();
		$directory = plugin_dir_url( __FILE__ );

		wp_register_script('validate-script', $directory . 'lib/jquery.validate.js', array('jquery') );
		wp_enqueue_script('validate-script');

		wp_register_script('validate-methods-js', $directory . 'lib/additional-methods.min.js', array('jquery') );
		wp_enqueue_script( 'validate-methods-js' );

		wp_register_script('ajax-ofie-script', $directory . 'js/harbor-ajax-registration.js', array('jquery'));
		wp_enqueue_script('ajax-ofie-script');

		add_filter( 'allowed_http_origins', 'add_prd_to_allowed_origins' );

		wp_localize_script('ajax-ofie-script', 'ajax_ofie_object', array(
			'ajaxurl'			=> admin_url( 'admin-ajax.php' ),
			'redirecturl'		=> home_url(),
			'loadingmessage'	=> __('One second please...')
		));

		add_action( 'wp_ajax_process_ajax_ofie', 'process_ajax_reg' );
		add_action( 'wp_ajax_nopriv_process_ajax_ofie', 'process_ajax_reg' );

		add_action( 'wp_ajax_process_ajax_reg', 'process_ajax_reg' );
		add_action( 'wp_ajax_nopriv_process_ajax_reg', 'process_ajax_reg' );

		add_action( 'wp_ajax_check_user_email_callback', 'check_user_email_callback' );
		add_action( 'wp_ajax_nopriv_check_user_email_callback', 'check_user_email_callback' );

		add_action( 'wp_ajax_order_without_login_callback', 'order_without_login_callback' );
		add_action( 'wp_ajax_nopriv_order_without_login_callback', 'order_without_login_callback' );

	}

	add_action('init', 'ajax_ofie_init');

	function add_prd_to_allowed_origins( $origins ) {
		$origins[] = 'https://ssl.prdinternal.com';
		return $origins;
	}

// REGISTRATION PROCESSING FUNCTIONS
// =========================================================================

	function process_ajax_reg( $post = false ) {

		define('DONOTCACHEDB', true);
		define('DONOTCACHEOBJECT', true);

		$debug = false;
		$debug_email = 'mwndll@gmail.com';

		$require_email = false;

		global $wpdb;

		$new_reg = 0;
		$order_without_login = false;
		if ( class_exists('harborAbandonRecovery') ) {
			$harborAbandonRecovery = harborAbandonRecovery::getInstance();
			if ( 'yes' == $harborAbandonRecovery->getSetting('order-without-login') ) {
				$order_without_login = true;
			}
		}

		// GET AJAX DATA

		$data = ( $post ) ? $post : $_POST;

		$var_names = array( 'user_id', 'hbsc', 'keycode', 'ajax_source', 'prd_url', 'user_email', 'first_name', 'last_name', 'address', 'address2', 'city', 'state', 'zip', 'zip_code', 'country', 'new_reg', 'validate_email', 'product_id', );

		foreach ( $var_names as $v ) {
			$$v = ( $data[$v] ) ? $data[$v] : false;
		}
		// some ajax is using zip, but future scripts should use zip_code. Harbor uses zip_code in usermeta
		if ( $zip && ! $zip_code ) {
			$zip_code = $zip;
		}

		if ( strpos( $data['email'], 'kwyjibo.com' ) !== false ) {
			mail( $debug_email, 'DEBUG AJAX POST for ' . $data['email'], print_r( $_POST, 1 ) . "\r\n\r\n" . print_r( $data, 1 ) );
		}

		// REVALIDATE INPUT

		if ( $email_required && empty($user_email) ) {
			if ( $debug ) {
				$msg = "Submit did not include required email address.\r\n".print_r($json);
				mail($debug_email, 'AJAX OFIE Email Missing', $msg);
			}
			exit('{"error":"Please enter email address."}');
		}

		if (empty($prd_url)) {
			if ( $debug ) {
				$msg = "Submit did not include PRD URL.\r\n".print_r($json);
				mail($debug_email, 'AJAX OFIE PRD URL Missing', $msg);
			}
			//exit('{"error":"There was an internal error. Please refresh this page and try again."}');
		}

		if (empty($user_id) && (empty($first_name) || empty($last_name) || empty($harborsc))) {
			if ( $debug ) {
				$msg = "Submit did not include items needed to create user and redirect...\r\n".print_r($json);
				mail($debug_email, 'AJAX OFIE Values Missing', $msg);
			}
		}

		// CREATE USER IF NECESSARY

		if ( ! is_numeric($user_id) || empty($user_id) ) {

			if ( empty($user_email) ) {

				// do_nothing

				$registration_message = 'User Email Missing';

			} else if ( email_exists($user_email) ) {

				// get user information. redirect.
				// --------------------------------------------------------------------

				$user = get_user_by('email', $user_email);
				$user_id = $user->ID;
				$first_name = $user->first_name;
				$last_name = $user->last_name;

				$email_exists = true;
				if ( $order_without_login ) {
					$registration_message = 'Saving Information';
				} else {
					$registration_message = 'Redirecting...';
				}

			} else {

				$new_reg = time();

				// create username, default to email address
				// --------------------------------------------------------------------

				$user_login = strtolower(sanitize_email($user_email));
				if (username_exists($user_login)) {
					$integer_suffix = 2;
					while (username_exists($user_login.'-'.$integer_suffix)) { $integer_suffix++; }
					$user_login .= $integer_suffix;
				}
				$user_pass = wp_generate_password(8, false);

				// insert user
				// --------------------------------------------------------------------

				$userdata = array(
					'user_login'	=> $user_login,
					'first_name'	=> $first_name,
					'last_name'		=> $last_name,
					'user_email'	=> $user_email,
					'user_pass'		=> $user_pass,
				);

				$user_id = wp_insert_user($userdata);

				// check for errors
				// --------------------------------------------------------------------

				if ( is_wp_error($user_id) || ! is_int($user_id) ) {
					if ( $debug ) {
						$msg = "Did not successfully create user.\r\nwp_insert_user() returned ".$user_id."\r\n\r\n".print_r($userdata);
						mail($debug_email, 'AJAX OFIE Error Inserting User', $msg);
					}
					$response = json_encode( array(
						'finished'	=> false,
						'error'		=> 'Could not create new user.',
						'message'	=> 'Could not create new user.',
					) );
					exit( $response );
				}

				// insert metadata
				// --------------------------------------------------------------------

				$harborRegistration = harborRegistration::getInstance();
				$harborRegistration->save_fields( $user_id ); // confirm and opt_out tokens

				$meta_keys = array('ajax_source','prd_url','user_email','first_name','last_name','address','address2','city','state','zip_code','country');
				foreach ( $meta_keys as $m ) {
					if ( $$m ) {
						update_user_meta( $user_id, $m, $$m );
					}
				}

				// send welcome email
				// --------------------------------------------------------------------

				$harborRegistration->wp_new_user_notification($user_id, $user_pass, false);

				// register user for notifications
				// --------------------------------------------------------------------

				if ( class_exists( 'harborWhatCountsFramework' ) ) {
					$harborWhatCountsFramework = harborWhatCountsFramework::getInstance();
					$harborWhatCountsFramework->add_new_user( $user_id );
				} else if ( class_exists( 'harborHarborPrime' ) ){
					$harborHarborPrime = harborHarborPrime::getInstance();
					$harborHarborPrime->addUser( $user_id );
				}

				// explictly record source codes
				// --------------------------------------------------------------------

				$harborSourceTracking = harborSourceTracking::getInstance();
				$harborosc = $harborSourceTracking->getCurrentSourceCode();

				update_user_meta( $user_id, 'harborosc', $harborosc );
				update_user_meta( $user_id, 'hbsc', $harborosc );

				$registration_message = 'Registration Successful';

			}

		} else {

			// order without login
			// updated info saved in HARC table and order table
			$registration_message = 'Saving Information';

			$update_meta_values = false;

			if ( $user_email == $validate_email && $new_reg > strtotime('-1 hour') ) { $update_meta_values = true; }

			if ( ! $update_meta_values ) {
				if ( email_exists($user_email) ) {
					$user = get_user_by('email', $user_email);
					if ( $user->ID == $user_id ) {
						$update_meta_values = true;
					}
				}
			}

			if ( $update_meta_values ) {
				// update existing user data IF resubmmitting processing form
				// within one hour and have not changed email address
				// --------------------------------------------------------------------

				$meta_keys = array( 'first_name', 'last_name', 'address', 'address2', 'city', 'state', 'zip_code', 'country' );
				foreach ( $meta_keys as $m ) {
					if ( $$m ) {
						update_user_meta( $user_id, $m, $$m );
					}
				}

				$registration_message = 'Information Updated';

			}

		}


		if ( $prd_url ) {

			// SET UP REDIRECT TO PRD

			$query_args = array(
				'em'	=> rawurlencode( $user_email ),
				'fn'	=> rawurlencode( $first_name ),
				'ln'	=> rawurlencode( $last_name ),
				'uid'	=> intval( $user_id ),
				'hbsc'	=> rawurlencode( $harborsc ),
			);
			$query_args = array_filter( $query_args );

			$prd_url = add_query_arg( $query_args, $prd_url );

			// for HARC plugin...
			$prd_url = apply_filters( 'harbor_external_order_url', $prd_url );

			$response = json_encode( array(
				'finished'	=> true,
				'prd_url'	=> $prd_url,
				'message'	=> 'Redirecting...',
			) );

		} else {

			$response = json_encode( array(
				'finished'		=> true,
				'user_id'		=> intval( $user_id ),
				'new_reg'		=> $new_reg,
				'message'		=> $registration_message,
			) );

		}

		exit( $response );
	}

	function check_user_email_callback( $post = false ) {
		$data = ( $post ) ? $post : $_POST;
		if ( is_user_logged_in() ) {
			$response = json_encode( "true" );
		} else {
			global $wpdb;
			if ( email_exists( $data['user_email'] ) ) {
				$order_without_login = false;
				if ( class_exists('harborAbandonRecovery') ) {
					$harborAbandonRecovery = harborAbandonRecovery::getInstance();
					if ( 'yes' == $harborAbandonRecovery->getSetting('order-without-login') ) {
						$order_without_login = true;
					}
				}
				if ( $order_without_login ) {
					$response = json_encode( "false" );
				} else {
					$response = json_encode( "The email you have chosen is already registered. Please <a href='#' data-reveal-id='harborLogin'>login</a> or choose a different email." );
				}
			} else {
				$response = json_encode( "true" );
			}
		}
		exit( $response );
	}

	function order_without_login_callback( $post = false ) {
		$data = ( $post ) ? $post : $_POST;
		$result = '';
		$user_id = 0;
		if ( ! is_user_logged_in() && class_exists('harborAbandonRecovery') ) {
			$harborAbandonRecovery = harborAbandonRecovery::getInstance();
			if ( 'yes' == $harborAbandonRecovery->getSetting('order-without-login') ) {
				$user_id = email_exists( $data['user_email'] );
				if ( $user_id ) {
					global $wpdb;
					$user_order_info = array(
						'user_email',
						'first_name',
						'last_name',
						'address',
						'address2',
						'city',
						'state',
						'zip_code',
						'country',
						'phone',
						'offer_id'
					);
					foreach ( $user_order_info as $info ) {
						$$info = $data[$info] ? esc_attr($data[$info]) : '';
					}
					$offer_id = $data['selected_offer_id'] ? $data['selected_offer_id'] : '';

					$result = $wpdb->insert(
						$wpdb->prefix . 'harbor_abandon_recovery',
						array(
							'user_id' => $user_id,
							'user_email' => $user_email,
							'first_name' => $first_name,
							'last_name' => $last_name,
							'address' => $address,
							'address2' => $address2,
							'city' => $city,
							'state' => $state,
							'zip_code' => $zip_code,
							'country' => $country,
							'phone' => $phone,
							'update_token' => uniqid(rand()),
							'offer_id' => $offer_id,
							'not_logged_in_registered' => 'y'
						),
						array(
							'%d',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s'
						)
					);
				}
			}
		}
		if ( $result ) {
			$response = json_encode( array( 'user_id' => intval($user_id) ) );
		} elseif ( is_user_logged_in() ) {
			$response = json_encode( "User is logged in." );
		} elseif ( ! class_exists('harborAbandonRecovery') ) {
			$response = json_encode( "Abandon recovery methods not available." );
		} elseif ( ! $user_id ) {
			$response = json_encode( "New user registration." );
		} else {
			$response = '';
		}
		exit( $response );
	}

// FRONT END DISPLAY FUNCTIONS AND SHORTCODES
// =========================================================================

	function ajax_ofie_sllp($args) {

		$args = shortcode_atts(array(
			'header'			=> false,
			'body'				=> false,
			'text'				=> false,
			'style'				=> false,
			'photo'				=> false,
			'button_text'		=> 'Subscribe Now!',
			'button_mobile'		=> 'Subscribe Now!'
		), $args);

		$rand = intval(microtime()*100000);

		global $post;
		$post_id = $post->ID;
		$title = get_the_title($post_id);
		if (get_post_meta($post_id, 'ofie_image', true)) {
			$image = get_post_meta($post_id, 'ofie_image', true);
			$image = "<img src='/wp-content/uploads/".$image."'>";
		} else {
			$img_atts = array(
				'class'	=> 'thumb hide-on-phones',
				'alt'	=> trim(strip_tags( $title )),
				'title'	=> trim(strip_tags( $title )),
				'style' => 'margin-bottom:2%'
			);
			$image = get_the_post_thumbnail( $post_id, 'small-thumbnail', $img_atts );
		}

		$harborSourceTracking = harborSourceTracking::getInstance();
		$harborsc = $harborSourceTracking->getCurrentSourceCode();

		$harborRegistration = harborRegistration::getInstance();
		$harborRegistration->show_errors( $show_error_args );

		$prd_url = get_field('prd_subscription_url');

		$user_id = get_current_user_id();
		$user = get_userdata($user_id);

		$fields = array();

		if ($user->ID) { $fields['user_id'] = $user->ID; }
		if ($user->user_email) { $fields['user_email'] = $user->user_email; }
		if ($user->first_name) { $fields['first_name'] = $user->first_name; }
		if ($user->last_name) { $fields['last_name'] = $user->last_name; }
		if ($harborsc) { $fields['hbsc'] = $harborsc; }
		if ($prd_url) { $fields['prd_url'] = $prd_url; }

		$form .= '<aside class="ajax_ofie_aside rclp_ofie super_ofie_sllp callout primary" ' . $args['style'] . ' >';

		if ( $args['photo'] && $image ) {
			$form .= '<div align="center"><figure>'.$image.'</figure></div>';
		}

		if ( !empty($args['header']) ) {
			$form .= "<h1 class='sllp_headline'>" . $args['header'] . "</h1>";
		}

		if ( !empty($args['text']) ) {
			$form .= "<p>" . $args['text'] . "</p>";
		}

		$form .= '<form action="process_ajax_ofie" method="post" class="ajax_ofie" id="form_'.$rand.'">';

		$form .= '<p class="status"></p>';

		if (!$user) {
			$form .= '<div class="row"> ';
			$form .= '<div class="columns large-6">';
			$form .= '<input type="text" required placeholder="First Name" value="'.$first_name.'" class="first_name" name="first_name" id="first_name_'.$rand.'">';
			$form .= '</div>';
			$form .= '<div class="columns large-6">';
			$form .= '<input type="text" required placeholder="Last Name" value="'.$last_name.'" class="last_name" name="last_name" id="last_name_'.$rand.'">';
			$form .= '</div>';
			$form .= '</div>';

			$form .= '<div class="row">';
			$form .= '<div class="small-12 columns">';
			$form .= '<input type="email" required placeholder="Email Address" value="'.$user_email.'" class="user_email" name="user_email" id="user_email_'.$rand.'">';
			$form .= '</div>';
			$form .= '</div>';
		}

		$form .= '<div class="row">';
		$form .= '<div class="small-12 columns">';
		$form .= '<div class="sllpButton centeronmobile">';
		$form .= '<span class="show-for-large"><button type="submit" value="'. $args['button_text'] . '" class="button centeronmobile arrow">'. $args['button_text'] . '</button></span>';
		$form .= '<span class="hide-for-large"><button type="submit" value="'. $args['button_mobile'] . '" class="button centeronmobile arrow">'. $args['button_mobile'] . '</button></span>';
		$form .= '</div>';
		$form .= '</div>';
		$form .= '</div>';

		foreach ($fields as $key => $value) {
			$form .= '<input type="hidden" name="'.$key.'" class="'.$key.'" value="'.$value.'" />';
		}

		$form .= '</form>';

		if ( !empty($args['body']) ) {
			$form .= '<div>' . wpautop($args['body']) . '</div>';
		}

		$form .= '<div class="clear_floats"></div>';

		$form .= '<p class="disclosure">We understand that your email address is private. We will never share your information except as outlined in our privacy policy.</p>';

		$form .= '</aside>';

		return $form;

	}

	add_shortcode('ajax_ofie_sllp', 'ajax_ofie_sllp');
