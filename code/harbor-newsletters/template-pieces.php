<?php

/**
 * Template for Harbor Newsletters
 * Version: 0.5
 */

function wrapper($url, $primary, $fieldname, $color, $body, $category_link, $first_excerpt = false) {

	/* PRIMARY, FIELDNAME, BGCOLOR, PRIMARY, BODY */
	$wrapper = '<!--%%$opt_out_token%%--> 
	<!--%%$user_id%%-->
	<!doctype html>
	<html xmlns="http://www.w3.org/1999/xhtml">
		<head>
			<meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
			<title>'.$primary.'</title>
			<style type="text/css">
				body {
					margin: 0;
					padding: 0;
					-webkit-font-smoothing: antialiased;
					font-family: "Arial", "Helvetica", sans-serif
					}

				body, .ReadMsgBody, .ExternalClass {
					width: 100%;
					background-color: #ffffff;
					}

				table {
					border-collapse: collapse;
					}

				.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {
					line-height: 150%;
					margin: 0 !important;
					}

				a { text-decoration: none; color: #333333; }

				p, h1, h2 {
					margin: 0;
					padding: 0;
					margin-bottom: 0;
					}

				@media only screen and (max-width: 640px)  {
					body[yahoo] .deviceWidth {
						width: 440px !important;
						padding: 0;
						}	

					body[yahoo] .center {
						text-align: center !important;
						}	 

					td.mobileCenter {
						text-align: center !important;
						}
				}

				@media only screen and (max-width: 479px) {
					body[yahoo] .deviceWidth {
						width: 280px !important;
						padding:0;
						}

					body[yahoo] .center {
						text-align: center !important;
					}	 
				}

		</style>
		</head>
		<body leftmargin="0" marginheight="0" marginwidth="0" style="font-family: Arial, Helvetica, sans-serif" topmargin="0" yahoo="fix">

		<!-- pre-header -->
		<div style="display:none;font-size:6px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">'.$first_excerpt.'</div>
		<!-- end pre-header -->

			<table align="center" border="0" cellpadding="0" cellspacing="0" class="ExternalClass" width="100%">
				<tr>
					<td bgcolor="#ffffff" style="padding-top:20px" valign="top" width="100%">
						<!-- Start Header-->
						<table align="center" border="0" cellpadding="0" cellspacing="0" class="deviceWidth" width="580">
							<tr>
								<td class="center" style="font-size: 13px; color: #272727; font-weight: light; text-align: center; font-family: Arial, Helvetica, sans-serif; line-height: 20px; vertical-align: middle; padding:10px 20px;">
								   <span>Having Trouble Viewing this email? <a href="%%DISPLAY_MSG%%" style="color: #f94914">view it in your browser</a></span>
								</td>
							</tr>
						</table>

						<table align="center" bgcolor="#fff" border="0" cellpadding="0" cellspacing="0" class="deviceWidth" width="580">
							<tr>
								<td bgcolor="#ffffff" style="padding:0 0 20px 0;" valign="top">
									<a href="'.$category_link.'%%STOP%%hbsc=E%%$__campaign_id%%"><img alt="" border="0" class="deviceWidth" src="'.$url.'/newsletters/images/nameplate_'.$fieldname.'.gif" style="display: block" /></a></td>
							</tr>
						</table>

						<table align="center" bgcolor="#fff" border="0" cellpadding="0" cellspacing="0" class="deviceWidth" width="580">
							<tr>
								<td bgcolor="#747678" style="padding:10px 20px" valign="middle">
									<span style="color:#fff; font-weight: normal; font-size: 18px; font-family:Arial, Helvetica, sans-serif;">Today&rsquo;s Feature</span>
								</td>
								<td align="center" bgcolor="'.$color.'"  style="padding:0" valign="middle" width="210">
									<span style="color:#fff; font-weight: bold; font-family:Arial, Helvetica, sans-serif; text-transform: uppercase; font-size: 18px; "><strong>'.$primary.'</strong></span>
								</td>
							</tr>
						</table>

					</td>
				</tr>
			</table>
				'.$body.'

			<table width="580" class="deviceWidth" border="0" cellspacing="0" align="center" bgcolor="#545454">
				<tr>
					<td align="center" width="50%" style="padding: 20px 0; color: #fff; font-size: 12px;">
						<a font-size: 12px; href="http://harbor.com/account/unsubscribe/%%STOP%%id=%%$user_id%%&tkn=%%$opt_out_token%%&hbsc=E%%$__campaign_id%%" style="color: #ffffff; font-size: 12px;"><span style="color: #ffffff; font-size: 12px; text-decoration: none;">UNSUBSCRIBE</span></a>
					</td>
					<td align="center" style="padding: 20px 0; color: #ffffff; font-size: 12px; ">
						<a href="%%FTAF%%" style="color: #ffffff; font-size: 12px;" ><span style="color: #ffffff; font-size: 12px; text-decoration: none;">FORWARD TO A FRIEND</span></a>
					</td>
				</tr>
			</table>

			<table width="580" class="deviceWidth" border="0" cellspacing="0" align="center" bgcolor="#545454">
				<tr>
					<td align="center">
						<a href="https://www.facebook.com/harbornews?_rdr=p" style="color: #ffffff;"><img alt="Facebook" border="0" src="http://cdn.harbor.com/wp-content/themes/harbor-foundation-5/img/social/facebook-white.png" width="16" /></a>  <a href="https://twitter.com/xyz_gold" style="color: #ffffff;"><img alt="Twitter" border="0" src="http://cdn.harbor.com/wp-content/themes/harbor-foundation-5/img/social/twitter-white.png" alt="Twitter" width="16" /></a>  <a href="https://www.linkedin.com/grp/home?gid=1866276" style="color: #ffffff;"><img alt="linked In" border="0" src="http://cdn.harbor.com/wp-content/themes/harbor-foundation-5/img/social/linkedin-white.png" width="16" /></a>  <a href="https://plus.google.com/+harbornews" style="color: #ffffff;"><img alt="Google Plus" class="border=" src="http://cdn.harbor.com/wp-content/themes/harbor-foundation-5/img/social/googleplus-white.png" width="16" /></a>
					</td>
				</tr>
			</table>

			<table width="580" class="deviceWidth" border="0" cellspacing="0" align="center" bgcolor="#545454">
				<tr>
					<td colspan="2" style="font-size: 13px; vertical-align: top; padding:0 8px 10px 8px">
						<p style="font-size: 12px; line-height: 18px; color: #fff; text-align: center">
							Help us be sure that this email newsletter gets to your inbox. Adding our return address <br>
							<a href="http://harbor.com/contact-us/" target="_blank" style="color: #fff">xyz@harbor.com</a> to your address book may "whitelist" us with your filter, helping<br>
							future email newsletters get to your inbox.
							<br><br>
							Was this email forwarded to you? Sign up to receive your own copy.<br>
							<br>
						</p>
					</td>
				</tr>   
			</table>

			<table width="580" border="0" cellpadding="0" cellspacing="0" align="center" class="deviceWidth" bgcolor="#ffffff">
				<tr>
					<td style="padding:0">
						<table align="left" width="59%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
							<tr>
								<td valign="top" align="left" class="center" style="padding-top:15px; color: #5d5d5d; font-size: 13px; line-height: 18px">
									Investing News Network values your privacy. At no time will we make your email address available to any third party. If at any point you wish to remove yourself from this list or change your email address please click <a href="http://harbor.com/account/%%STOP%%id=%%$user_id%%&tkn=%%$opt_out_token%%&hbsc=E%%$__campaign_id%%" style="color: #f94914">here</a>.
								</td>
							</tr>
						</table>
						<table align="right" width="39%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
							<tr>
								<td align="right" valign="top" class="mobileCenter" style="font-size: 13px; color: #5d5d5d; font-weight: normal; text-align: right; font-family: Arial, Helvetica, sans-serif; line-height: 18px; vertical-align: top; padding-top:15px"><p style="mso-table-lspace:0;mso-table-rspace:0; margin:0">  
								Dig Media Inc.<br>
								<a href="http://harbor.com/contact-us/" target="_blank" style="color: #5d5d5d">xyz@harbor.com</a><br>
								Main: <a href="tel+1-604-688-8231" style="color: #5d5d5d">+1-604-688-8231</a><br>
								L200 – 560 Beatty Street<br>
								Vancouver, BC Canada V6B 2L3
								</td>
							</tr>
						</table> 
					</td>
				</tr>
			</table>

		</body>
	</html>';

	return $wrapper;

}

function article($url, $title, $subtitle, $date, $excerpt, $permalink, $image, $has_underline = true, $button_config = false, $linktext = false, $small_image = false) {

	$button_config = ($button_config == 'none') ? false : $button_config;

	/* ARTICLE, UNDERLINE */
	$article_wrapper = '<table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" class="deviceWidth" width="580">
		<tr>
		%%ARTICLE%%
		</tr>
		%%UNDERLINE%%
		</table>';

	$underline = '<tr><td bgcolor="#ffffff" height="1" style="border-top: 1px solid #000"></td></tr>';

	$readmore = '<p style="padding: 0;  margin:0"><a href="%%PERMALINK%%%%STOP%%hbsc=E%%$__campaign_id%%" style="font-weight: bold; color: #f9511d">Read More →</a></p>';
	$textlink = '<p style="padding: 0;  margin:0"><a href="%%PERMALINK%%%%STOP%%hbsc=E%%$__campaign_id%%" style="font-weight: bold; color: #f9511d">%%LINKTEXT%%</a></p>';
	$button = '<table align="left" width="200" style="margin-top: 8px;">
		<tr><td align="center" bgcolor="#f9511d" style="padding:10px 0; background-color:#f9511d; border: none;">
			<a href="%%PERMALINK%%%%STOP%%hbsc=E%%$__campaign_id%%" style="color:#ffffff; font-size:13px; font-weight:bold; text-align:center; text-decoration:none; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust:none;">%%LINKTEXT%%</a>
		</td></tr>
	</table>';

	/* IMAGE, TITLE, DATE, EXCERPT, PERMALINK */
	if ($small_image) {
		$feature_article = '<td style="padding:0">
			<table width="100%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
				<tr><td>
					<table align="left" width="18%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
						<tr>
							<td valign="top" align="left" class="center" style="padding-top: 30px">
								<a href="%%PERMALINK%%%%STOP%%hbsc=E%%$__campaign_id%%"><img width="80px" src="%%URL%%/wp-content/uploads/%%IMAGE%%" alt="" border="0" style="border-radius: 4px; max-width: 100px; display: block;" class="deviceWidth" /></a>
							</td>
						</tr>
					</table>
					<table align="right" width="80%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
						<tr>
							<td valign="bottom" style="font-size: 14px; color: #000000; font-weight: normal; text-align: left; font-family: Arial, Helvetica, sans-serif; line-height: 21px; vertical-align: top; padding: 30px 0 10px 0"><p style="mso-table-lspace:0;mso-table-rspace:0; margin:0">  
								%%TITLE%%
								%%DATE%%
							</td>
						</tr>
					</table>
				</td></tr>
				<tr><td>
					<table width="100%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
						<tr>
							<td valign="top" style="font-size: 14px; color: #000000; font-weight: normal; text-align: left; font-family: Arial, Helvetica, sans-serif; line-height: 21px; vertical-align: top; padding: 5px 0 20px 0"><p style="mso-table-lspace:0;mso-table-rspace:0; margin:0">  
								%%EXCERPT%%
								%%LINK%%
							</td>
						</tr>
					</table>
				</td></tr>
			</table>
		</td>';
	} else {
		$feature_article = '<td style="padding:0">
			<table align="left" width="49%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
				<tr>
					<td valign="top" align="left" class="center" style="padding-top:30px">
						<a href="%%PERMALINK%%%%STOP%%hbsc=E%%$__campaign_id%%"><img width="267" src="%%URL%%/wp-content/uploads/%%IMAGE%%" alt="" border="0" style="border-radius: 4px; width: 267px; display: block;" class="deviceWidth" /></a>
					</td>
				</tr>
			</table>                       
			<table align="right" width="49%" cellpadding="0" cellspacing="0" border="0" class="deviceWidth">
				<tr>
					<td valign="top" style="font-size: 14px; color: #000000; font-weight: normal; text-align: left; font-family: Arial, Helvetica, sans-serif; line-height: 21px; vertical-align: top; padding:30px 0 20px 0"><p style="mso-table-lspace:0;mso-table-rspace:0; margin:0">  
						%%TITLE%%
						%%DATE%%
						%%EXCERPT%%
						%%LINK%%
					</td>
				</tr>
			</table>
		</td>';
	}
 
	/* TITLE, SUBTITLE, EXCERPT, PERMALINK */
	$standard_article = '<td style="font-size: 13px; color: #000000; font-weight: normal; text-align: left; font-family: Arial, Helvetica, sans-serif; line-height: 21px; vertical-align: top; padding: 20px 0 20px 0">
		%%TITLE%%
		%%SUBTITLE%%
		%%DATE%%
		%%EXCERPT%%
		%%LINK%%
	</td>';

	$article = ($image) ? $feature_article : $standard_article ;

	switch ($button_config) {
		case 'button':
			$link = $button;
			break;
		case 'readmore':
			$link = ($linktext) ? $textlink : $readmore;
			break;
		default:
			$link = '';
			break;
	}
	
	$article = str_replace('%%LINK%%', $link, $article);

	if ($title) {
		$title = '<h1 style="font-size: 18px; font-weight: bold; line-height: 22px; margin:0; padding: 0 0 8px 0; margin: 0; color: #333333;">'.$title.'</h1>';
		$title = '<a href="%%PERMALINK%%%%STOP%%hbsc=E%%$__campaign_id%%" style="text-decoration: none;">'.$title.'</a>';
	}

	$excerpt = str_replace('<h1', '<h1 style="font-size: 18px; font-weight: bold; line-height: 22px; margin:0; padding: 0 0 8px 0; margin: 0"', $excerpt);

	$excerpt = str_replace('?xreplx', '%%STOP%%hbsc=E%%$__campaign_id%%', $excerpt);

	$date = ($date) ? '<p style="color: #999; padding: 0; margin:0 0 8px 0"><strong>'.date('F j, Y \a\t g:i a', strtotime($date)).'</strong></p>' : '';
	$subtitle = ($subtitle) ? '<p style="padding: 0; margin:0 0 8px 0"><strong>'.$subtitle.'</strong></p>' : '';
	$excerpt = '<p style="padding: 0; margin: 0 0 15px 0">'.$excerpt.'</p>';

	$needles = array('%%URL%%', '%%TITLE%%', '%%SUBTITLE%%', '%%DATE%%', '%%EXCERPT%%', '%%PERMALINK%%', '%%IMAGE%%', '%%LINKTEXT%%');
	$replace = array($url, $title, $subtitle, $date, $excerpt, $permalink, $image, $linktext);
	$article = str_replace($needles, $replace, $article);

	$article = str_replace('%%ARTICLE%%', $article, $article_wrapper);

	$underline = ($has_underline) ? $underline : '' ;
	$article = str_replace('%%UNDERLINE%%', $underline, $article);

	return $article;
}

// NO LONGER USED, NOT UPDATED TO FINAL SPECS!
function render_news_list($blocktitle, $articles) {

	// each $article = array(title, uber, uber2, primary, date, permalink, image);

	$article_loop = '';

	/* TITLE, UBER, UBER2, PRIMARY, DATE, PERMALINK, opt: IMAGE */
	$article = '<tr>' ;
	if ($blocktitle == 'Company News') {
		$article .= '<td valign="top" align="left" class="center" style="padding:0"><a href="%%PERMALINK%%"><img src="%%IMAGE%%" /></a></td>';
	}
	$article .= '<td valign="top" style="font-size: 12px; line-height: 16px; font-weight: bold; color: #747678">
	<strong><a href="%%PERMALINK%%" style="color: #000; font-size: 14px;">%%TITLE%%</a></strong><br/>
	<b style="margin:0; padding: 0; color: #d89533">%%UBER%%</b> > <b style="margin:0; padding: 0; color: #d89533">%%UBER2%%</b> > <b style="margin:0; padding: 0; color: #d89533">%%PRIMARY%%</b> | %%DATE%%
	</td>
	</tr>';

	foreach ($articles as $a) {
		$needles = array('%%TITLE%%', '%%UBER%%', '%%UBER2%%', '%%PRIMARY%%', '%%DATE%%', '%%PERMALINK%%', '%%IMAGE%%');
		$replace = array($a[title], $a[uber], $a[uber2], $a[primary], $a[date], $a[permalink], $a[image]);
		$article_loop .= str_replace($needles, $replace, $article);
	}

	$output = '<table width="580"  class="deviceWidth" border="0" cellpadding="0" cellspacing="0" align="center" bgcolor="#ffffff"><tr><td>
		<table width="100%" border="0" cellpadding="0" cellspacing="0" style="border: 1px solid #747678">
			<tr><td bgcolor="#747678" style="font-size: 18px; color: #fff; line-height: 27px; padding: 2px 15px">'.$blocktitle.'</td></tr>
			<tr><td>
				<table width="100%" cellpadding="7"><tr><td>
					<table width="550" border="0" cellpadding="0" cellspacing="0" align="center" class="deviceWidth" bgcolor="#ffffff">
						'.$article_loop.'
					</table>
				</td></tr></table>
			</td></tr></table>
	</td></tr></table>';

	return $output;

}
