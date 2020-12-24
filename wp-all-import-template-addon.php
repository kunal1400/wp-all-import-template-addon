<?php
/**
Plugin Name: WP All Import Using Template
Description: A super awesome add-on for WP All Import which facilites the import using the choosen template!
Version: 1.0
Author: Kunal Malviya
**/

define("ATI_UPLOAD_PUBLIC_PATH", plugins_url('uploads/', __FILE__));

/**
* If the name of schedule event is same as previous one then it was not working so 
* made it dynamic. Now if any issue occur in future then just update the name of event.
**/
define('SCHEDULE_HOOK_NAME', 'games_importer_cron_hook_c');

/*** 
* Plugin activation callback function.
* This function set a schedule by providing Hook to us.
***/
register_activation_hook(__FILE__, function () {
    // Schedule an action if it's not already scheduled
	if ( ! wp_next_scheduled( SCHEDULE_HOOK_NAME ) ) {
	    wp_schedule_event( time(), 'every_one_minute', SCHEDULE_HOOK_NAME );
	}
});

/*** 
* By this function we are setting the time interval
***/
add_filter( 'cron_schedules', function ( $schedules ) {
    if( !isset($schedules["every_one_minute"]) ){
    	$schedules['every_one_minute'] = array(
	        'interval' => 60, // Every 1 minute
	        'display'  => __( 'Every 1 minutes' ),
	    );
    }
    return $schedules;
});


/*** 
* When plugin deactivated then callback function will be called 
***/
register_deactivation_hook(__FILE__, function () {
	update_option('_counter', 0);
    wp_clear_scheduled_hook( SCHEDULE_HOOK_NAME );
});


/**
 * 
 */
class All_template_importer {
	
	function __construct() {
		$this->page_sub_menu_slug = "all_template_importer_csvs";
		$this->page_menu_slug = "all_template_importer";
		$this->target_dir  	= __DIR__."/uploads/";

		add_action( "admin_menu", array($this, "plugin_menu") );
		add_action( "admin_init", array($this, "handle_upload") );
	}

	public function plugin_menu() {
		// dashicons-database-import Not working :(
		add_menu_page("Template CSV Importer", "Template CSV Importer", "manage_options", $this->page_menu_slug, array($this, "displayUpload"), 'dashicons-admin-multisite', 90);

		add_submenu_page( $this->page_menu_slug, "Uploaded CSV", "Uploaded CSV", "manage_options", $this->page_sub_menu_slug, array($this, "displayList") );
	}

	public function displayUpload() {
		include "partials/uploadfile.php";
	}

	public function displayList() {
		$referencePosts = $this->getReferencePosts();
		$directoryPath = $this->target_dir;

		$import_start_flag = get_option('import_start_flag');
		$reference_post_id = get_option('reference_post_id');
		include "partials/displaylist.php";
	}

	public function getReferencePosts() {
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
			return $radioFields;
		}
		else {
			return null;
		}
	}

	public function handle_upload() {
		if( isset($_POST['butimport']) ) {
			$target_file = $this->target_dir . basename($_FILES["import_file"]["name"]);

			$extension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

			if( !empty($_FILES['import_file']['name']) && $extension == 'csv' ) {
				
				// Check if file already exists
				if ( file_exists($target_file) ) {
					add_action( 'admin_notices', array($this, 'file_already_exists_error') );
					return;
				}

				// Check file size
				if ($_FILES["import_file"]["size"] > 500000) {					
					add_action( 'admin_notices', array($this, 'file_too_large') );
					return;
				}

				if (move_uploaded_file($_FILES["import_file"]["tmp_name"], $target_file)) {
					wp_redirect( '?page='.$this->page_sub_menu_slug );
					exit;
				}
				else {
					add_action( 'admin_notices', array($this, 'unknown_error') );
					return;
				}
			}
			else{
				add_action( 'admin_notices', array($this, 'invalid_extension_error') );
				return;
		  	}
		}
	
		if( isset($_POST['import_start_form']) ) {

			// If reference_post_id is set or not 
			if ( !isset($_POST['reference_post_id']) ) {
				add_action( 'admin_notices', array($this, 'reference_post_id_required_field_error') );
				return;
			}

			// If reference_post_id is set or not 
			if ( !isset($_POST['import_start_flag']) ) {
				add_action( 'admin_notices', array($this, 'reference_post_id_required_field_error') );
				return;
			}

			$import_start_flag = sanitize_text_field( $_POST['import_start_flag'] );
			$reference_post_id = sanitize_text_field( $_POST['reference_post_id'] );

			// Updating options in wp
			update_option('import_start_flag', $import_start_flag);
			update_option('reference_post_id', $reference_post_id);

			// // File extension
			// $extension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

			// // If file extension is 'csv'
			// if( !empty($_FILES['import_file']['name']) && $extension == 'csv' ){				

		 //    	$totalInserted = 0;

		 //    	// Open file in read mode
		 //    	$csvFile = fopen($_FILES['import_file']['tmp_name'], 'r');

		 //    	// Getting the reference post
   //  			$referencePost = $this->getReferencePostContent( $_POST['reference_post_id'] );

		 //    	$csvFirstRow = fgetcsv($csvFile); // Skipping header row

		 //    	/**
		 //    	* Replacing all meta key name by their values for post content
		 //    	**/
		 //    	$i = 0;
		 //    	$newPostContent = $referencePost->post_content;
		 //    	$postarr = array();
		 //    	while( ($csvData = fgetcsv($csvFile)) !== FALSE ) {
		 //      		$csvData = array_map("utf8_encode", $csvData);		      		
		 //      		$key 	 = $csvFirstRow[$i];
		 //      		$value 	 = $csvData[$i];
			// 		/** 
			// 		* Replacing the custom fields by their value from string using 3 semicolon instead of 2 because of default behaviour of PHP
			// 		**/
			// 		$newPostContent  = str_replace("{{{$key}}}", $value, $newPostContent);
					
			// 		$postarr[] = array(
			// 			'post_title' => $csvData[0].' | '.$csvData[1],
			// 			'post_type' => 'page',
			// 			'post_status' => 'publish'
			// 		);

		 //    		$i++;
		 //    	}

		 //    	$referencePostTemplate = get_post_meta( $referencePost->ID, '_wp_page_template', true );
		 //    	foreach ($postarr as $i => $post) {
			// 		$newPostId = wp_insert_post( $post );
			// 		if ( !empty($newPostId) ) {
			// 			wp_update_post(
			// 				array(
			// 					'ID' => $newPostId,
			// 					'post_content' => $newPostContent
			// 				)							
			// 			);

			// 			// /** 
   //  		// 			* Replacing the custom fields by their value from string using 3 semicolon instead of 2 because of default behaviour of PHP
   //  		// 			**/
			// 			// $valueWithAcf = $this->replaceVariablesByTheirValueInCSV( $value, $csvData );

   //  		// 			// Updating the acf other fields
			// 			// update_field( $key, $valueWithAcf, $newPostId );
			// 		}

			// 		/**
		 //    		* UPDATING THE PAGE TEMPLATE
		 //    		**/	    
			// 		if ( $referencePostTemplate && $newPostId ) {
			// 			$this->update_page_template( $newPostId, $referencePostTemplate);
			// 		}
		 //    	}
		 //    	// echo "<h3 style='color: green;'>Total record Inserted : ".$totalInserted."</h3>";
		 //  	} 
		 //  	else{
			// 	add_action( 'admin_notices', array($this, 'invalid_extension_error') );
			// 	return;
		 //  	}
		}
	}

	public function replaceVariablesByTheirValueInCSV( $str="", $values ) {
    	if ( is_array($values) && count($values) > 0 ) {
    		foreach ($values as $key => $value) {
				$str = str_replace("{{{$key}}}", $value, $str);    			
    		}
    		return $str;
    	} else {
    		return $values;
    	}
    }

	public function reference_post_id_required_field_error() {		
		$this->showMessage('notice notice-error', 'reference_post_id is required');
	}

	public function invalid_extension_error() {
		$this->showMessage('notice notice-error', 'Invalid Extension');
	}

	public function file_already_exists_error() {
		$this->showMessage('notice notice-error', 'File already exists');
	}

	public function file_too_large() {
		$this->showMessage('notice notice-error', 'Sorry, your file is too large.');
	}

	public function file_uploaded_successfully() {
		$this->showMessage('notice notice-error', 'File uploaded successfully.');
	}

	public function unknown_error() {
		$this->showMessage('notice notice-error', 'Some unexpected error occured');
	}

	public function showMessage( $class, $message ) {
		$class = 'notice notice-error';
    	$message = 'Invalid Extension';
    	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	public function getReferencePostContent( $reference_template_id ) {    	
    	if ( !empty($reference_template_id) ) {    		
    		$referencePost = get_post( $reference_template_id );
    		$content = $referencePost->post_content;		    
    		return $referencePost;	
    	}
    	else {
    		return null;
    	}
    }

	public function import( $reference_template_id ) {
    	if ( !empty($reference_template_id) ) {
    		/**
    		* Getting the reference post data
    		**/
    		$referencePost 	 = get_post( $reference_template_id );
		    $referencePostTemplate = get_post_meta( $referencePost->ID, '_wp_page_template', true );

    		if ( $referencePost ) {
    			/**
	    		* Generating data to update post
	    		**/
	    		$updatePostData = array( 'ID' => $post_id );
			    $newPostContent = $referencePost->post_content;

				foreach ($data as $key => $value) {
	    			if ($key !== "reference_template_id") {

	    				// $cf_value = apply_filters('pmxi_acf_custom_field', $value, $post_id, $key);

	    				if ( is_array($value) && !empty($value['attachment_id']) ) {
	    					// Updating the acf attachment fields
							update_field( $key, $value['attachment_id'], $post_id );
	    				}
	    				else {
	    					/** 
	    					* Replacing the custom fields by their value from string using 3 semicolon instead of 2 because of default behaviour of PHP
	    					**/
							$newPostContent  = str_replace("{{{$key}}}", $value, $newPostContent);

							/** 
	    					* Replacing the custom fields by their value from string using 3 semicolon instead of 2 because of default behaviour of PHP
	    					**/
							$valueWithAcf = $this->replaceVariablesByTheirValueInCSV( $value, $data );

	    					// Updating the acf other fields
							update_field( $key, $valueWithAcf, $post_id );
	    				}
	    			}
				}

				$updatePostData['post_content'] = $newPostContent;				
				wp_update_post( $updatePostData );
    		}
    		
    		/**
    		* UPDATING THE PAGE TEMPLATE
    		**/	    
			if ( $referencePostTemplate ) {
				$this->update_page_template( $post_id, $referencePostTemplate);
			}
    	}    	
    }

}

$all_template_importer = new All_template_importer();

/*** 
* Whenever hook is called then the callback function will run
***/
add_action( SCHEDULE_HOOK_NAME, function () {
	// if(get_option('rawg_games_import_started') == "no") {
	// 	return;
	// }

	/****** DB COUNTER CODE: START ******/
	$counter = get_option('_counter');

	// If counter is set in db then update
	if( !empty($counter) ) {
		$counter++;
	} 
	else {
		$counter = 1;
	}

	// Updating the option
	update_option('_counter', $counter);
	/****** END ******/
});