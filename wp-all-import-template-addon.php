<?php
/**
Plugin Name: WP All Import Using Template
Description: A super awesome add-on for WP All Import which facilites the import using the choosen template!
Version: 1.0
Author: Kunal Malviya
**/

include "assets/admin/rapid-addon.php";

final class wp_all_import_using_templaete_add_on {

	protected static $instance;

	protected $add_on;

	static public function get_instance() {
	    if ( self::$instance == NULL ) {
	        self::$instance = new self();
	    }
    	return self::$instance;
	}
	
	protected function __construct() {
        
        // Define the add-on
        $this->add_on = new RapidAddon( 'Import Using Template Add-On', 'template_replication_add_on' );
		
		// Getting all draft pages
		$draftPosts = get_posts(
			array(
				'numberposts' => -1,
				'post_status' => 'draft',
				'post_type' => array('page')
			)
		);

		if ( is_array($draftPosts) && count($draftPosts) > 0 ) {
			$radioFields = array();
			foreach ($draftPosts as $post) {
				$radioFields[$post->ID] = $post->post_title;
			}
			// Rendering the radio fields
			$this->add_on->add_field(
			    'reference_template_id',
			    'Select Reference Template',
			    'radio',
			    $radioFields
			);
		}		

        // Add UI elements to the import template
        // $this->add_on->add_field( 'reference_template_id', 'ID of template', 'text', null, '#ID of reference post/page', false, '' );

        $acfKeys = $this->acf_field_key();
        if (count($acfKeys) > 0) {
        	foreach ($acfKeys as $i => $data) {
				$unserializeData = unserialize( $data['post_content'] );
				if ( $unserializeData['type'] === "image" ) {
		        	$this->add_on->add_field( $data['post_excerpt'], $data['post_title'], 'image' );
				}
				else {
		        	$this->add_on->add_field( $data['post_excerpt'], $data['post_title'], 'text', null, '#Keys used in template', false, '' );
				}
        	}
        }

        // This tells the add-on API which method to call
        // for processing imported data. 
        $this->add_on->set_import_function( [ $this, 'import' ] );

        // This registers the method that will be called
        // to run the add-on.
        add_action( 'init', [ $this, 'init' ] );

        $this->reference_template = null;
    }

    // Tell the add-on to run, add conditional statements as needed.
    public function init() {
    	/*// This approach is needed when you need one OR another plugin active. 
		if ( function_exists('is_plugin_active') ) {
		    // Only run this add-on if the free or pro version of the Yoast plugin is active.
		    if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
		        $this->add_on->run();
		    }
		}*/
		// https://www.wpallimport.com/documentation/addon-dev/best-practices/
        $this->add_on->run(array(
        	"post_types" => array( "post", "page" )
    	));
    }

    // Add the code that will actually save the imported data here.
    public function import( $post_id, $data, $import_options ) {    	
    	if ( !empty($data['reference_template_id']) ) {
    		/**
    		* Getting the reference post data
    		**/
    		$referencePost 	 = get_post( $data['reference_template_id'] );
		    $referencePostTemplate = get_post_meta( $referencePost->ID, '_wp_page_template', true );

    		if ( $referencePost ) {
    			/**
	    		* Generating data to update post
	    		**/
	    		$updatePostData = array( 'ID' => $post_id );
			    $newPostContent = $referencePost->post_content;

				foreach ($data as $key => $value) {
	    			if ($key !== "reference_template_id") {
	    				if ( is_array($value) && !empty($value['attachment_id']) ) {
	    					// Updating the acf attachment fields
							update_field( $key, $value['attachment_id'], $post_id );
	    				}
	    				else {
	    					/** 
	    					* Replacing the custom fields by their value from string using 3 semicolon instead of 2 because of default behaviour of PHP
	    					**/
							$newPostContent = str_replace("{{{$key}}}", $value, $newPostContent);

	    					// Updating the acf other fields
							update_field( $key, $value, $post_id );
	    				}
	    			}
				}

				$updatePostData['post_content'] = $newPostContent;
				if ( $referencePostTemplate ) {
					$updatePostData['page_template'] = $referencePostTemplate;
				}
				wp_update_post( $updatePostData );
    		}
    			    
			/*if ( $template ) {
				$this->update_page_template( $post_id, $template);
			}*/
    	}    	
    }

    function update_page_template($postId, $template='default') {
    	global $wpdb;
    	$sql = "UPDATE {$wpdb->prefix}postmeta SET meta_value='".$template."' WHERE meta_key='_wp_page_template' AND post_id=".$postId;
    	$wpdb->query($sql);
    }

    function acf_field_key() {	
		global $wpdb;
	    return $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type='acf-field';", ARRAY_A);
	}

	function replaceString() {

	}

	/*function saveRemoteUrl( $remoteUrl, $slug='' ) {	
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
		$arrContextOptions = array(
		    "ssl" => array(
		        "verify_peer" => false,
		        "verify_peer_name" => false,
		    ),
		); 

		if( !empty($remoteUrl) ) {
			$filename 	= basename($remoteUrl);
			$uploaddir 	= wp_upload_dir();
			$uploadfile = $uploaddir['path'] . '/' . $filename;
			$contents 	= file_get_contents($remoteUrl, false, stream_context_create($arrContextOptions));
			$savefile 	= fopen($uploadfile, 'w');
			fwrite($savefile, $contents);
			fclose($savefile);

			$wp_filetype = wp_check_filetype($filename, null );

			$attachment = array(
			    'post_mime_type' => $wp_filetype['type'],
			    'post_title' => $filename,
			    'post_content' => '',
			    'post_status' => 'inherit'
			);

			$attach_id 	  = wp_insert_attachment( $attachment, $uploadfile );
			$imagenew 	  = get_post( $attach_id );
			$fullsizepath = get_attached_file( $imagenew->ID );
			$attach_data  = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			return $attach_id;
		}
		else {
			return null;
		}	
	}*/
}

wp_all_import_using_templaete_add_on::get_instance();

// function acf_field_key(){
// 	global $wpdb;
//     return $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type='acf-field';", ARRAY_A);
// }
// $a = acf_field_key();
// if (count($a) > 0) {
// 	foreach ($a as $i => $data) {
// 		if ( !empty($data['post_content']) ) {
// 			$unserializeData = unserialize($data['post_content']);
// 			echo "<pre>";
// 	    	print_r($unserializeData);
// 	    	echo "</pre>";
// 		}    
// 	}
// }
