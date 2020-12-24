<h2>Upload CSV To Import</h2>

<!-- Form -->
<form method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>	
	<p><input type="file" name="import_file" ></p>
	<p><input type="submit" name="butimport" value="Import"></p>
</form>