<?php
/*
Plugin Name: CRM4Wordpress
Description: This integrates Wordpress with an instance of Batchbook, CRM software. Everytime someone leaves a comment on your site, a new record is created in batchbook with the contact details, related tags and categories of that post. You must first enable your <a href="../wp-admin/options-general.php?page=CRM4Wordpress">Settings</a> for the plugin to work.
Author: <a href="http://www.maxrover.co.za">Max Rover Research</a> | <a href="../wp-admin/options-general.php?page=CRM4Wordpress">Settings</a>
Version: 1.1

== CHANGELOG v1.1 ==

Support for Wordpress 2.7

*/

/******************************************************************************

My plugin is released under the GNU General Public License (GPL)
http://www.gnu.org/licenses/gpl.txt

Copyright 2010  Max Rover  (email : contact@maxrover.co.za)

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
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
The license is also available at http://www.gnu.org/copyleft/gpl.html

*********************************************************************************/
	// Add deactivation hook

	register_deactivation_hook ( __FILE__, 'batchbook_plugin_deactivate' );
	// Remove activation options.
	function batchbook_plugin_deactivate() 
	{
		delete_option("batchbook_api_key");
		delete_option("batchbook_url");
		delete_option("batchbook_admin_email");
	}	

	//Admin menu hook
	add_action('admin_menu', 'batchbook_plugin_menu');
	//Add menu options to the Settings section
	function batchbook_plugin_menu() 
	{
	  add_options_page('CRM4Wordpress Settings', 'CRM4Wordpress', 'manage_options', 'CRM4Wordpress', 'batchbook_plugin_options');
	}

	function batchbook_plugin_options() 
	{
		if (!current_user_can('manage_options'))  
		{
			wp_die( __('You do not have sufficient permissions to access this page.') );
	  	}

		//Retrieve values from database
		$api_key = get_option( 'batchbook_api_key' );
		$url = get_option('batchbook_url');
		$email = get_option('batchbook_admin_email');

		if( isset($_POST['save']) && $_POST['save'] == 'Y' ) 
		{
			//Persist the values to the database.
			$api_key = $_POST['batchbook_api_key'];
			$url = $_POST['batchbook_url'];
			$email = $_POST['batchbook_admin_email'];
			
        	// Save the posted value in the database
        	update_option('batchbook_api_key', $api_key);
			update_option('batchbook_url', $url);
			update_option('batchbook_admin_email', $email);

			//Display ok message
			//echo '<div class="updated"><p><strong>' . _e('settings saved.', 'menu-test' ) . '</strong></p></div>';
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}
?>
			<!-- BatchBook settings admin panel -->	
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2>BatchBook Settings</h2>
				<h3><?php _e('Configuration Settings'); ?></h3>
				<form action="" method="post" id="frmBatchBookSettings">
					<input type="hidden" name="save" value="Y">
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label><?php _e('API Key'); ?></label>
							</th>
							<td>
								<input name="batchbook_api_key" type="text" id="batchbook_api_key" value="<?php echo $api_key; ?>" class="regular-text code" />
								(<?php _e('<a href="http://batchblue.com/product-info.html">What is this?</a>'); ?>)
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label><?php _e('Web Address (URL)'); ?></label>
							</th>
							<td>
								<input name="batchbook_url" type="text" id="batchbook_url" value="<?php echo $url; ?>" class="regular-text code" />
								<span class="description"><?php _e('<code>Example - https://acme.batchbook.com</code>'); ?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label><?php _e('Plugin Administrator Email'); ?></label>
							</th>
							<td>
								<input name="batchbook_admin_email" type="text" id="batchbook_admin_email" value="<?php echo $email; ?>" class="regular-text code" />
							</td>
						</tr>				
					</table>
					<p class="submit">
						<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
					</p>
				</form>
			</div>
<?php
	}
?>
