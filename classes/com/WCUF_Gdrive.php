<?php 
require WCUF_PLUGIN_ABS_PATH.'/classes/vendor/google/vendor/autoload.php';

class Gdrive
{
	var $gdrive_client;
	var $gdrive_service;
	static $gdrive_filepath_prefix = 'gdrive:'; 
	public function __construct()
	{
		$this->gdrive_client = new Google_Client();
		
		$this->gdrive_client->setDeveloperKey('');
		$this->gdrive_client->setApplicationName("WooCommerce Upload Files");
		$this->gdrive_client->setScopes(Google_Service_Drive::DRIVE);
		$this->gdrive_client->setAccessType('offline');
		
		
		$this->gdrive_service = new Google_Service_Drive($this->gdrive_client );
	}
	public static function is_gdrive_file_path($file_path)
	{
		if(!is_string($file_path))
			return false;
		return strpos($file_path, WCUF_Gdrive::$gdrive_filepath_prefix) !== false ? true : false;
	}
	public function upload_file($filename, $params = array())
	{
		$fileMetadata = new Google_Service_Drive_DriveFile(array('name' => basename($filename)));
		$content = file_get_contents($filename);
		$file = $this->gdrive_service->files->create($fileMetadata, array(
			'data' => $content,
			'mimeType' => mime_content_type($filename),
			'uploadType' => 'multipart',
			'fields' => 'id'
			)
		);
		return $file; 
	}
	
}
?>