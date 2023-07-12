<?php 
class WCUF_UploadField
{
	function __construct()
	{
	}
	public function get_individual_id_from_string($string)
	{
		return str_replace("idsai", "" ,$string); 
	}
	public function is_individual_id_string($string)
	{
		return strpos($string,"idsai") !== false;
	}
	private function get_post_meta($order_id, $key, $single = true)
	{
		if(version_compare( WC_VERSION, '2.7', '<' ))
		{
			$data = get_post_meta($order_id, $key, $single);
			return $data;
		}
		$order = wc_get_order($order_id);
		
		if(!isset($order) || $order == false)
			return array();
		
		$data = $order->get_meta( $key, $single);
		
		return $data;
	}
	private function delete_post_meta($order_id, $key)
	{
		if(version_compare( WC_VERSION, '2.7', '<' ))
		{
			delete_post_meta( $order_id, $key);
			return;
		}
		$order = wc_get_order($order_id);
		
		if(!isset($order) || $order == false)
			return;
		
		$order->delete_meta_data($key);
		$order->save();
		return;
	}
	private function update_post_meta($order_id, $key, $value)
	{
		if(version_compare( WC_VERSION, '2.7', '<' ))
		{
			update_post_meta( $order_id, $key, $value);
			return;
		}
		$order = wc_get_order($order_id);
		
		if(!isset($order) || $order == false)
			return;
		
		$order->update_meta_data( $key, $value);
		$order->save();
		return;
	}
	public function get_meta_names()
	{
		return array('_wcst_uploaded_files_meta', '_wcuf_uploaded_files');
	}
	public function get_uploaded_files_meta_data_by_order_id($order_id)
	{
		$result = $this->get_post_meta($order_id, '_wcst_uploaded_files_meta', true); //old error
		$result2 = $this->get_post_meta($order_id, '_wcuf_uploaded_files', true);
		
		//in case of incomplete upload, they are removed
		if(isset($result2) && is_array($result2))
			foreach($result2 as $key => $data)
				if(!isset($data['url']))
					unset($result2[$key]);
		
		if( (!$result || empty($result)) && (!$result2 || empty($result2)))
			return array();
		
		if(!$result || empty($result))		
			return !$result2 ? array() : $result2;
		
		if(!$result2 || empty($result2))		
			return !$result ? array() : $result;
		
		$final_result = array_merge($result, $result2); //impossible, on save the old _wcst_uploaded_files_meta is deleted;
		
		
		return $final_result;
	}
	public function save_uploaded_files_meta_data_to_order($order_id, $file_order_metadata)
	{
		$this->delete_post_meta( $order_id, '_wcst_uploaded_files_meta'); //old and wrong meta is deleted
		$this->update_post_meta( $order_id, '_wcuf_uploaded_files', $file_order_metadata);
	}
	public function delete_uploaded_files_meta_data_by_order_id($order_id)
	{
		$this->delete_post_meta( $order_id, '_wcst_uploaded_files_meta');
		$this->delete_post_meta( $order_id, '_wcuf_uploaded_files');
	}
	public function get_num_uploaded_files($order_id, $upload_field_id = 'none', $max_uploaded_files_number_considered_as_sum_of_quantities = false)
	{
		$result = $this->get_uploaded_files_meta_data_by_order_id($order_id);
		$total = 0;
		//wcuf_var_dump($result);
		foreach((array)$result as $upload_field_id_key => $meta)
				if($upload_field_id == 'none' || $upload_field_id == $upload_field_id_key)
				{
					if($max_uploaded_files_number_considered_as_sum_of_quantities)
						foreach((array)$meta["quantity"] as $quantity)
							$total +=  intval($quantity);
					else
						$total += isset($meta['original_filename']) && is_array($meta['original_filename']) ? count($meta['original_filename']) : 0;
				}
		return $total;
	}
	public function get_num_uploaded_files_in_session($upload_field_id, $max_uploaded_files_number_considered_as_sum_of_quantities)
	{
		global $wcuf_session_model;
		$number = 0;
		$data = $wcuf_session_model->get_item_data($upload_field_id);
		if(!isset($data) || !isset($data["tmp_name"]))
			return $number;
		
		foreach((array)$data["quantity"] as $uploaded_files)
		{
			$number += $max_uploaded_files_number_considered_as_sum_of_quantities ? intval($uploaded_files) : 1;
		}
			
		return $number;
	}
	public function is_upload_field_content_managed_as_zip($file_meta) //old multiple files upload were managed as single zip file
	{
		return isset($meta['original_filename']) && is_array($file_meta['original_filename']) && !isset($file_meta['is_multiple_file_upload']) ? true : false;
	}
	public function is_dropbox_stored($file_meta)
	{
		return WCUF_DropBox::is_dropbox_file_path($file_meta['absolute_path']);
	}
	public function is_upload_field_content_managed_as_multiple_files($file_meta)
	{
		return isset($file_meta['original_filename']) && isset($file_meta['is_multiple_file_upload']) ? $file_meta['is_multiple_file_upload'] : false;
	}	
	public function get_secure_urls($order_id, $id, $uploaded_metadata)
	{
		if($id != 'none')
		{
			if(!wcuf_get_value_if_set($uploaded_metadata, array($id, 'url'), false))
				return "#";
		}
		else 
		{
			if(!wcuf_get_value_if_set($uploaded_metadata, 'url', false))
				return "#";
		}
			
		
		global $wcuf_option_model, $wcuf_file_model;
		
		$secure_links = $wcuf_option_model->get_all_options('secure_links', false);
		$wcuf_file_model->manage_access_to_order_folder($order_id, $secure_links);
		if($secure_links)
		{
			
			//on the view order template that data structure might not be an array -> it is an order upload field type
			if($id != 'none')
				$metadata = is_array($uploaded_metadata[$id]['url']) ? $uploaded_metadata[$id]['url'] : array($uploaded_metadata[$id]['url']);
			else 
			{
				$metadata = is_array($uploaded_metadata['url']) ? $uploaded_metadata['url'] : array($uploaded_metadata['url']);
				$id = $uploaded_metadata['id'];
			}
			
			$data_to_return = array();
			foreach($metadata as $index => $upload_metadata)
				$data_to_return[] = get_site_url()."?wcuf_order_id={$order_id}&wcuf_upload_id={$id}&wcuf_index={$index}";
				
			return $data_to_return;
		}
		
		return $id != 'none' ? $uploaded_metadata[$id]['url'] : $uploaded_metadata['url'];
	}
	public function can_be_zip_file_created_upload_field_content($file_meta)
	{
		$result = array();
		$counter = 0;
		
		if(is_array($file_meta['source']) && class_exists('ZipArchive'))
			foreach($file_meta['source'] as $index => $source)
			{
				if($source == 'local')
					//$result[] = array('path' => $file_meta['absolute_path'][$counter], 'name' => $file_meta['original_filename'][$counter++]);
					$result[] = array('path' => $file_meta['absolute_path'][$index], 'name' => $file_meta['original_filename'][$index]);
					
			}
		
		return $result;
	}
}
?>