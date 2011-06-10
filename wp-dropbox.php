<?php /*

**************************************************************************

Plugin Name:  Dropbox Upload Form
Plugin URI:   
Description:  Use the shortcode [wp-dropbox] in any page to insert a Dropbox file upload form. Kudos to <a href="http://inuse.se">inUse</a> for letting me release this in-house developed plugin under GPL. Use with caution and DON'T blame me if something breaks.
Version:      0.1.5
Author:       Henrik Östlund
Author URI:   http://östlund.info/

**************************************************************************

Copyright (C) 2010 Henrik Östlund henrik(at)myworld.se

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

**************************************************************************/

function show_dropbox()
{	
	$db_user = get_option( 'db_username' );
	$db_pass = get_option( 'db_password' );
	$dp_path = get_option( 'db_path' );
	$db_tmp_path = get_option( 'db_temp_path' );
	$db_allow_ext = trim( get_option( 'db_allow_ext' ) );
	$db_key = get_option( 'db_key' );
	$db_secret = get_option( 'db_secret' );

	$db_token = get_option( 'db_auth_token' );
	$db_token_secret = get_option( 'db_auth_token_secret' );

	echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/wp-dropbox/css/wp-db-style.css" />' . "\n";

	echo '<div class="wp-dropbox">';

	$showform = True;
	try {
		if (($db_token == '') or ($db_secret == '') or ($db_token == '') or ($db_token_secret == '') )
			throw new Exception(__('Need to configure plugin!'));

		if ($db_allow_ext == '')
			throw new Exception(__('Need to configure allowed file extensions!'));

	} catch(Exception $e) {
    	echo '<span id="syntax_error">'.__('Error:'). ' ' . htmlspecialchars($e->getMessage()) . '</span>';
		$showform = False;
	}
	
	if ($_POST['email']) {

	    try {
			include 'inc/dropbox.php';
   			$dbupf = new Dropbox($db_key,$db_secret);
			$dbupf->setOAuthToken($db_token);
			$dbupf->setOAuthTokenSecret($db_token_secret);

		} catch(Exception $e) {
			echo '<span id="syntax_error">'.__('Error:'). ' ' . htmlspecialchars($e->getMessage()) . '</span>';
			$showform = False;
		} 

	try {
	$allowedExtensions = split("[ ]+", $db_allow_ext);

	  foreach ($_FILES as $file) { 
	    if ($file['tmp_name'] > '') { 
	      if (!in_array(end(explode(".", 
	            strtolower($file['name']))), 
	            $allowedExtensions)) { 
			$ext = implode(", ", $allowedExtensions);
	       die('<p>'.__('Allowed file extensions: ',wpdbUploadForm).''.$ext.'<br/>'.
	        '<a href="javascript:history.go(-1);">'. 
	        __('&lt;= Go back',wpdbUploadForm).'</a></p>'); 
	      } 
	    } 
	  } 

	        // Rename uploaded file to reflect original name
	        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
	            throw new Exception(__('File was not uploaded from your computer.',wpdbUploadForm));

			if (!file_exists($db_tmp_path))
			{
	        if (!mkdir($db_tmp_path))
	            throw new Exception(__('Cannot create temporary directory!',wpdbUploadForm));
			}
	        if ($_FILES['file']['name'] === "")
	            throw new Exception(__('File name not supplied by the browser.',wpdbUploadForm));

			$new_file_name = explode(".",$file['name']);

		    $tmpFile = $db_tmp_path.'/'.str_replace("/\0", '_', $new_file_name[0]) . "_" . date("Y-m-d").".".str_replace("/\0", '_', $new_file_name[1]);
	    	if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpFile))
	        	throw new Exception(__('Cannot rename uploaded file!',wpdbUploadForm));

	       	// Upload
			$return = $dbupf->filesPost($dp_path, $tmpFile);
				if ( !$return ) {
		        	throw new Exception(__('ERROR!',wpdbUploadForm));
				}

	        echo '<span id="sucess">'.__('Your file is uploaded',wpdbUploadForm).'</span>';
	    	$showform = False;
			$delete_file = True;

	    } catch(Exception $e) {
	        echo '<span id="syntax_error">'.__('Error:',wpdbUploadForm) . ' ' . htmlspecialchars($e->getMessage()) . '</span>';
			$delete_file = False;
	    }		
/*
	   $attachments = array($tmpFile);
	   $headers = 'From: My Name <myname@mydomain.com>' . "\r\n\\";
	   wp_mail('henrik@myworld.se', 'subject', 'message', $headers, $attachments);
*/
	    // Clean up
	if($delete_file == True) {
	    	if (isset($tmpFile) && file_exists($tmpFile))
	        	unlink($tmpFile);
		}
	}

	if($showform == True) {
		?>
	        <form method="POST" enctype="multipart/form-data">
	            <input type="hidden" name="email" value="1"/>
				
				<input class="input_form" size="34" type="file" name="file" />
	        	<input type="submit" value="<?php _e('Submit',wpdbUploadForm); ?>" />
			</form>
		<?php }
	echo "</div>";
}

	function wp_dropbox_settings_page() {
	?>	
	<div class="wrap">
		<h2>Wordpress Drobox Upload Form</h2>
		<p>Make and use a special folder on your Dropbox if you allow public uploads. The date when the file got uploded is appended at the end of the filename, example foo_2010-01-01.pdf just so we don't overwrite files.</p>
<?php
		if( $_POST[ "wp_db_submit_hidden" ] == 'Y' ) {
				// Check if we should ask Dropbox api for a token for the given user
				if ( trim($_POST[ 'wp_db_password' ]) != '') {
					$updateAuth = True;
				}
				$db_error = False;
				
				if ( $updateAuth ) {
					include 'inc/dropbox.php';
					$dbupf = new Dropbox($_POST[ 'dbapikey' ],$_POST[ 'dbapisecret' ]);
					$dbapistuff = $dbupf->token($_POST[ 'wp_db_username' ], $_POST[ 'wp_db_password' ]);

					if ( empty( $dbapistuff["error"] ) ) {
						update_option( 'db_auth_token', $dbapistuff["token"] );
	        			update_option( 'db_auth_token_secret', $dbapistuff["secret"] );
					}
					else {
						?>
							<div class="updated"><p><strong><?php echo $dbapistuff["error"]; ?></strong></p></div>							
						<?php
						$db_error = True;
					}
				}
				
		        // Save the posted value in the database
		        update_option( 'db_username', $_POST[ 'wp_db_username' ] );
		        update_option( 'db_path', $_POST[ 'db_path' ] );
		        update_option( 'db_temp_path', $_POST[ 'db_temp_path' ] );
		        update_option( 'db_allow_ext', $_POST[ 'db_allow_ext' ] );
		        update_option( 'db_key', $_POST[ 'dbapikey' ] );
        		update_option( 'db_secret', $_POST[ 'dbapisecre' ] );
		        // Put an options updated message on the screen
		if (!$db_error) {
		?>
			<div class="updated"><p><strong><?php _e('Options saved. Dropbox connection is okay, no need to update your password again.', 'mt_trans_domain' ); ?></strong></p></div>
		<?php
		}
		    }
?>
	        <form name="wp_db_form" method="POST" action="">
				<input type="hidden" name="wp_db_submit_hidden" value="Y">	
<table class="form-table">
				<tr>
					<th scope="row"><p>Dropbox username/login.</p></td>
	            	<td><input id="inputid" type="text" size="30" name="wp_db_username" value="<?php echo get_option( 'db_username' ); ?>" />
					<label for="inputid">username@foo.bar</label>		
					</td>
				</tr>
				<tr>
					<th scope="row"><p>Dropbox password.</p></th>
		            <td><input id="inputid" type="password" size="30" name="wp_db_password" value="" />
					<label for="inputid">supersecretpassword</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><p>Path in dropbox folder (please, don't save to root folder).</p></th>
		            <td><input type="text" size="60" name="db_path" value="<?php echo get_option( 'db_path' ); ?>" />
					<label for="inputid">public_upload</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><p>Temporary path on server. Files get saved here if Dropbox server is down.</p></th>
		            <td><input type="text" size="60" name="db_temp_path" value="<?php echo get_option( 'db_temp_path' ); ?>" />
					<label for="inputid"><?php echo ABSPATH; ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><p>Allowed file extensions, separated by space.</p></th>
    				<td><input type="text" size="60" name="db_allow_ext" value="<?php echo get_option( 'db_allow_ext' ); ?>" />
						<label for="inputid">doc docx pdf</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><p>Dropbox API Key.</p></th>
    				<td><input type="text" size="60" name="dbapikey" value="<?php echo get_option( 'db_key' ); ?>" />
						<label for="inputid">https://www.dropbox.com/developers/apps</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><p>Dropbox API Secret.</p></th>
    				<td><input type="text" size="60" name="dbapisecre" value="<?php echo get_option( 'db_secret' ); ?>" />
						<label for="inputid">https://www.dropbox.com/developers/apps</label>
					</td>
				</tr>				
				<tr>
					<th scope="row"><input type="submit" value="<?php _e('Save options',wpdbUploadForm); ?>" /></th>
					<td></td>
				</tr>					
</table>
		</form>

		<br />		
		<a href="http://www.dropbox.com/referrals/NTI1MDUxNDc5" target="_blank">Need a Dropbox account, pls use this link so I get some extra space.</a>

		</div>
	<?php }

	// shortcode
	function shortcode_wp_dropbox( $atts, $content = NULL ) {
		// Hackis way to show my shortcode at the right place
		ob_start();
		show_dropbox();
		$output_string=ob_get_contents();
		ob_end_clean();
		return $output_string;
	}

	function wp_db_create_menu() {
		//create new top-level menu
		add_options_page('WP-Dropbox', 'WP-Dropbox', 'administrator', __FILE__, 'wp_dropbox_settings_page');

		//call register settings function
		add_action( 'admin_init', 'register_wp_dropbox_settings' );
	}

	function wp_dropbox_deactivate()
		{
			remove_shortcode( 'wp-dropbox' );
	        delete_option( 'db_username' );
	        delete_option( 'db_path' );
	        delete_option( 'db_temp_path' );
			delete_option( 'db_allow_ext' );	
			delete_option( 'db_key' );
			delete_option( 'db_secret' );			
			delete_option( 'db_auth_token' );
			delete_option( 'db_auth_token_secret');
		}

	function register_wp_dropbox_settings() {
		//register our settings
		register_setting( 'wp_db-settings-group', 'db_username' );
		register_setting( 'wp_db-settings-group', 'db_path' );
		register_setting( 'wp_db-settings-group', 'db_temp_path' );
		register_setting( 'wp_db-settings-group', 'db_allow_ext' );
		register_setting( 'wp_db-settings-group', 'db_key' );
		register_setting( 'wp_db-settings-group', 'db_secret' );
		register_setting( 'wp_db-settings-group',  'db_auth_token' );
		register_setting( 'wp_db-settings-group',  'db_auth_token_secret');
	}

	function WP_DB_PluginInit()
	  {
	  	load_plugin_textdomain( 'wpdbUploadForm', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)),
		       dirname(plugin_basename(__FILE__)));
	  }


	// Start this plugin once all other plugins are fully loaded
//	add_action( 'plugins_loaded', 'WPDropbox');
	add_shortcode( 'wp-dropbox', 'shortcode_wp_dropbox' );
	add_action('admin_menu', 'wp_db_create_menu');
	add_action( 'init', 'WP_DB_PluginInit' );
	
	register_deactivation_hook( __FILE__, 'wp_dropbox_deactivate' );
?>