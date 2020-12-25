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
		$this->page_menu_slug = "all_template_importer";
		$this->page_sub_menu_slug = "all_template_importer_csvs";
		$this->target_dir  	= __DIR__."/uploads/";

		add_action( "admin_menu", array($this, "plugin_menu") );
		add_action( "admin_init", array($this, "handle_form_submissions") );

		/*** 
		* Whenever hook is called then the callback function will run
		***/
		add_action( SCHEDULE_HOOK_NAME, function () {
			if(get_option('import_start_flag') == "0") {
				return;
			}

			/****** DB COUNTER CODE: START ******/
			$counter = get_option('_counter');
			$responseArr = $this->readFile();

			if ( is_array($responseArr) && $responseArr['status'] == true ) {
				// If counter is set in db then update
				if( !empty($counter) ) {
					$counter++;
				} 
				else {
					$counter = 1;
				}

				// Updating the option
				update_option('_counter', $counter);
			}
			else {
				$this->resetAllData();	
			}
			/****** END ******/
		});

		// add_action( "init", function() {
		// 	if(get_option('import_start_flag') == "0") {
		// 		return;
		// 	}

		// 	$counter = get_option('_counter');
		// 	$responseArr = $this->readfile();

		// 	if ( is_array($responseArr) && $responseArr['status'] == true ) {
		// 		// If counter is set in db then update
		// 		if( !empty($counter) ) {
		// 			$counter++;
		// 		} 
		// 		else {
		// 			$counter = 1;
		// 		}

		// 		// Updating the option
		// 		update_option('_counter', $counter);
		// 	}
		// 	else {
		// 		$this->resetAllData();	
		// 	}
		// 	echo '<pre>';
		// 	print_r($responseArr);
		// 	echo '</pre>';
		// });
	}

	public function plugin_menu() {
		// dashicons-database-import Not working :(
		add_menu_page("Upload CSV", "Upload CSV", "manage_options", $this->page_menu_slug, array($this, "displayUpload"), 'dashicons-admin-multisite', 90);

		add_submenu_page( $this->page_menu_slug, "Start/Stop Import", "Start/Stop Import", "manage_options", $this->page_sub_menu_slug, array($this, "displayList") );
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

	public function handle_form_submissions() {
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
    		update_option('_counter', 0);			
			update_option('import_start_flag', $import_start_flag);
			update_option('reference_post_id', $reference_post_id);			
		}

		if( !empty($_GET['deletefile']) ) {
			$fileToDelete = realpath( $this->target_dir.sanitize_file_name($_GET['deletefile']) );
			unlink($fileToDelete);
			wp_redirect( '?page='.$this->page_sub_menu_slug );
			exit;
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

	public function getCsvRowByIndex( $filePath, $csvrowindex=0 ) {			
		
		// #1 Getting the reference post id
		$reference_post_id = get_option('reference_post_id');

		if ( !empty($reference_post_id) ) {

			// #2 Getting the reference post
			$referencePost = $this->getReferencePostContent( $reference_post_id );

			// #3 Open file in read mode
    		$csvFile = fopen($filePath, 'r');

			// #4 Getting the csv header
    		$csvFirstRow = fgetcsv($csvFile);

    		// #5 Generating an array of data
	    	$postarr = array();

    		// #6 Generating the post content
	    	$newPostContent = $referencePost->post_content;

    		// #7 Populating the data in array
	    	$i = 0;
	    	while( ($csvData = fgetcsv($csvFile)) !== FALSE ) {
	      		$csvData = array_map("utf8_encode", $csvData);		      		
	      		$key 	 = $csvFirstRow[$i];
	      		$value 	 = $csvData[$i];

				/** 
				* Replacing the custom fields by their value from string using 3 semicolon instead of 2 because of default behaviour of PHP
				**/
				$newPostContent = str_replace("{{{$key}}}", $value, $newPostContent);
				
				$subArray = array();
				foreach ($csvFirstRow as $j => $key) {
					$subArray[$key] = htmlentities($csvData[$j]);
				}

				$postarr[] = $subArray;
	    		$i++;
	    	}

	    	// #8 Closing the file
	    	fclose($csvFile);

    		// #9 Getting the array by index
    		if ( !empty($postarr[$csvrowindex]) ) {
    			$returnPostData = $postarr[$csvrowindex];
    			$returnPostData['__post_content'] = htmlentities($newPostContent);
	    		return $returnPostData;
    		}
    		else {
    			return null;
    		}
		}  	
    }

    public function readFile() {
		// $files = list_files( $this->target_dir );
		$index = get_option('_counter');

		$files = glob( $this->target_dir.'*' );
		$csvFilePath = null;
		foreach ($files as $i => $filePath) {
			$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
			if ($extension == 'csv') {
				$csvFilePath = $filePath;
				break;
			}
		}
		
		if ($csvFilePath) {
			$csvRowData = $this->getCsvRowByIndex( $csvFilePath, $index );

			if( is_array($csvRowData) && count($csvRowData) > 0 ) {

				// #1 Getting the reference post id
				$reference_post_id = get_option('reference_post_id');

				// #2 Getting the post array
				$postArray = array(
					'post_title' => $csvRowData['_post_state_full_name'].' | '.$csvRowData['_post_state'],
					'post_type' => 'page',
					'post_status' => 'publish',
					'post_content' => html_entity_decode( $csvRowData['__post_content'] )
				);

				// #3 Getting the postArray data		
				$pageExistsData = get_page_by_title( $postArray['post_title'], OBJECT, $postArray['post_type'] );
				
				// #4 If pageExistsData data is not present then inserting new post else getting already existed id
				if ( !$pageExistsData ) {
					// Inserting the new post
					$newPostId = wp_insert_post( $postArray );

					// #5 Getting the reference post template
		    		$referencePostTemplate = get_post_meta( $reference_post_id, '_wp_page_template', true );
					
					// #6 updating the new post template
					if ( $referencePostTemplate && $newPostId ) {
						update_post_meta( $newPostId, '_wp_page_template', $referencePostTemplate);
					}

					/**
					* #7 Getting all the acf fields name from db because in csv some fields are extra and it will help in mapping also
					**/
					$acfFields = $this->acf_field_key( $csvRowData );
					if ( !empty($newPostId) && count($acfFields) > 0 ) {
						// Iterating all fields
			        	foreach ($acfFields as $i => $data) {
			        		$acfKey   	 = $data['post_excerpt'];
			        		$columnValue = $csvRowData[$data['post_excerpt']];
			        		$acfValue = $this->replaceVariablesByTheirValueInCSV( $columnValue, $csvRowData );

							$unserializeData = unserialize( $data['post_content'] );						
							if ( $unserializeData['type'] === "image" ) {
								$attachmentId = $this->saveRemoteUrl( $acfValue );
								if ( $attachmentId ) {
									update_field( $acfKey, $attachmentId, $newPostId );
								}
							}
							else {
								update_field( $acfKey, html_entity_decode($acfValue), $newPostId );
							}
			        	}
			        }			     
				}
				else {
					$newPostId = $pageExistsData->ID;
				}
				return array("status" => true, "message" => "$newPostId inserted");
			}
			else {
				// $this->resetAllData();
				return array("status" => false, "message" => "All data imported");				
			}
		}
		else {
			return array("status" => false, "message" => "$csvFilePath not found");
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

    public function resetAllData() {
    	update_option('import_start_flag', 0);
    	update_option('_counter', 0);
    }

    function acf_field_key( $csvRowData ) {
		global $wpdb;

		/**
		* Getting all the acf fields name from db because in csv some fields are extra and it will help in mapping also
		**/
		$tmpKeysArray = "";
		$i = 0;
		foreach ( array_keys($csvRowData) as $i => $key ) {
			if ($i !== 0) {
				$tmpKeysArray .= ", '{$key}'";
			}
			else {
				$tmpKeysArray .= "'{$key}'";
			}
			$i++;
		}

		$query = "SELECT * FROM $wpdb->posts WHERE post_type='acf-field' AND post_excerpt IN ($tmpKeysArray)";

	    return $wpdb->get_results($query, ARRAY_A);
	}

	function saveRemoteUrl( $remoteUrl ) {
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
		$arrContextOptions = array(
		    "ssl" => array(
		        "verify_peer" => false,
		        "verify_peer_name" => false,
		    ),
		);

		// $remoteUrl = 'https://images.hqseek.com/pictures/Playboy_Corin_Riggs_set1/10429JR-0160.jpg';

		if(!empty($remoteUrl)) {
			$filename 	= basename($remoteUrl);
			$ifeimg = $this->isFileExistsInMediaGallery($filename);

			if ( !$ifeimg ) {
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
				
				$attach_id = wp_insert_attachment( $attachment, $uploadfile );
				$imagenew = get_post( $attach_id );
				$fullsizepath = get_attached_file( $imagenew->ID );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
				wp_update_attachment_metadata( $attach_id, $attach_data );
					
				return $attach_id;
			}
			else {
				return $ifeimg->ID;
			}
		}
		else {
			return null;
		}	
	}

	function isFileExistsInMediaGallery( $filename ) {
		global $wpdb;
		$query = "SELECT * FROM {$wpdb->posts} WHERE post_title='$filename' AND post_type='attachment' ";
		return $wpdb->get_row($query);
	}
}

$all_template_importer = new All_template_importer();

// add_action("init", function() {
// 	$all_template_importer->readFile();
// });