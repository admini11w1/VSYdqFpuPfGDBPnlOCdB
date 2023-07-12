<?php 
class WCUF_CommonHooks
{
	var $uploaded_files_metadata;
	function __construct()
	{
		add_action( 'woocommerce_order_item_meta_end', array( &$this, 'display_order_item_meta' ), 20, 3 ); //This is fired both by emails and order details page while rendering item table
		
	}
	function debug( $order, $sent_to_admin = false, $plain_text = false, $email = '' )
	{
		wcuf_var_dump("here");
	}
	function display_order_item_meta($item_id, $item, $order )
	{
		global $wcuf_order_model, $wcuf_upload_field_model, $wcuf_file_model, $wcuf_product_model, $wcuf_media_model, $wcuf_option_model, $wcuf_time_model;		
		$order_id = $order->get_id();
		
		if(did_action('woocommerce_email_order_details') == 0 || !$wcuf_option_model->show_previews_on_emails_item_table())
			return;
		
		$uploaded_files = wcuf_get_value_if_set($this->uploaded_files_metadata, $order_id, false) != false ? $this->uploaded_files_metadata[$order_id] : $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($order_id); 
		$this->uploaded_files_metadata[$order_id] = $uploaded_files;
		
		$product_id = $item->get_product_id();
		$product_variation_id = $item->get_variation_id();
		$product = wc_get_product(isset($product_variation_id ) && $product_variation_id != 0 ? $product_variation_id  : $product_id);
		
		//Product upload preview
		if(isset($product) && $product != false)
		{
			$uploaded_files_metadata = wcuf_get_value_if_set($this->uploaded_files_metadata, $order_id, false) != false ? $this->uploaded_files_metadata[$order_id] : $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($order_id);
			$this->uploaded_files_metadata[$order_id] = $uploaded_files_metadata;
			
			//Compute which files are images and which not
			$current_product_uploads = $product_specific_uploads  = array();
			foreach($uploaded_files_metadata as $upload_field_id => $file_meta)
			{
				$product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
				$variation_id = $product->is_type('variation') ? $product->get_id() : 0;
				$unique_id = $wcuf_order_model->read_order_item_meta($item,'_wcuf_sold_as_individual_unique_key');
				$item_id_data = array('product_id' => $product_id , 'variant_id'=> $variation_id , 'unique_product_id'=> $unique_id  );
				
				$ids = $wcuf_file_model->get_product_ids_and_field_id_by_file_id("order_".$upload_field_id);		
				$is_the_uploaded_assocaited_to_the_product = $wcuf_product_model->is_the_same_product($item_id_data, $ids);
				
				if($is_the_uploaded_assocaited_to_the_product)
				{
					$current_product_uploads[$upload_field_id] = $file_meta;
					$product_specific_uploads[$upload_field_id] = $upload_field_id;
				}
					
			}

			include WCUF_PLUGIN_ABS_PATH.'/template/email_product_uploads_preview.php';
		}
	}
}
?>