<div class="wrap">
	<h2>All CSV</h2>
	<form method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>		
	<?php

		if ( is_array($referencePosts) && count($referencePosts) > 0 ) {
			foreach ($referencePosts as $id => $name) {
				$selectedClass = "";

				if( $reference_post_id == $id ) 
					$selectedClass = "checked";

				echo '<p>
					<input required '.$selectedClass.' type="radio" id="rf'.$id.'" name="reference_post_id" value="'.$id.'" />
					<label for="rf'.$id.'">'.$name.'</label>
				</p>';
			}
		}
		?>
		<p>
			<label for="import_start_flag_id"><b>Import Status</b></label>
			<select id="import_start_flag_id" name="import_start_flag">
				<option <?php echo $import_start_flag == "1" ? "selected" : "" ?> value="1">Start</option>
				<option <?php echo $import_start_flag == "0" ? "selected" : "" ?> value="0">Stop</option>
			</select>
		</p>
		<p><label>CURRENT CSV ROW: <?php echo get_option('_counter') ?></label></p>    	
		<p><input type="submit" name="import_start_form" value="submit"></p>		
	</form>
	<table class='wp-list-table widefat fixed striped media'>
		<thead>
			<tr>
				<td width="100">Sno</td>
				<td>File Name</td>
				<td>size</td>
				<td>Action</td>
			</tr>
		</thead>
		<tbody>
			<?php
			if (is_dir($directoryPath)) {
			    if ($dh = opendir($directoryPath)) {
			    	$i = 0;
			        while (($file = readdir($dh)) !== false) {

			        	if ( in_array($file, array(".", "..") )) {
			        		continue;
			        	}

			        	$filePath = $directoryPath.$file;
			        	?>
			            <tr>
							<td><?php echo $i+1 ?></td>
							<td><?php echo $file ?></td>
							<td><?php echo size_format(filesize($filePath), 2) ?></td>
							<td>
								<a class="button button-primary" target="_blank" href="<?php echo ATI_UPLOAD_PUBLIC_PATH.$file ?>">Download</a>
								<a class="button button-warning" href="<?php echo "?page={$this->page_sub_menu_slug}&deletefile=".$file ?>">Delete</a>
							</td>
						</tr>
			        <?php
			        $i++; 
			    	}
			        closedir($dh);
			    }
			}
			?>
		</tbody>
	</table>
</div>