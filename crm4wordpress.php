<?php
/*
Plugin Name: CRM4Wordpress Create Person
Description: When someone posts a comment on your blog his/her details will be captured in the BatchBook CRM system. This is done by using the BatchBook API's (<a href="http://developer.batchblue.com">http://developer.batchblue.com</a>). Currently the users name, email, website, date of comment and link/url of comment is passed to the BatchBook system.
Author: <a href="http://www.maxrover.co.za">Max Rover Research</a>
Version: 1.1

== CHANGELOG v1.1 ==
- Added filtering to only allow data posted to BatchBook for comments that have been approved.
- Added administative module to configure the BatchBook url and API token ID from the Wordpress admin dashboard.

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
	// Add activation hook
	register_activation_hook( __FILE__, 'batchbook_plugin_activate' );
	// Ensure that BatchBook configuration settings have been made.
	function batchbook_plugin_activate() 
	{
		$do_roll_back = false;
		$api_key = get_option( 'batchbook_api_key' );
		$url = get_option('batchbook_url');
		if(!isset($api_key) || !isset($url) || $api_key == '' || $url == '')
		{
			$do_roll_back = true;		
		}
		if($do_roll_back)
		{
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('The CRM4Wordpress plugin pre-requisites are not configured. Please ensure that the CRM4Wordpress Admin Console plugin is activated and that the required configuration settings have been entered.','CRM4Wordpress Plugin Activation Error');			
		}
	}

	// Add actions and filters
	add_action ( 'comment_post', 'bb_process_comment');
	add_action ( 'wp_set_comment_status', 'bb_process_comment');
	add_action ('edit_comment', 'bb_process_comment');
	//Process Comment
	function bb_process_comment($comment_ID)
	{
		global $wpdb;
		//Get BatchBook setting data.
		$api_key = get_option( 'batchbook_api_key' );
		$url = get_option('batchbook_url');
		$plugin_admin_email = get_option('batchbook_admin_email');
		//Retrieve comment detail.
		$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID = '$comment_ID'"); //get_comment($comment_ID);

		if($api_key != '' && $url != '')
		{
			//Check if any results returned.
			if($comment)
			{
				if($comment->comment_approved == 1) //Only allow approved comment data to be sent to BatchBook
				{
					//Split the comment author into name and surname.
					$fristName = getFirstname($comment->comment_author);
					$lastName = getLastname($comment->comment_author);
					$link = get_comment_link($comment);
					$person_id = BatchBookPersonFind($fristName, $comment->comment_author_email, $api_key, $url);
					//Check if the BatchBook person already created.
					if($person_id == 0)
					{
						//Create the BatchBook person.	
						BatchBookPersonCreate($comment->comment_post_ID, $fristName, $lastName, $comment->comment_author_email, $comment->comment_author_url, $comment->comment_date, $link, $api_key, $url);
						//sendMail($comment);
					}		
					else
					{
						//Update the supertag associated with the person
						BatchBookPersonSuperTagCreate($person_id, $comment->comment_date, $link, $api_key, $url);
						BatchBookPersonSoicialMediaTagsUpdate($person_id, $comment->comment_author_url, $api_key, $url);
					}
				}
			}
		}
		else
		{
			if($plugin_admin_email != '')
			{
				if($api_key == '')
					wp_mail($plugin_admin_email, 'BatchBook Create Person Plugin Error', 'The BatchBook API Key is not set. Please use the BatchBook Admin Console to set this value.', 'From: BatchBook Admin Console <' . get_option('admin_email') . '>');
				if($url == '')
					wp_mail($plugin_admin_email, 'BatchBook Create Person Plugin Error', 'The BatchBook Web Address (URL) is not set. Please use the BatchBook Admin Console to set this value.', 'From: BatchBook Admin Console <' . get_option('admin_email') . '>');
			}
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
	
	function sendMail($comment)
	{
		$body .= $comment->comment_ID;
		$body .= ' | ';
		$body .= $comment->comment_post_ID;
		$body .= ' | ';
		$body .= $author_array[0];
		$body .= ' - ';
		$body .= $author_array[1];
		$body .= ' | ';		
		$body .= $comment->comment_author_email;
		$body .= ' | ';
		$body .= $comment->comment_author_url;
		$body .= ' | ';
		$body .= $comment->comment_date;
		$body .= ' | ';
		$body .= $comment->comment_approved;
		
		$friends = 'brian.heunis@gmail.com';
    	mail($friends, "comment info", $body);		
	}

	function BatchBookPersonCreate($comment_post_id, $firstname, $lastname, $email, $website, $comment_date_created, $comment_link, $api_key, $url)
	{
		$service_url = $url . "/service/people.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,"person[first_name]=" . $firstname . "&person[last_name]=" . $lastname . "&person[notes]=Created via Wordpress comment post.");
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		
		//Create a location for the newly created person.
		if($code == '201')
		{
			$id = BatchBookPersonGetId($curl_response);
			if($id > 0)
			{
				//Create location
				BatchBookPersonLocationCreate($id, $email, $website, $api_key, $url);
				//Create super tag containing comment creation date and url to comment
				Batchbookpersonsupertagcreate($id, $comment_date_created, $comment_link, $api_key, $url);
				//Create BatchBook tags for all Worpress categories associated with this post
				$categories = get_the_category($comment_post_id);
				if ($categories) {
					foreach($categories as $category) {
						//$tags .= $category->cat_name . ' | ';
						BatchBookPersonTagCreate($id, $category->cat_name, $api_key, $url);
					}
				}

				//Create BatchBook tags for all Worpress tags associated with this post
				$tags = get_the_tags($comment_post_id);
				if ($tags) {
					foreach($tags as $tag) {
						//$tags .= $tag->name . ' ';
						BatchBookPersonTagCreate($id, $tag->name, $api_key, $url);
					}
				}
				//Update supertag containing social media details for twitter, linkedin, flikr and facebook
				BatchBookPersonSoicialMediaTagsUpdate($id, $website, $api_key, $url);
			}
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
	
	function BatchBookPersonLocationCreate($id, $email, $website, $api_key, $url)
	{
		$service_url = $url . "/service/people/" . $id . "/locations.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,"location[label]=wordpress&location[email]=" . $email . "&location[website]=" . $website);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);		
	}
	
	function BatchBookPersonSuperTagCreate($id, $comment_date_created, $comment_url, $api_key, $url)
	{
		$service_url = $url . "/service/people/" . $id . "/super_tags/commenter.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS,"super_tag[date]=" . $comment_date_created . "&super_tag[link]=" . urlencode($comment_url));
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);		
	}
	
	function BatchBookPersonTagCreate($id, $tag_name, $api_key, $url)
	{
		$service_url = $url . "/service/people/" . $id . "/add_tag.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS,"tag=" . $tag_name);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);		
	}
	
	function BatchBookPersonSoicialMediaTagsUpdate($id, $website, $api_key, $url)
	{
		$twitter = '';
		$facebook = '';
		$flickr = '';
		$linkedin = '';
		if($website != '')
		{
			if ( function_exists('meetYourCommenters_getRelMes') )
			{
				$dataArray = meetYourCommenters_getRelMes($website);
				if ( count($dataArray) > 0 ) 
				{
					foreach ( $dataArray as $meURL ) 
					{
						//$retstring .= $meURL['url'] . ' | ';
						if($meURL['type'] == 'internal')
						{
							//echo "<li class='visitor-profile-" . $meURL['type'] . "'><img src=\"http://" . $meURL['host'] . "/favicon.ico\" alt=\"\" width=\"16\" height=\"16\" /><a href='" . $meURL['url'] . "'>" . $meURL['url'] . '[' . $meURL['type'] . ']' . "</a></li>";
							//Find any twitter link
							if(strpos($meURL['url'],'twitter') === false){
							}
							else{
								$twitter = $meURL['url'];
							}
							
							//Find any facebook link
							if(strpos($meURL['url'],'facebook') === false){
							}
							else{
								$facebook = $meURL['url'];
							}
							
							//Find any flickr link
							if(strpos($meURL['url'],'flickr') === false){
							}
							else{
								$flickr = $meURL['url'];
							}
								
							//Find any linkedin link
							if(strpos($meURL['url'],'linkedin') === false){
							}
							else{
								$linkedin = $meURL['url'];
							}
						}
					}
				}
			}
		}
		BatchBookCommenterLinksSuperTagCreate($id, $twitter, $facebook, $flickr, $linkedin, $api_key, $url);
	}
	
	function BatchBookCommenterLinksSuperTagCreate($id, $twitter_link, $facebook_link, $flickr_link, $linkedin_link, $api_key, $url)
	{
		$service_url = $url . "/service/people/" . $id . "/super_tags/commenter%20links.xml";
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS,"super_tag[twitter]=" . urlencode($twitter_link) . "&super_tag[facebook]=" . urlencode($facebook_link) . "&super_tag[flickr]=" . urlencode($flickr_link) . "&super_tag[linkedin]=" . urlencode($linkedin_link));
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_VERBOSE, true);		
		$curl_response = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);		
	}
	
	function BatchBookPersonFind($firstname, $email, $api_key, $url)
	{
		$service_url = $url . "/service/people.xml?name=" . $firstname . "&email=" . $email;
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_GET, 1);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":x");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
			
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
	/*------*/
?>
