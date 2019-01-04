<?php
/**
 * Plugin Name: Harbor Autotagger
 * Plugin URI: http://www.kwyjibo.com
 * Description: Automatic Scheduled Post Tagging
 * Version: 1.43
 * License: GPL
 * Author: Michael Wendell
 * Author URI: http://kwyjibo.com
 */

class harborAutotagger {

	// ------------------------------------------------------------------------
	// ACTIVATION AND SETUP

		public function activate() {
			add_option('harborat_enable', false);
			add_option('harborat_runat', false);
			add_option('harborat_filter', true);
			add_option('harborat_email', false);
			add_option('harborat_recent', false);
			add_option('harborat_types', false);
			add_option('harborat_temps', false);
		}

		public function register_settings() {
			// constant settings
			register_setting('harbor_harbor_autotagger', 'harborat_enable');
			register_setting('harbor_harbor_autotagger', 'harborat_runat');
			register_setting('harbor_harbor_autotagger', 'harborat_filter');
			register_setting('harbor_harbor_autotagger', 'harborat_email');
			register_setting('harbor_harbor_autotagger', 'harborat_recent');
			register_setting('harbor_harbor_autotagger', 'harborat_types');
			register_setting('harbor_harbor_autotagger', 'harborat_temps');
			// cron runtime settings
			register_setting('harbor_harbor_autotagger', 'harborat_force');
			register_setting('harbor_harbor_autotagger', 'harborat_unique');
			register_setting('harbor_harbor_autotagger', 'harborat_total');
			register_setting('harbor_harbor_autotagger', 'harborat_done');
			register_setting('harbor_harbor_autotagger', 'harborat_secs');
			register_setting('harbor_harbor_autotagger', 'harborat_ping');
			register_setting('harbor_harbor_autotagger', 'harborat_posts');
			register_setting('harbor_harbor_autotagger', 'harborat_tags');
			register_setting('harbor_harbor_autotagger', 'harborat_matches');
		}

		public function __construct() {
			register_activation_hook(__FILE__, array($this,'activate'));
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_init', array($this, 'register_settings'));
			add_action('admin_init', array($this, 'upload_tags'));
			add_action('admin_init', array($this, 'upload_desc'));
			add_shortcode( 'glossary', array( $this, 'showGlossary' ) );
		}

	// ------------------------------------------------------------------------
	// MENU AND OPTIONS

		public function admin_menu() {
			add_options_page(__('Harbor Harbor Autotagger'), __('Harbor Autotagger'), 'manage_options', 'harbor_autotag', array($this, 'options'));
		}

		public function options() {

			$plugin_data = get_plugin_data(__FILE__, 0, 0);

			$harborat_posts = $harborat_tags = $harborat_ping = $harborat_report_tag = $harborat_report_post = $harborat_report_comment = $harborat_report_user= false;

			if (isset($_POST['trigger'])) {
				if (wp_verify_nonce($_REQUEST['_wpnonce'],'harborat_config')) {

					$harborat_enable = $_POST['harborat_enable'];
					$harborat_runat = $_POST['harborat_runat_hour'].":".$_POST['harborat_runat_minute'];
					$harborat_filter = $_POST['harborat_filter'];
					$harborat_email = $_POST['harborat_email'];
					$harborat_types = $_POST['harborat_types'];

					update_option('harborat_enable', $harborat_enable);
					update_option('harborat_runat', $harborat_runat);
					update_option('harborat_filter', $harborat_filter);
					update_option('harborat_types', $harborat_types);

					if (!empty($harborat_email)) {
						if (filter_var($harborat_email, FILTER_VALIDATE_EMAIL)) {
							update_option('harborat_email', $harborat_email);
						} else {
							$notice = "<div class='error'><p>The email you supplied tested as invalid. Notification email has not been updated.</p></div>";
						}
					} else {
						delete_option('harborat_email');
					}

					$notice .= "<div class='updated'><p>Your cron settings have been updated.</p></div>";
				}
			}

			if ($_POST['report_trigger'] == "send_report") {
				$tags = $_POST['harborat_report_tag'];
				$posts = $_POST['harborat_report_post'];
				$comment = $_POST['harborat_report_comment'];
				$user = $_POST['harborat_report_user'];
				$site = strtoupper($_SERVER['HTTP_HOST']);
				$subject = "Autotagger issue on ".$site;
				$body = $subject."\r\n\r\n";
				$body .= "Submitted by: ".$user."\r\n";
				$body .= "TAGS: ".$tags."\r\n";
				$body .= "POSTS: ".$posts."\r\n\r\n";
				$body .= "COMMENTS:\r\n".$comment;
				mail('mwndll@gmail.com', $subject, $body);

				$notice .= "<div class='updated'><p>Thank you. Your issue will be reviewed as soon as possible.</p></div>";
			}

			?>

			<style type='text/css'>
				h3 { color: #0074a2; }
				.float_box {
					float: left;
					width: 600px;
					padding: 0 20px;
					margin: 0 20px 20px 0;
					background-color: #f8f8f8;
					border: 1px solid #f6f6f6;
					box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
				}
				div.cron_status, div.harborat_notice {
					margin: 23px 0;
					padding: 10px 20px 16px;
					background-color: #fff;
					box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
					/*border-left: 4px solid #00a0d2;*/
					border-left-color: #00a0d2;
					border-left-width: 4px;
					border-left-style: solid;
				}
				div.pulse { animation: pulse 3s infinite; }
				@keyframes pulse {
					0% { border-left-color: #00a0d2; }
					8% { border-left-color: #ffdd11; }
					16% { border-left-color: #00a0d2; }
				}
				span.cron_disabled {
					font-weight: bold;
					color: #b00;
				}
				.submit input {
					text-shadow: none;
					font-size: 13px !important;
				}
				#harborat_posts, #harborat_tags, #harborat_ping {
					margin-left: 25px;
					margin-top: 4px;
					width: 90%;
				}
			</style>

			<div class="wrap">
				<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>
				<?php if (!empty($notice)) { echo $notice; } ?>
				<hr/>
				<form action="" method="post" class='float_box'>
					<h3><?php _e('Configure Autotagger Cron Settings') ?></h3>

					<?php

					$timezone = get_option('timezone_string');
					date_default_timezone_set($timezone);

					$harborat_recent = get_option('harborat_recent');
					if (empty($harborat_recent)) {
						$domain = $_SERVER['HTTP_HOST'];
						$harborat_recent = "There is currently no record of the cron being run.<br/>
								Please have a developer add the plugin's cron component to the server's crontab. This cron should be set to run every minute.<br/>
								<i>/usr/bin/flock -n /tmp/fcj.lockfile /usr/bin/php -f " . plugin_dir_path( __FILE__ ) . "cli-harbor-autotagger.php</i>";
					}

					$harborat_enable = esc_attr(get_option('harborat_enable'));
					$harborat_runat = esc_attr(get_option('harborat_runat'));
					$harborat_filter = esc_attr(get_option('harborat_filter'));
					$harborat_email = esc_attr(get_option('harborat_email'));
					$harborat_types = get_option('harborat_types');

					$harborat_runat = explode(":", $harborat_runat);
					$harborat_email = (filter_var($harborat_email, FILTER_VALIDATE_EMAIL)) ? $harborat_email : '';

					wp_nonce_field('harborat_config');

					$utctime = gmdate('H:i');
					?>

					<div class="cron_status"><b>Cron Status:</b><br/><?php echo $harborat_recent; ?></div>

					<p>
						<label for="harborat_enable">
							<input type="checkbox" name="harborat_enable" value="true" id="harborat_enable"
								<?php if ($harborat_enable) { echo 'checked'; } ?>
							/>
							<?php _e('Enable Autotagger Cron Component'); ?>
							<?php if (!$harborat_enable) { echo "&nbsp; &nbsp; <span class='cron_disabled'>Cron Currently Disabled</span>"; } ?>
						</label>
					</p>

					<p>
						<label>
							<select name="harborat_runat_hour" id="harborat_runat_hour"/>
							<?php for($i = 0; $i <= 24; $i++) {
								$i_str = str_pad($i,2,'0', STR_PAD_LEFT);
								echo "<option value='".$i_str."'";
								if ($harborat_runat[0] == $i_str) { echo " selected"; }
								echo ">".$i_str."</option>";
							} ?>
							</select>:
							<select name="harborat_runat_minute" id="harborat_runat_minute"/>
							<?php for($i = 0; $i <= 59; $i++) {
								$i_str = str_pad($i,2,'0', STR_PAD_LEFT);
								echo "<option value='".$i_str."'";
								if ($harborat_runat[1] == $i_str) { echo " selected"; }
								echo ">".$i_str."</option>";
							} ?>
							</select>
							<?php echo $timezone; ?>
						</label>
					</p>

					<p>
						<label for="harborat_email">
							Send Notification Email:<br/>
							<input type="text" name="harborat_email" value="<?php echo $harborat_email; ?>">
						</label>
					</p>

					<p>
						<label for="harborat_filter_daily">
							<input type="radio" name="harborat_filter" value="daily" id="harborat_filter_daily"
								<?php if ($harborat_filter == 'daily') { echo 'checked'; } ?>
							/>
							<?php _e('Only run Autotagger on Posts updated or modified within past 24 hours'); ?>
						</label><br/>
						<label for="harborat_filter_recent">
							<input type="radio" name="harborat_filter" value="recent" id="harborat_filter_recent"
								<?php if ($harborat_filter == 'recent') { echo 'checked'; } ?>
							/>
							<?php _e('Only run Autotagger on Posts updated or modified within past 7 days'); ?>
						</label><br/>
						<label for="harborat_filter_tagless">
							<input type="radio" name="harborat_filter" value="tagless" id="harborat_filter_tagless"
								<?php if ($harborat_filter == 'tagless') { echo 'checked'; } ?>
							/>
							<?php _e('Only run Autotagger on Posts which have no tags'); ?>
						</label><br/>
						<label for="harborat_filter_all">
							<input type="radio" name="harborat_filter" value="all" id="harborat_filter_all"
								<?php if ($harborat_filter == 'all') { echo 'checked'; } ?>
							/>
							<?php _e('Run Autotagger on ALL posts'); ?>
						</label>
					</p>

					<?php
					global $wpdb;
					$sql = "SELECT DISTINCT post_type FROM wp_posts WHERE (post_status = 'publish');";
					$types = $wpdb->get_col($sql);
					?>

					<p>
						Select Post Types to Tag:<br>
					<table width='100%'><tr><td valign='top'>
								<?php
								if (!is_array($harborat_types)) { $harborat_types = array($harborat_types); }
								$col = ceil(count($types)/3);
								$i = 0;
								foreach($types as $t) { $i++;
									echo "<label><input type='checkbox' name='harborat_types[]' value='".$t."'";
									if (in_array($t, $harborat_types)) { echo " checked"; }
									echo ">&nbsp;".$t."</label><br/>";
									if ($i == $col) {
										echo "</td><td valign='top'>";
										$i = 0;
									}
								}
								?>
							</td></tr></table>
					</p>

					<input type="hidden" name="trigger" value="true" />
					<?php submit_button('Update Cron Settings'); ?>

				</form>

				<script type='text/javascript'>
					jQuery(document).ready(function() {
						jQuery('#process_now').click(function() {
							var harborat_force = jQuery('input:radio[name=harborat_force]:checked').val();
							var harborat_ping = encodeURIComponent(jQuery('input:text[name=harborat_ping]').val());
							var harborat_posts = encodeURIComponent(jQuery('input:text[name=harborat_posts]').val());
							var harborat_tags = encodeURIComponent(jQuery('input:text[name=harborat_tags]').val());
							jQuery('.harborat_notice span').load( "<? echo plugins_url( '/', __FILE__ ); ?>ajax-harbor-autotagger.php?go=true&mf="+harborat_force+"&emo="+harborat_ping+"&pst="+harborat_posts+"&tgs="+harborat_tags, function() {
								show_status();
							});
						});

						jQuery('#harborat_posts').focus(function() {
							jQuery('#harborat_force_posts').prop('checked', true);
						})

						jQuery('#harborat_force_daily, #harborat_force_recent, #harborat_force_tagless, #harborat_force_all').click(function() {
							jQuery('#harborat_posts').val('');
						})

						jQuery('#harborat_tags').focus(function() {
							jQuery('#harborat_force_tags').prop('checked', true);
						})

						jQuery('#harborat_posts').keypress(function(key) {
							if (key.charCode != 44 && (key.charCode < 48 || key.charCode > 57)) return false;
						});

						jQuery('#harborat_tags').keypress(function(key) {
							if (key.charCode != 44 && (key.charCode < 48 || key.charCode > 57)) return false;
						});

					});

					function show_status() {
						jQuery('#process_now').prop('disabled', true);
						jQuery('.harborat_notice').slideDown();
						stat = setInterval(function(){reset_status()}, 3000);
					}

					function reset_status() {
						jQuery('div.harborat_notice').addClass('pulse');
						jQuery('.harborat_notice span').load( "<? echo plugins_url( '/', __FILE__ ); ?>ajax-harbor-autotagger.php?rand="+Math.random(),
							function(response, status, xhr) {
								if (response == "Autotagging complete.") {
									clearInterval(stat);
									jQuery('#process_now').prop('disabled', false);
									jQuery('div.harborat_notice').removeClass('pulse').delay(800);
									jQuery('.harborat_notice').slideUp();
								}
							})
					}

				</script>

				<form action="" method="post" class='float_box'>
					<h3><?php _e('Run Autotagger Immediately') ?></h3>

					<?php
					$harborat_filter_now = ($_POST['harborat_filter_now']) ? $_POST['harborat_filter_now'] : $harborat_filter;
					$harborat_filter_now = ($harborat_filter_now != 'false') ? 'true' : 'false';
					wp_nonce_field('harborat_process_now');
					?>

					<div class='harborat_notice' style='display: none;'><b>Current Process:</b><br/><span></span></div>

					<p>
						<label for="harborat_force_daily">
							<input type="radio" name="harborat_force" value="daily" id="harborat_force_daily" checked/>
							<?php _e('Only run Autotagger on Posts updated or modified within past 24 hours'); ?>
						</label><br/>
						<label for="harborat_force_recent">
							<input type="radio" name="harborat_force" value="recent" id="harborat_force_recent"/>
							<?php _e('Only run Autotagger on Posts updated or modified within past 7 days'); ?>
						</label><br/>
						<label for="harborat_force_tagless">
							<input type="radio" name="harborat_force" value="tagless" id="harborat_force_tagless"/>
							<?php _e('Only run Autotagger on Posts which have no tags'); ?>
						</label><br/>
						<label for="harborat_force_all">
							<input type="radio" name="harborat_force" value="all" id="harborat_force_all"/>
							<?php _e('Run Autotagger on ALL posts (may take hours to complete)'); ?>
						</label><br/>
						<label for="harborat_force_posts" id="harborat_force_posts_label">
							<input type="radio" name="harborat_force" value="posts" id="harborat_force_posts"/>
							<?php _e('Run Autotagger on these posts:'); ?>
						</label><br/>
						<input type="text" name="harborat_posts" id="harborat_posts" value="<?php echo $harborat_posts; ?>" placeholder="Comma-delimited post IDs">
					<hr/>
					<label for="harborat_tags" id="harborat_tags_label">
						<?php _e('Run Autotagger using only these tags:'); ?><br/>
						<input type="text" name="harborat_tags" id="harborat_tags" value="<?php echo $harborat_tags; ?>" placeholder="Comma-delimited term IDs">
					</label><hr/>
					<label for="harborat_ping">
						Send Email When Complete:<br/>
						<input type="text" name="harborat_ping" id="harborat_ping" value="<?php echo $harborat_ping; ?>" placeholder="Notification Email Address">
					</label>
					</p>

					<p class='submit'>
						<input type="button" name="process_now" id="process_now" class="button button-primary" value="Process Posts Now">
					</p>

				</form>

				<form enctype="multipart/form-data" action="" method="post" class='float_box'>
					<h3><?php _e('Upload Text File with New/Updated Tags') ?></h3>

					<?php wp_nonce_field('harborat_upload_tags'); ?>
					<p>
						<?php _e('File should be a text list of tags, one tag per line.'); ?>
					</p>
					<p>
						<label for="harborat_tags">
							<?php _e('Select a file for upload:'); ?>
						</label><br/>
						<input type="file" name="harborat_tags" id="harborat_tags" />
					</p>

					<?php submit_button('Upload Tags'); ?>

				</form>

				<form enctype="multipart/form-data" action="" method="post" class='float_box'>
					<h3><?php _e('Upload Tab-Delimited List of Tag Descriptions') ?></h3>

					<?php wp_nonce_field('harborat_upload_desc'); ?>
					<p>
						<?php _e('Each line should include a tag, followed by the tag description. Tags should already exist in database, or descriptions will be ignored. The tag and the description should be separated by a tab character. The tag may be the name or the slug. There should be no tabs in the description.'); ?>
					</p>

					<p>
						<label for="harborat_desc">
							<?php _e('Select a file for upload:'); ?>
						</label><br/>
						<input type="file" name="harborat_desc" id="harborat_desc" />
					</p>

					<?php submit_button('Upload Descriptions'); ?>

				</form>

				<form method="post" class='float_box'>
					<h3><?php _e('Report Autotagger Issues') ?></h3>

					<p>
						<label for="harborat_report_tag">
							The following tag(s) are not being assigned to posts properly:<br/>
							<input type="text" name="harborat_report_tag" value="<?php echo $harborat_report_tag; ?>" style="width: 100%">
						</label>
					</p>

					<p>
						<label for="harborat_report_post">
							Please list a post that should include the tag(s) listed above:<br/>
							<input type="text" name="harborat_report_post" value="<?php echo $harborat_report_post; ?>" style="width: 100%">
						</label>
					</p>

					<p>
						<label for="harborat_report_comment">
							Additional comments:<br/>
							<textarea name="harborat_report_comment" style="width: 100%; height: 73px;"><?php echo $harborat_report_comment; ?></textarea>
						</label>
					</p>

					<p>
						<label for="harborat_report_user">
							Please enter your email to recieve a response to this issue:<br/>
							<input type="text" name="harborat_report_user" value="<?php echo $harborat_report_user; ?>" style="width: 100%">
						</label>
					</p>

					<p class='submit'>
						<input type="submit" name="report_now" id="report_now" class="button button-primary" value="Report Issue">
						<input type="hidden" name="report_trigger" value="send_report">
					</p>

				</form>


			</div>
			<?php
		}

	// ------------------------------------------------------------------------
	// FUNCTIONS FOR UPLOADING TAGS AND DESCRIPTIONS

		public function upload_tags () {
			if (isset($_GET['page']) && 'harbor_autotag' == $_GET['page'] && isset($_FILES['harborat_tags'])) {
				set_time_limit(0);
				check_admin_referer( 'harborat_upload_tags' );
				ini_set('auto_detect_line_endings', true);
				global $wpdb;

				$harborat_temps = time();
				$sql = "CREATE TABLE IF NOT EXISTS temp_autotagger_upload_".$harborat_temps." (
						id INT NOT NULL auto_increment,
						name VARCHAR(200) NOT NULL,
						term_id BIGINT(20) NULL,
						processed TINYINT(4) NOT NULL DEFAULT '0',
						PRIMARY KEY (id)
						) COMMENT '".date('F j, Y, g:i a', $harborat_temps)."';";
				$wpdb->query($sql);

				update_option('harborat_temps', $harborat_temps);

				$tags_file = fopen($_FILES['harborat_tags']['tmp_name'], "r");
				$this->added = 0;
				$this->updated = 0;

				$fields = array('name');
				$count = 0;
				$all_values = array();
				$report = '';

				while (!feof($tags_file)) {
					$tag = trim(fgets($tags_file));
					$tag = preg_replace("/[^A-Za-z0-9 ]/", ' ', $tag);
					$count++;
					$all_values[] = "('".$tag."')";
					if ($count % 50 == 0) {
						$vstr = implode(',', $all_values);
						$sql = "INSERT INTO temp_autotagger_upload_".$harborat_temps." (name) VALUES ".$vstr.";";
						$report .= $sql."\r\n";
						$wpdb->query($sql);
						$all_values = array();
					}
				}
				$vstr = implode(',', $all_values);
				$sql = "INSERT INTO temp_autotagger_upload_".$harborat_temps." (name) VALUES ".$vstr.";";
				$report .= $sql."\r\n";
				$wpdb->query($sql);

				$notice = "Added {$count} tags to a temporary table. This number may include duplicates. The tags will be added to the working database at a rate of approximately 250 per minute.";
				$notice = str_replace( "'", "\'", "<div class='updated'><p>$notice</p></div>" );
				add_action('admin_notices', create_function( '', "echo '$notice';" ) );
			}
		}

		public function upload_desc () {
			if (isset($_GET['page']) && 'harbor_autotag' == $_GET['page'] && isset($_FILES['harborat_desc'])) {

				check_admin_referer( 'harborat_upload_desc' );
				set_time_limit(0);
				ini_set('auto_detect_line_endings', true);

				$desc_file = fopen($_FILES['harborat_desc']['tmp_name'], "r");
				$added = $skipped = 0;

				global $wpdb;

				while (!feof($desc_file)) {
					$data = explode("\t", fgets($desc_file));

					if (!empty($data[0])) {
						$sql = $wpdb->prepare("UPDATE wp_term_taxonomy x JOIN wp_terms t ON x.term_id = t.term_id AND x.taxonomy = 'post_tag' SET x.description = %s WHERE (t.slug = %s);", $data[1], sanitize_title($data[0]));
						$success = $wpdb->query($sql);
						if ($success) { $added++; } else { $skipped++; }
					}
				}

				$notice = "Added {$added} tag descriptions. skipped {$skipped} descriptions.";
				$notice = str_replace( "'", "\'", "<div class='updated'><p>$notice</p></div>" );
				add_action('admin_notices', create_function( '', "echo '$notice';" ) );
			}
		}

	// ------------------------------------------------------------------------
	// GLOSSARY SHORTCODE

		public function showGlossary($attr, $content=null) {

			global $wpdb;

			$sql = "SELECT t.term_id, t.name, x.description
					FROM wp_term_taxonomy x
					JOIN wp_terms t ON x.term_id = t.term_id
					WHERE (x.taxonomy LIKE 'post_tag')
					AND NOT (x.description IS NULL)
					AND NOT (x.description LIKE '')
					ORDER BY t.name;";
			$tags = $wpdb->get_results($sql);

			$alphaNav = '';
			$termList = '';
			$curLetter = '';
			foreach ( $tags as $tag ) {
				$letter = strtoupper($tag->name[0]);
				if ( $letter != $curLetter ) {
					if ( !empty($termList) ) {
						$termList .= "</dl>\r\n";
					}
					$termList .= "<h3 class='alpha_section'>{$letter}<a name='section-{$letter}' style='display: block; position: relative; top: -230px;'></a></h3>\r\n<dl>\r\n";
					$alphaNav .= "<li><a href='#section-{$letter}'>{$letter}</a></li>\r\n";
					$curLetter = $letter;
				}
				$termList .= '<dt><a href="' . get_tag_link($tag->term_id) . '">' . ucwords($tag->name) . '</a></dt>';
				$termList .= '<dd>' . wpautop($tag->description) . '</dd>';
			}
			if ( !empty($termList) ) {
				$termList .= "</dl>\r\n";
			}
			if ( !empty($alphaNav) ) {
				$alphaNav = "<div class='alpha_nav_wrapper'><ul class='alpha_nav'>\r\n{$alphaNav}</ul></div>\r\n";
			}
			return do_shortcode($alphaNav . $termList);
		}

}

// Instantiate our class
$harborAutotagger = new harborAutotagger();

