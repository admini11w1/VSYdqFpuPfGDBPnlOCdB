<?php 
class WCUF_EmailNotifier
{
	public function __construct() 
	{
		add_filter( 'woocommerce_email_attachments', array( &$this, 'add_attachments' ), 10, 3);
	}
	public function add_attachments( $attachments , $status, $order ) 
	{
		global $wcuf_option_model, $wcuf_upload_field_model;
		
		$reflect = is_object($order) ? new ReflectionClass($order) : "none";
		$ref_class_name = $reflect == "none" ? "none" : $reflect->getShortName();
		if(!isset($order) || !isset($status) || !isset($attachments) || !is_object($order) || (get_class($order) != "WC_Order" && get_class($order) != "WC_Admin_Order" && $ref_class_name != "Order") || $status != 'new_order')
			return $attachments;
		
		$file_fields_groups =  $wcuf_option_model->get_fields_meta_data();
		$file_order_metadata = $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($order->get_id());
		
		foreach($file_order_metadata as $fieldname_id => $file_data)
		 {
			$original_option_id = $file_data["id"];
			$result = explode("-", $original_option_id);
			$original_option_id = $result[0];
			
			foreach($file_fields_groups as $option)
			{
				if($option['id'] == $original_option_id && wcuf_get_value_if_set($option, 'email_attach_files_to_new_order', false)  )
				{
					if(isset($file_order_metadata[$file_data["id"]]['absolute_path'])) //absolute_path
						foreach($file_order_metadata[$file_data["id"]]['absolute_path'] as $element_id => $url)
							if($file_order_metadata[$file_data["id"]]['source'][$element_id] == 'local' )
								$attachments[] = $url;					
				}
			}
		}
		
		return $attachments;
	}
}
?>