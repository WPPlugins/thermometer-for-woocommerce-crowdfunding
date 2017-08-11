<?php
/*
Plugin Name: Thermometer for WooCommerce Crowdfunding
Description: This plugin REQUIRES that the Booster for WooCommerce plugin be installed and the Crowdfunding module enabled. This plugin lets you create a thermometer for a Crowdfunding WooCommerce product. Insert [wcj_product_crowdfunding_thermometer product_id="12345" show_date="true|FALSE"] in your posts, pages or widgets.
Version: 1.0.1
Author: Daniel Bair
Author URI: http://danielbair.com/
Text Domain: wcj-thermometer
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

function wcj_thermometer_install() {
	global $wpdb;
	$opt_name = 'wcj_thermometer_page_id';
	$opt_val = get_option( $opt_name, null );
	if ($opt_val == null) {
		$opt_val = wp_insert_post(array('post_title'=>'Widget', 'post_name' => 'widget-php', 'post_status' => 'publish', 'post_type'=>'page', 'post_content'=>''));
		update_option( $opt_name, $opt_val );
	}
}

function wcj_thermometer_network_install($networkwide) {
	global $wpdb;

	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				wcj_thermometer_install();
			}
			switch_to_blog($old_blog);
			return;
		}
	}
	wcj_thermometer_install();
}

register_activation_hook( __FILE__ ,'wcj_thermometer_network_install');

function wcj_product_crowdfunding_thermometer_display_content($attrs) {

	if ( get_option( 'wcj_crowdfunding_enabled' ) == 'yes' ) {

		global $post, $product;

		if (isset($attrs['product_id']) && is_numeric($attrs['product_id'])) {
			$id = $attrs['product_id'];
			$product = wc_get_product($id);
		}
		else {
			return __("<br><b>ERROR: Please specify a product_id from the database.</b>", 'wcj-thermometer' );
		}

		$plugin_url = plugins_url( '', __FILE__ );

		if ($product == null) {
			return __("<br><b>ERROR: There is no matching product_id $id in the database.</b>", 'wcj-thermometer' );
		}
		else {

			$project_idx = $id;
			$post = get_post( $id );
			$thermo_height = "200";

			$project_time = do_shortcode('[wcj_product_crowdfunding_time_remaining product_id="$id"]');
			$project_goal = do_shortcode('[wcj_product_crowdfunding_goal product_id="$id"]');
			$project_goal_num = do_shortcode('[wcj_product_crowdfunding_goal product_id="$id" hide_currency="yes"]');
			if (strlen($project_goal)>=120) {
				$project_goal = substr($project_goal, 0, strpos($project_goal, '.'))."</span>";
			}
			$project_needed = do_shortcode('[wcj_product_crowdfunding_deadline product_id="$id"]');
			if (strtotime( $project_needed )) {
				$project_needed = date( 'd M Y', strtotime( $project_needed ) );
			}

			//monthly calculator
			/*
			if (strtolower($project_needed)=="monthly") {
				$sum = $db->db_query ( "SELECT SUM(pdd_donator_sum) AS SUMM FROM project_donated WHERE pdd_project_idx = '$project_idx' AND pdd_donator_time >= '".date('Y-m')."-01 00:00:00' " );
			}
			else{
				$sum = $db->db_query ( "SELECT SUM(pdd_donator_sum) AS SUMM FROM project_donated WHERE pdd_project_idx = '$project_idx' " );
			}
			$sum_rs = $db->db_fetch_array ( $sum );

			$SUMM = $sum_rs ['SUMM'];
			if ($SUMM == "") {
				$SUMM = "0";
			}
			else {
				$SUMMsub = substr($SUMM, 0, strpos($SUMM, "."));
				if ($SUMMsub != "") {
					$SUMM = $SUMMsub;
				}
			}
			*/

			$project_received = do_shortcode('[wcj_product_total_orders_sum product_id="$id"]');
			$project_received_num = do_shortcode('[wcj_product_total_orders_sum product_id="$id" hide_currency="yes"]');
			if (strlen($project_received)>=120) {
				$project_received = substr($project_received, 0, strpos($project_received, '.'))."</span>";
			}

			//matching grant calculator
			/*
			if ($project_grant != '') {
				if (strtolower($project_needed)=="monthly") {
					$sum = $db->db_query ( "SELECT SUM(pdd_donator_match) AS SUMM FROM project_donated WHERE pdd_project_idx = '$project_idx' AND pdd_donator_time >= '".date('Y-m')."-01 00:00:00' " );
				}
				else {
					$sum = $db->db_query ( "SELECT SUM(pdd_donator_match) AS SUMM FROM project_donated WHERE pdd_project_idx = '$project_idx' " );
				}
				$sum_rs = $db->db_fetch_array ( $sum );
				$SUMM = $sum_rs ['SUMM'];
				if ($SUMM == "") {
					$SUMM = "0";
				}
				$project_goal = ($project_goal + $project_grant);
				$project_received = ($project_received + $SUMM);
			}
			*/

			//$project_goal_to_go = $project_goal_num - $project_received_num;
			$project_goal_to_go = do_shortcode('[wcj_product_crowdfunding_goal_remaining product_id="$id" hide_currency="yes"]');

			//calculate thermometer fill
			if ( ! empty($project_goal) ) {
				$thermo_fill = ceil( ( $project_received_num / $project_goal_num ) * $thermo_height );
			}
			else {
				$thermo_fill = "0";
			}

			//set fill color to green if goal reached otherwise use yellow if over half way or red if up to half way
			if ($thermo_fill >= $thermo_height) {
				$thermo_fill = $thermo_height;
				$thermo_fill_image = "background-image:url('".$plugin_url."/images/widget/therm-bottom-green.jpg');";
				$thermo_fill_color = "background-color:#96bc5e; border-left:solid 1px #74a050;border-right:solid 1px #74a050;"; //green
			}
			else {
				if ( ( $project_received_num / $project_goal_num ) >= ".5") {
					$thermo_fill_image = "background-image:url('".$plugin_url."/images/widget/therm-bottom-yellow.jpg');";
					$thermo_fill_color = "background-color:#ede583; border-left:solid 1px #e1d22d;border-right:solid 1px #e1d22d;"; //yellow
				}
				else {
					$thermo_fill_image = "background-image:url('".$plugin_url."/images/widget/therm-bottom-red.jpg');";
					$thermo_fill_color = "background-color:#d27878; border-left:solid 1px #ba3e3e;border-right:solid 1px #ba3e3e;"; //red
				}
			}

			/*
			if (isset($_REQUEST ['single'])) {
				if (isset($_REQUEST ['goal'])) {
					$donate_link = "/invest/?pid=$project_idx&goal=1";
				} else {
					$donate_link = "/invest/?pid=$project_idx";
				}
			} else {
				$donate_link = "/invest/#j$project_idx";
			}
			*/
			$donate_link = get_permalink( $post );

			$wcjcft_output = '
			<div style="overflow: hidden; width:225px; height:300px; background:white; margin:0; font-family:Arial, Helvetica, sans-serif !important; font-size:12px; display:block;">
				<div style="float:left; width:73px; height:234px; background-image:url(\''.$plugin_url.'/images/widget/therm-bg.jpg\'); background-repeat:no-repeat; position:relative;">
					<div style="height:'.$thermo_fill.'px; width:14px; '.$thermo_fill_color.' max-height:'.$thermo_height.'px; position:absolute; left:38px; bottom:0px;"></div>
				</div>
				<div style="float:left; width:152px; height:234px;">
					<div style="width:152px; height:65px; background-image:url(\''.$plugin_url.'/images/widget/therm-hdr.jpg\'); background-repeat:no-repeat;">&nbsp;</div>
					<div style="width:152px; height:169px; background-image:url(\''.$plugin_url.'/images/widget/goal-bg.jpg\'); background-repeat:no-repeat;">
						<div style="padding:15px 10px 0px 8.5px; text-align:center; font-size:30px; color:#7190c5;">'.$project_goal.'</div>
						<div style="font-size:12px; padding:0px 10px 0px 8.5px; color:#a0b081; text-align:center">'.__("Project Goal", 'wcj-thermometer' ).'</div>';
			if (isset($attrs['show_date']) && ($attrs['show_date']=="true")) {
				$wcjcft_output .= '
						<div style="font-size:12px; padding:10px 10px 0px 8.5px; color:#a0b081; text-align:center">'.__("Needed by", 'wcj-thermometer' ).'</div>
						<div style="padding:5px 10px 0px 8.5px; text-align:center; font-size:22px; color:#7190c5;">'.$project_needed.'</div>';
			}
			else {
				$wcjcft_output .= '
						<div style="padding:15px 10px 0px 8.5px; text-align:center; font-size: 30px; color:#7190c5;">'.$project_received.'</div>
						<div style="font-size:12px; padding:0px 10px 0px 8.5px; color:#a0b081; text-align:center">'.__("Received so far", 'wcj-thermometer' ).'</div>';
			}
			$wcjcft_output .= '
					</div>
				</div>
				<div style="clear:both; width:225px; height:67px;">
					<div style="float: left; width: 92px; height: 67px; '.$thermo_fill_image.' background-repeat: no-repeat; font-size: 9px;">&nbsp;</div>
					<div style="float: left; width: 133px; height: 67px; background-image: url(\''.$plugin_url.'/images/widget/bottom-right.jpg\'); background-repeat: no-repeat; font-size: 9px;">&nbsp;
						<a href="'.$donate_link.'" target="_blank"><img src="'.$plugin_url.'/images/widget/donate-button.png" alt="'.__("Donate Button", 'wcj-thermometer' ).'" style="border: 0;"></a>
					</div>
				</div>
			</div>';
			return $wcjcft_output;
		}
	}
	else {
		return __('<b>ERROR</b><br>This <i>REQUIRES</i> the Booster for WooCommerce plugin installed and the Crowdfunding module enabled.', 'wcj-thermometer' );
	}
}

add_shortcode('wcj_product_crowdfunding_thermometer', 'wcj_product_crowdfunding_thermometer_display_content');

function wcj_thermometer_iframe_widget() {
	$opt_name = 'wcj_thermometer_page_id';
	$opt_val = get_option( $opt_name );
	if( is_page( intval($opt_val) ) ) {

		if (isset($_REQUEST ['pid']) && is_numeric($_REQUEST ['pid'])) {
			$product_id = $_REQUEST ['pid'];
		}
		else {
			if (isset($_REQUEST ['id']) && is_numeric($_REQUEST ['id'])) {
				$product_id = $_REQUEST ['id'];
			}
		}
		if (isset($_REQUEST ['showDate'])) {
			$needed_by = 'show_date="true"';
		}
		else {
			$needed_by = '';
		}

		// Get widget HTML
		ob_start();
			echo do_shortcode('[wcj_product_crowdfunding_thermometer product_id="'.$product_id.'" '.$needed_by.']');
		$widget_html = ob_get_clean();

		// Make Valid HTML5
		$widget_html = "<!doctype html><meta charset=utf-8><title>Thermometer: ".get_the_title()."</title>".$widget_html;

		// Output and exit
		die( $widget_html );
	}
}

add_action( 'template_redirect', 'wcj_thermometer_iframe_widget' );

if ( is_admin() ) {
	function wcj_thermometer_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Thermometer for WooCommerce', 'woocommerce-thermometer' ),
			__( 'Thermometer', 'woocommerce-thermometer' ) ,
			'manage_woocommerce',
			'wcj-thermometer',
			'wcj_thermometer_admin_page'
		);
	}

	function wcj_thermometer_admin_page() {
		//must check that the user has the required capability
		if (!current_user_can('manage_woocommerce')) {
			wp_die( __('You do not have sufficient permissions to access this page.', 'wcj-thermometer' ) );
		}
		if ( get_option( 'wcj_crowdfunding_enabled' ) == 'yes' ) {

			// variables for the field and option names
			$opt_name = 'wcj_thermometer_page_id';
			$hidden_field_name = 'wcj_submit_button';
			$data_field_name = 'page_id';

			// Read in existing option value from database
			$opt_val = get_option( $opt_name );

			// See if the user has posted us some information
			// If they did, this hidden field will be set to 'Y'
			if( isset($_POST[ $hidden_field_name ]) && sanitize_text_field($_POST[ $hidden_field_name ]) == 'Y' ) {

				check_admin_referer( 'wcj_thermometer_set_widget_page' );

				// Read their posted value
				$opt_val = intval($_POST[ $data_field_name ]);

				// Save the posted value in the database
				update_option( $opt_name, $opt_val );

				// Put a "settings saved" message on the screen
				?>
				<div class="updated"><p><strong><?php _e('settings saved.', 'wcj-thermometer' ); ?></strong></p></div>
				<?php

			}
			// the permalink to the selected page for the external iframe widget
			$the_base = get_permalink($opt_val);

			// Now display the settings editing screen
			echo '<div class="wrap">';
			echo "<h2>" . __( 'Thermometer for WooCommerce', 'wcj-thermometer' ) . "</h2>";
			// settings form
			?>
			<form name="wcj_thermometer_settings_form" method="post" action="">
			<?php wp_nonce_field( 'wcj_thermometer_set_widget_page' ); ?>
			<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
			<p><?php _e("Thermometer iframe embed page:", 'wcj-thermometer' ); ?>
			<?php wp_dropdown_pages(array('depth' => 3, 'child_of' => 0,'selected' => $opt_val, 'echo' => 1,'name' => $data_field_name, 'id' => $data_field_name)); ?>
			<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'wcj-thermometer' ) ?>" />
			<i><?php _e("This is the front end page that will be used for displaying the thermometer widget in an iframe for sharing on other websites.", 'wcj-thermometer' ); ?></i>
			</p>
			</form>
			<hr />
			<script language="javascript">
			function wcj_thermometer_update_view(the_id) {
				if (document.getElementById("showDate-"+the_id).checked) {
					document.getElementById("iframe-widget-"+the_id).src = "<?php echo $the_base; ?>?pid="+the_id+"&showDate";
					document.getElementById("textarea-shortcode-"+the_id).value = '[wcj_product_crowdfunding_thermometer product_id="'+the_id+'" show_date="true"]';
					document.getElementById("textarea-iframe-"+the_id).value = '<iframe id="iframe-widget" src="<?php echo $the_base; ?>?pid='+the_id+'&showDate" height="320" width="240" frameborder="0" scrolling="no"></iframe>';
				}else{
					document.getElementById("iframe-widget-"+the_id).src = "<?php echo $the_base; ?>?pid="+the_id;
					document.getElementById("textarea-shortcode-"+the_id).value = '[wcj_product_crowdfunding_thermometer product_id="'+the_id+'"]';
					document.getElementById("textarea-iframe-"+the_id).value = '<iframe id="iframe-widget" src="<?php echo $the_base; ?>?pid='+the_id+'" height="320" width="240" frameborder="0" scrolling="no"></iframe>';
				}
			}
			</script>
			<?php
			echo '<div class="content">';
			$loop = new WP_Query( array( 'post_type' => array('product'), 'post_status' => array('publish'), 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
			while ( $loop->have_posts() ) : $loop->the_post();
				$the_id = get_the_ID();
				$the_link = $the_base."?pid=".$the_id;
				$project_goal = do_shortcode('[wcj_product_crowdfunding_goal product_id="$the_id" hide_currency="yes"]');
				if ($project_goal != 0) {
					echo "<div style='display: inline-block; padding: 10px; border: 1px solid lightgrey;'>";
					echo '<input id="showDate-'.$the_id.'" type="checkbox" onclick="wcj_thermometer_update_view('.$the_id.');">'.__("Show needed by date?", 'wcj-thermometer' );
					echo "&nbsp;&nbsp;<a href='post.php?post=".$the_id."&action=edit' alt='Edit product'>".__("Edit product", 'wcj-thermometer' )."</a><br>";
					echo '<iframe id="iframe-widget-'.$the_id.'" src="'.$the_link.'" height="320" width="240" frameborder="0" scrolling="no"></iframe>';
					echo "<h1>".get_the_title()."</h1>";
					echo "<h4>".__("SHORTCODE for displaying in posts, pages, &amp; widgets", 'wcj-thermometer' )."</h4>";
					echo '<textarea id="textarea-shortcode-'.$the_id.'" style="width: 400px; height: 44px; resize: none; overflow: hidden;" onClick="this.select();" readonly="1">[wcj_product_crowdfunding_thermometer product_id="'.$the_id.'"]</textarea><br>';
					echo "<h4>".__("IFRAME EMBED CODE for displaying on external websites", 'wcj-thermometer' )."</h4>";
					echo '<textarea id="textarea-iframe-'.$the_id.'" style="width: 400px; height: 64px; resize: none; overflow: hidden;" onClick="this.select();" readonly="1">&lt;iframe id="iframe-widget" src="'.$the_link.'" height="320" width="240" frameborder="0" scrolling="no"&gt;&lt;/iframe&gt;</textarea><br>';
					echo "<br>";
					echo "</div>";
				}
			endwhile; wp_reset_query();
			echo "</div>";
			echo "</div>";
		}
		else {
			wp_die(__('<b>ERROR</b><br>This <i>REQUIRES</i> the <a href="http://booster.io">Booster for WooCommerce plugin</a> installed and the <a href="admin.php?page=wc-settings&tab=jetpack&wcj-cat=products&section=crowdfunding">Crowdfunding module</a> enabled.', 'wcj-thermometer' ),'ERROR');
		}
	}
	add_action( 'admin_menu', 'wcj_thermometer_menu', 100 );
}
?>
