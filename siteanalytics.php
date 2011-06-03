<?php
/*
Plugin Name: Site Analytics for WordPress
Plugin URI: http://www.godaddy.com/hosting/website-analytics.aspx?isc=WPSA1
Description: Add Site Analytics to your WordPress blog. This plugin will contact the Site Analytics servers and get your script. You need a <a href="http://www.godaddy.com/hosting/website-analytics.aspx?isc=WPSA1" title="Visit Site Analytics">Site Analytics</a> account to use it.
Version: 1.4.3
Author: GoDaddy
License: GPL 2
*/

$sa_test_mode = false;

require_once( ABSPATH . 'wp-content/plugins/site-analytics/sa-get-script.php' );

if ( ! class_exists( 'adminsiteanalytics' )) {

	class adminsiteanalytics {

		function addConfigPage() {

			global $wpdb;

			if ( function_exists('add_submenu_page')) {
				add_submenu_page('plugins.php', 'Site Analytics Configuration', 'Site Analytics', 1, basename(__FILE__), array('adminsiteanalytics','configPage'));
			}
		}

		function configPage() {

			$sa_domain_not_found = false;
			$sa_proxy_error = false;
			$script = '';
			$save_reqd = false;
			$sa_admin = '0';
			$sa_exits = '0';

			if ( isset( $_POST['Save'] )) {
				if ( isset( $_POST['saScript'] ) && !empty( $_POST['saScript'] )) {
					$script = $_POST['saScript'];
					if ( $script[0] == '<' )
					{
						delete_option( 'sa_script' );
						add_option( 'sa_script', $script, '', 'yes' );
						$script = stripslashes( $script );
					}
				}
				delete_option( 'sa_admin' );
				if ( isset( $_POST['track_admin'] )) {
					$sa_admin = '1';
					add_option( 'sa_admin', '1', 'Track admin pages', 'yes' );
				}
			} else if ( isset( $_POST['Clear'] )) {
				$script = '';
				delete_option( 'sa_script' );
				delete_option( 'sa_admin' );
			} else if ( isset( $_POST['Ask'] )) {
				$script = sa_get_script( get_option('siteurl'), $sa_proxy_error, $sa_domain_not_found );
				delete_option( 'sa_admin' );
				delete_option( 'sa_exits' );
				if ( isset( $_POST['track_admin'] )) {
					$sa_admin = '1';
					add_option( 'sa_admin', '1', 'Track admin pages', 'yes' );
				}
				$save_reqd = true;
			} else {
				$script = stripslashes( get_option( 'sa_script' ));
				$sa_admin = get_option( 'sa_admin' );
			}
			if (( true === $sa_proxy_error ) || ( true === $sa_domain_not_found )) {
				$message = $script;
			} else if (( '' !== $script ) && ( true === $save_reqd )) { 
				$message = 'Be sure to <b>save the changes!</b>';
			} else {
				$message = 'Site Analytics is setup and ready to go!';
			}
			echo '<div class="wrap"><h2>Site Analytics Configuration</h2>'.
				'   <form style="width:100%;" action="" method="post" id="sa_script_form">'.
				'       <table width="100%" cellspacing="0" cellpadding="4" border="0" class="tborder">'.
				'           <thead>'.
				'               <tr class=""><td><b>Site Analytics JavaScript</b></td></tr>'.
				'               <tr class=""><td>'.$message.'</td></tr>'.
				'           </thead>'.
				'           <tbody>'.
				'               <tr><td><input type="checkbox" id="track_admin" name="track_admin"'.($sa_admin=='1'?' checked="yes"/>':'/>').'&nbsp;<b>Track admin pages</b></td></tr>'.
				'               <tr><td><textarea name="saScript" rows="15" cols="100">'.$script.'</textarea></td></tr>'.
				'           </tbody>'.
				'       </table>'.
				'       <p class="submit">';
			if ( function_exists( 'sa_get_script' )) {
				echo
				'           <input type="submit" name="Ask" value="Get Site Analytics Script" />';
			}
				echo
				'           <input type="submit" name="Clear" value="Clear" />'.
				'           <input type="submit" name="Save" value="Save" />'.
				'       </p>'.
				'       </center>'.
				'   </form>'.
				'</div>';
		}
	} // End class adminsiteanalytics
}

if ( !class_exists( 'filtersiteanalytics' )) {

	class filtersiteanalytics {

		function addsiteanalyticsScript() {

			global $version;

			if (( $opt_script = stripslashes( stripslashes( get_option( 'sa_script' )))) == '' ) return;
			echo( "\n\n".'<!-- Site Analytics plugin v'.$version.' for Wordpress 2.6 - 3.0 (http://www.godaddy.com/hosting/website-analytics.aspx?isc=WPSA1)(Begin) -->'."\n" );
			$page = $_SERVER['PHP_SELF'];
			$sa_admin = get_option( 'sa_admin' );
			if ( !strpos( $page, 'wp-admin' ) || ( $sa_admin == '1' ))
			{
				echo( '<script type="text/JavaScript">'."\n".'var sa_currentPage=\''.$_SERVER['PHP_SELF'].'\';'."\n".
				      'var sa_admin='.((get_option( 'sa_admin' ) == '1' )?'true':'false').";\n".
				      '</script>'."\n".$opt_script );
			}
			echo( "\n<!-- Site Analytics plugin v".$version." for Wordpress 2.6 - 3.0 (End) -->\n\n");
		}
	} // End class filtersiteanalytics
}

$version = "1.3";

$saf = new filtersiteanalytics();

// add the menu item to the admin interface
add_action( 'admin_menu', array( 'adminsiteanalytics', 'addConfigPage' ));

// add the footer so the javascript is loaded in admin and blog pages.
add_action( 'wp_footer', array( 'filtersiteanalytics', 'addsiteanalyticsScript' ));
add_action( 'admin_footer', array( 'filtersiteanalytics', 'addsiteanalyticsScript' ));
?>