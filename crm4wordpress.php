<?php
/*
Plugin Name: CRM4Wordpress
Description: When someone posts a comment on your blog his/her details will be captured in the BatchBook CRM system. This is done by using the BatchBook API's (<a href="http://developer.batchblue.com">http://developer.batchblue.com</a>). Currently the users name, email, website, date of comment and link/url of comment is passed to the BatchBook system.
Author: <a href="http://www.maxrover.co.za">Max Rover Research</a>
Version: 1.2

== CHANGELOG v1.1 ==
- Added filtering to only allow data posted to BatchBook for comments that have been approved.
- Added administative module to configure the BatchBook url and API token ID from the Wordpress admin dashboard.

== CHANGELOG v1.2 ==
- Combined the admin plugin with the BatchBook post comment plugin.
- Added basic error handling that will be forwareded via email to the plugin administrator.
- Replaced the Google API call with the Rapleaf API call.
- Added Rapleaf configuration to the settings admin console.
- Added Super Tag created fields to the settings admin console.
- Commented the curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); commands that do not work when in safe_mode or an open_basedir is set.
- Create a Rapleaf class wrapper.

Support for Wordpress 2.7

*/

/*
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
*/

	/******************************************************************************** 
	****************** CRM4Wordpress Configuration Console Start ********************
	*********************************************************************************/
	// Add plugin initialization hook
	function crm4wordpress_init() {
		crm4wordpress_admin_warnings();
	}
	add_action('init', 'crm4wordpress_init');
	
	function crm4wordpress_admin_warnings()
	{
		if ((!get_option('crm4wp_bb_api_key') || 
			!get_option('crm4wp_bb_account') || 
			!get_option('crm4wp_bb_admin_email')) && 
			!isset($_POST['save']) ) 
		{
			function crm4wordpress_warning() {
				echo "
				<div id='crm4wordpress-warning' class='updated fade'><p><strong>".__('CRM4Wordpress is almost ready.')."</strong> ".sprintf(__('All Batchbook Configuration <a href="../wp-admin/options-general.php?page=CRM4Wordpress">settings</a> must be configured for it to work.'), "plugins.php?page=akismet-key-config")."</p></div>
				";
			}
			add_action('admin_notices', 'crm4wordpress_warning');
			return;
		}
	}
	
	function LogError($method, $message)
	{
		$to = get_option( 'crm4wp_bb_admin_email');
		//If plugin admin email not set, use the Wordpress admin email
		if($to == '')
			$to = get_option('admin_email');
		if($to != '')
 			wp_mail($to, 'CRM4Wordpress Error', 'Error in method ' . $method . '. ' . $message , 'From: CRM4Wordpress Admin Console <' . $to . '>');		
	}

	// Add deactivation hook
	register_deactivation_hook ( __FILE__, 'batchbook_plugin_deactivate' );
	function batchbook_plugin_deactivate() 
	{
		delete_option("crm4wp_bb_api_key");
		delete_option("crm4wp_bb_account");
		delete_option("crm4wp_bb_admin_email");
		delete_option("crm4wp_rl_activate_api");
		delete_option("crm4wp_rl_api_key");
	}	

	//Admin menu hook
	add_action('admin_menu', 'batchbook_plugin_menu');
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
		$crm4wp_bb_api_key = get_option( 'crm4wp_bb_api_key' );
		$crm4wp_bb_account = get_option('crm4wp_bb_account');
		$crm4wp_bb_admin_email = get_option('crm4wp_bb_admin_email');
		//if($crm4wp_bb_admin_email == '')
			//$crm4wp_bb_admin_email = get_option('admin_email');
		$crm4wp_rl_activate_api = get_option('crm4wp_rl_activate_api');
		$crm4wp_rl_api_key = get_option('crm4wp_rl_api_key');

		if( isset($_POST['save']) && $_POST['save'] == 'Y' ) 
		{
			//Persist the values to the database.
			$crm4wp_bb_api_key = $_POST['crm4wp_bb_api_key'];
			$crm4wp_bb_account = $_POST['crm4wp_bb_account'];
			$crm4wp_bb_admin_email = $_POST['crm4wp_bb_admin_email'];
			if(isset($_POST['crm4wp_rl_activate_api']))
				$crm4wp_rl_activate_api = 'CHECKED';
			else
				$crm4wp_rl_activate_api = '';
			$crm4wp_rl_api_key = $_POST['crm4wp_rl_api_key'];
			
        	// Save the posted value in the database
        	update_option('crm4wp_bb_api_key', $crm4wp_bb_api_key);
			update_option('crm4wp_bb_account', $crm4wp_bb_account);
			update_option('crm4wp_bb_admin_email', $crm4wp_bb_admin_email);
			update_option('crm4wp_rl_activate_api', $crm4wp_rl_activate_api);
			update_option('crm4wp_rl_api_key', $crm4wp_rl_api_key);

			//Display ok message
			echo '<div id="message" class="updated fade"><p><strong>CRM4Wordpress settings saved.</strong></p></div>';
		}
?>
			<!-- BatchBook settings admin panel -->	
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2>CRM4Wordpress Settings</h2>
				<h3><?php _e('Batchbook Configuration'); ?></h3>
				<form action="" method="post" id="frmBatchBookSettings">
					<input type="hidden" name="save" value="Y">
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label><?php _e('API Key'); ?></label>
							</th>
							<td>
								<input name="crm4wp_bb_api_key" type="text" id="crm4wp_bb_api_key" value="<?php echo $crm4wp_bb_api_key; ?>" class="regular-text code" />
								(<?php _e('<a href="http://batchblue.com/product-info.html">What is this?</a>'); ?>)
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label><?php _e('User Account Name'); ?></label>
							</th>
							<td>
								<input name="crm4wp_bb_account" type="text" id="crm4wp_bb_account" value="<?php echo $crm4wp_bb_account; ?>" class="regular-text code" />
								<span class="description"><?php _e('<code>Example - acme.batchbook.com</code>'); ?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label><?php _e('Plugin Administrator Email'); ?></label>
							</th>
							<td>
								<input name="crm4wp_bb_admin_email" type="text" id="crm4wp_bb_admin_email" value="<?php echo $crm4wp_bb_admin_email; ?>" class="regular-text code" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label><?php _e('Super Tag "Commenter" Created?'); ?></label>
							</th>
							<td>
								<input name="crm4wp_bb_commenter_super_tag" type="text" id="crm4wp_bb_commenter_super_tag" value="<?php echo BatchBookSuperTagExists('commenter'); ?>" class="regular-text code" readonly="readonly" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label><?php _e('Super Tag "Commenter Links" Created?'); ?></label>
							</th>
							<td>
								<input name="crm4wp_bb_commenter_link_super_tag" type="text" id="crm4wp_bb_commenter_link_super_tag" value="<?php echo BatchBookSuperTagExists('commenter%20links'); ?>" class="regular-text code" readonly="readonly" />
							</td>
						</tr>
					</table>
					<br/>
					<h3><?php _e('Rapleaf Configuration'); ?></h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label><?php _e('Activate Rapleaf interface?'); ?></label>
							</th>
							<td align="left">
								<input name="crm4wp_rl_activate_api" type="checkbox" id="crm4wp_rl_activate_api" value="" <?php echo $crm4wp_rl_activate_api; ?> />
							</td>
						</tr>					
						<tr valign="top">
							<th scope="row">
								<label><?php _e('API Key'); ?></label>
							</th>
							<td>
								<input name="crm4wp_rl_api_key" type="text" id="crm4wp_rl_api_key" value="<?php echo $crm4wp_rl_api_key; ?>" class="regular-text code" />
								(<?php _e('<a href="https://www.rapleaf.com/developer/api_access">What is this?</a>'); ?>)
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

	function BatchBookSuperTagExists($supertag)
	{
		$api_key = get_option('crm4wp_bb_api_key');
		$account = get_option('crm4wp_bb_account');

		if(api_key != '' && $account != '')
		{	
			$service_url = "https://" . $account . "/service/super_tags/" . $supertag . ".xml";
	
			$curl = curl_init($service_url);
			curl_setopt($curl, CURLOPT_GET, 1);
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$curl_response = curl_exec($curl);
			$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
		
			if('200' == $code)
			{
				return 'Has been created.';
			}
			else if('404' == $code)
			{
				return 'Has NOT been created.';
			}
			else if('401' == $code)
			{
				//LogError('BatchBookSuperTagExists', 'Error Code: ' . $code .  ' Incorrect BatchBook API Key!');
				return 'Incorrect BatchBook API Key!';				
			}
			else if('302' == $code)
			{
				//LogError('BatchBookSuperTagExists', 'Error Code: ' . $code .  ' Incorrect BatchBook User Account Name!');
				return 'Incorrect BatchBook User Account Name!';				
			}
			else
			{
				LogError('BatchBookSuperTagExists', 'Error Code: ' . $code .  ' BatchBook API Super Tag - ' . $supertag . ' Created Check Failed!');
				return 'BatchBook API Call Failed.';
			}
		}
		else
		{
			return 'Cannot connect to BatchBook!';
		}	
	}

	/******************************************************************************** 
	***************** CRM4Wordpress Hooks For BatchBook Interface Start *************
	*********************************************************************************/
	// Add actions and filters
	add_action ( 'comment_post', 'bb_process_comment');
	add_action ( 'wp_set_comment_status', 'bb_process_comment');
	add_action ('edit_comment', 'bb_process_comment');
	//Process Comment
	function bb_process_comment($comment_ID)
	{
		global $wpdb;
		//Get BatchBook setting data.
		$crm4wp_bb_api_key = get_option( 'crm4wp_bb_api_key' );
		$crm4wp_bb_account = get_option('crm4wp_bb_account');

		if($crm4wp_bb_api_key != '' && $crm4wp_bb_account != '')
		{
			//Retrieve comment detail.
			$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID = '$comment_ID'"); //get_comment($comment_ID);
			//Check if any results returned.
			if($comment)
			{
				if($comment->comment_approved == 1) //Only allow approved comment data to be sent to BatchBook
				{
					//Split the comment author into name and surname.
					$fristName = getFirstname($comment->comment_author);
					$lastName = getLastname($comment->comment_author);
					$link = get_comment_link($comment);
					$person_id = BatchBookPersonFind($fristName, $comment->comment_author_email, $crm4wp_bb_api_key, $crm4wp_bb_account);
					//Check if the BatchBook person already created.
					if($person_id == 0)
					{
						//Create the BatchBook person.	
						BatchBookPersonCreate($comment->comment_post_ID, $fristName, $lastName, $comment->comment_author_email, $comment->comment_author_url, $comment->comment_date, $link, $crm4wp_bb_api_key, $crm4wp_bb_account);
						//sendMail($comment);
					}		
					else
					{
						//Update the supertag associated with the person
						BatchBookPersonSuperTagCreate($person_id, $comment->comment_date, $link, $crm4wp_bb_api_key, $crm4wp_bb_account);
						BatchBookPersonSoicialMediaTagsUpdate($person_id, $comment->comment_author_email, $crm4wp_bb_api_key, $crm4wp_bb_account);
					}
				}
			}
		}
		else
		{
			LogError('bb_process_comment', 'The CRM4Wordpress has been activated. Please ensure that all BatchBook configuration settings are set and that the super tags Commenter and Commenter Links are configured in BatchBook.');
		}
		return $comment_ID;
	}
	
	function getFirstname($comment_author)
	{
		$firstName = '';
		$author_parts = explode(" ", $comment_author);
		if(sizeof($author_parts) > 0)
			$firstName = $author_parts[0];
		
		return $firstName;
	}
	
	function getLastname($comment_author)
	{
		$lastName = '';
		$author_parts = explode(" ", $comment_author);
		if(sizeof($author_parts) > 0)
		{
			for($i = 1; $i < sizeof($author_parts); $i++)
			{
				$lastName .= $author_parts[$i];
				if($i != sizeof($author_parts)-1)
					$lastName .= ' ';
			}
		}
		return $lastName;		
	}
	
	function BatchBookPersonCreate($comment_post_id, $firstname, $lastname, $email, $website, $comment_date_created, $comment_link, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$service_url = "https://" . $crm4wp_bb_account . "/service/people.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,"person[first_name]=" . $firstname . "&person[last_name]=" . $lastname . "&person[notes]=Created via Wordpress comment post.");
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $crm4wp_bb_api_key . ":x");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		//curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		
		if($code == '201')
		{
			$id = BatchBookPersonGetId($curl_response);
			if($id > 0)
			{
				//Create location
				BatchBookPersonLocationCreate($id, $email, $website, $crm4wp_bb_api_key, $crm4wp_bb_account);
				//Create super tag containing comment creation date and url to comment
				Batchbookpersonsupertagcreate($id, $comment_date_created, $comment_link, $crm4wp_bb_api_key, $crm4wp_bb_account);
				//Create BatchBook tags for all Worpress categories associated with this post
				$categories = get_the_category($comment_post_id);
				if ($categories) {
					foreach($categories as $category) {
						//$tags .= $category->cat_name . ' | ';
						BatchBookPersonTagCreate($id, $category->cat_name, $crm4wp_bb_api_key, $crm4wp_bb_account);
					}
				}

				//Create BatchBook tags for all Worpress tags associated with this post
				$tags = get_the_tags($comment_post_id);
				if ($tags) {
					foreach($tags as $tag) {
						//$tags .= $tag->name . ' ';
						BatchBookPersonTagCreate($id, $tag->name, $crm4wp_bb_api_key, $crm4wp_bb_account);
					}
				}
				//Update supertag containing social media details for twitter, linkedin, flikr and facebook
				BatchBookPersonSoicialMediaTagsUpdate($id, $email, $crm4wp_bb_api_key, $crm4wp_bb_account);
			}
		}
		else
		{
			LogError('BatchBookPersonCreate', 'Error Code: ' . $code .  ' BatchBook API Create Person Failed!');
		}
	}
	
	function BatchBookPersonGetId($curl_response)
	{
		$hArray = explode("\n",$curl_response);
		if(sizeof($hArray) > 0)
		{
			$location_string = $hArray[8];
			$location_parts = explode("/",$location_string);
			$file_name = $location_parts[sizeof($location_parts)-1];
			$file_name_parts = explode(".",$file_name);
			if(sizeof($file_name_parts) > 0)
				return (int)$file_name_parts[0]; 
			else
				return 0;
		}
		else
		{
			return 0;
		}
	}
	
	function BatchBookPersonLocationCreate($id, $email, $website, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$service_url = "https://" . $crm4wp_bb_account . "/service/people/" . $id . "/locations.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,"location[label]=wordpress&location[email]=" . $email . "&location[website]=" . $website);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $crm4wp_bb_api_key . ":x");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		//curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($code != '201')
		{
			LogError('BatchBookPersonLocationCreate', 'Error Code: ' . $code .  ' BatchBook API Create Person Location Failed!');
		}		
	}
	
	function BatchBookPersonSuperTagCreate($id, $comment_date_created, $comment_url, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$service_url = "https://" . $crm4wp_bb_account . "/service/people/" . $id . "/super_tags/commenter.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS,"super_tag[date]=" . $comment_date_created . "&super_tag[link]=" . urlencode($comment_url));
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $crm4wp_bb_api_key . ":x");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($code != '200')
		{
			LogError('BatchBookPersonSuperTagCreate', 'Error Code: ' . $code .  ' BatchBook API Create Person Location Failed!');
		}		
	}
	
	function BatchBookPersonTagCreate($id, $tag_name, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$service_url = "https://" . $crm4wp_bb_account . "/service/people/" . $id . "/add_tag.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS,"tag=" . $tag_name);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $crm4wp_bb_api_key . ":x");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);	
		if($code != '200')
		{
			LogError('BatchBookPersonTagCreate', 'Error Code: ' . $code .  ' BatchBook API Create Tag Failed!');
		}	
	}
	
	function BatchBookPersonSoicialMediaTagsUpdate($id, $email, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$twitter = '';
		$facebook = '';
		$flickr = '';
		$linkedin = '';
		if($email != '')
		{
			$crm4wp_rl_activate_api = get_option('crm4wp_rl_activate_api');
			$crm4wp_rl_api_key = get_option('crm4wp_rl_api_key');

			if($crm4wp_rl_activate_api != '' && $crm4wp_rl_activate_api == 'CHECKED')
			{
				if($crm4wp_rl_api_key != '')
				{
					$profile = new RapleafProfile($crm4wp_rl_api_key);
					$result = $profile->getData($email);
					$code = $result['status'];

					if ('200' == $code)//ok
					{
						$pmemberships = $result['memberships-primary'];
						foreach ($pmemberships as $pmembership) 
						{
							//twitter
							if(strpos($pmembership['profile_url'],'twitter') === false){
							}
							else
							{
								if($pmembership['exists'] == 'true')
								{
									$twitter = $pmembership['profile_url'];
								}
							}
							//facebook
							if(strpos($pmembership['profile_url'],'facebook') === false){
							}
							else
							{
								if($pmembership['exists'] == 'true')
								{
									$facebook = $pmembership['profile_url'];
								}
							}
							//flickr
							if(strpos($pmembership['profile_url'],'flickr') === false){
							}
							else
							{
								if($pmembership['exists'] == 'true')
								{
									$flickr = $pmembership['profile_url'];
								}
							}
							//linkedin
							if(strpos($pmembership['profile_url'],'linkedin') === false){
							}
							else
							{
								if($pmembership['exists'] == 'true')
								{
									$linkedin = $pmembership['profile_url'];
								}
							}
						}
					}
					else
					{
						LogError('BatchBookPersonSoicialMediaTagsUpdate', 'Error Code: ' . $code .  ' Rapleaf API Call Failed!');
					}
				}
			}
		}
		BatchBookCommenterLinksSuperTagCreate($id, $twitter, $facebook, $flickr, $linkedin, $crm4wp_bb_api_key, $crm4wp_bb_account);
	}
	
	function BatchBookCommenterLinksSuperTagCreate($id, $twitter_link, $facebook_link, $flickr_link, $linkedin_link, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$service_url = "https://" . $crm4wp_bb_account . "/service/people/" . $id . "/super_tags/commenter%20links.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS,"super_tag[twitter]=" . urlencode($twitter_link) . "&super_tag[facebook]=" . urlencode($facebook_link) . "&super_tag[flickr]=" . urlencode($flickr_link) . "&super_tag[linkedin]=" . urlencode($linkedin_link));
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $crm4wp_bb_api_key . ":x");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($code != '200')
		{
			LogError('BatchBookCommenterLinksSuperTagCreate', 'Error Code: ' . $code .  ' BatchBook API Super Tag Commenter Links Failed!');
		}		
	}
	
	function BatchBookPersonFind($firstname, $email, $crm4wp_bb_api_key, $crm4wp_bb_account)
	{
		$service_url = "https://" . $crm4wp_bb_account . "/service/people.xml?name=" . $firstname . "&email=" . $email;
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_GET, 1);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $crm4wp_bb_api_key . ":x");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($code != '200')
		{
			LogError('BatchBookPersonFind', 'Error Code: ' . $code .  ' BatchBook API Person Find Failed!');
			return 0;
		}
		
		if(sizeof($curl_response) > 0)
		{
			try
			{
			$xml = new SimpleXMLElement($curl_response);
			if(sizeof($xml->person) > 0)
				return $xml->person[0]->id;
			}
			catch(Excepton $e)
			{
				return 0;
			}
		}
		return 0;	
	}

	/******************************************************************************** 
	***************** CRM4Wordpress RapLeaf Helper Class ****************************
	*********************************************************************************/
	class RapleafProfile{
		var $api_key;
		var $url;
		var $status; 
	
		function RapleafProfile($api_key, $url= 'http://api.rapleaf.com/v2/person/') {
			$this->api_key = $api_key;
			$this->url = $url;	
			$this->email_id=$email_id;
		}	
		
		//he main function which is called to get all information
		function getData($email) {
			//assemble post_data string
			$base_url = $this->url . $email. '?api_key=' . $this->api_key . '&i=bmr';
			$response = $this->getRequest($base_url);
	
			//the return structure
			$result = array(
				'status'   => '',  //HTTP status code returned by the server
				'error'    => '',  //error message if there are any
				'basics' => array(),
				'memberships-primary' => array(),	
				'memberships-supplemental' => array(),		
				'reputation' => array(),				
			);
			
			$result['status'] = $this->status;
	
			if ($this->status == '200') { //OK
				$result['basics'] = $this->getBasics($response);		
				$result['memberships-primary'] = $this->getMemberships($response,'primary');	
				$result['memberships-supplemental'] = $this->getMemberships($response,'supplemental');	
				$result['reputation'] = $this->getReputation($response);				
			}elseif ($this->status == '201') {
				$result['error'] = 'This person has now been created. Check back shortly and we should have data.'.$response;
			}  
			elseif ($this->status == '400') {
				$result['error'] = 'Invalid email address.'.$response;
			} elseif ($this->status == '401') {
				$result['error'] = 'API key was not provided or is invalid.';
			} elseif ($this->status == '403') {
				$result['error'] = 'Your daily query limit has been exceeded. The default limit is 4,000.';
			} elseif ($this->status == '500') {
				$result['error'] = 'There was an unexpected error on our server. This should be very rare and if you see it please contact developer@rapleaf.com.';
			} 
			return $result;
		}	
		
		//Parse the xml response text to get basic details
		function getBasics($str) {
			$xml = simplexml_load_string($str);
			$result = array();
			$universities = array();
			$occupations = array();
			
			if(!is_null($xml->basics->universities->university)){
				foreach($xml->basics->universities->university as $university){
					$universities[] = strval($university);
				}
			}
			
			if(!is_null($xml->basics->occupations->occupation)){
				$count = 0;
				foreach($xml->basics->occupations->occupation as $occupation){
					
					if($occupation['job_title']) { 
						$occupations[$count]['job_title'] = strval($occupation['job_title']);
					} else {
						$occupations[$count]['job_title'] = NULL;
					}
					if($occupation['company']) {
						$occupations[$count]['company'] = strval($occupation['company']);
					} else {
						$occupations[$count]['company'] = NULL;
					}
					$count++;
				}
			}
	
			$result= array(
					'name' => $xml->basics->name, 
					'age' => $xml->basics->age,
					'gender' => $xml->basics->gender,
					'location' => $xml->basics->location,
					'earliest_known_activity' => $xml->basics->earliest_known_activity,
					'latest_known_activity' => $xml->basics->latest_known_activity,
					'num_friends' => $xml->basics->num_friends,		
					'universities' => $universities,
					'occupations' => $occupations,
					);
	
			return $result;
		}
	
		//Parse the xml response text to get Primary/Supplemental Membership details
		function getMemberships($str,$val) {
			$xml = simplexml_load_string($str);
			$result = array();
	
			if(!is_null($xml->memberships->$val->membership)){
				$count = 0;	
				foreach($xml->memberships->$val->membership as $membership){
							
					$result[$count]['site'] = strval($membership['site']);
					$result[$count]['exists'] = strval($membership['exists']);
					$result[$count]['profile_url'] = strval($membership['profile_url']);
					$count++;
				}
	
			}
			return $result;
		}		
		
		//Parse the xml response to get the Reputation details
		function getReputation($str) {
			$xml = simplexml_load_string($str);
			$result = array();
			$badges = array();
			
			if(!is_null($xml->reputation->badges->badge)){			
				foreach($xml->reputation->badges->badge as $badge){
						$badges[] = strval($badge);				
				}
			}
			
			$result= array(
					'score' => strval($xml->reputation->score), 
					'commerce_score' => strval($xml->reputation->commerce_score),
					'percent_positive' => strval($xml->reputation->percent_positive),
					'rapleaf_profile_url' => strval($xml->reputation->rapleaf_profile_url),
					'badges' => $badges,
					);
		
			return $result;
		}
			
		//cURL function to fetch the XML schema
		function getRequest( $url ){
			$ch      = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
			$content = curl_exec( $ch );
			$this->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close( $ch );
			return $content;
		}	
	}
?>
