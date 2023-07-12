<?php
class WCUF_OrderDetailAddon
{
	var $current_order;
	var $email_sender;
	var $uploaded_files_metadata = array();
	public function __construct()
	{
		add_action( 'add_meta_boxes', array( &$this, 'woocommerce_metaboxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( &$this, 'woocommerce_process_shop_ordermeta' ), 5, 2 );
		add_action( 'woocommerce_after_order_itemmeta', array( &$this, 'display_order_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( &$this, 'hide_private_metakeys' )); //hidden wcmca keys
		
	}
	
	function display_order_item_meta($item_id, $item, $_product )
	{
		if (get_class($item) != "WC_Order_Item_Product")
			return; 
		
		global $wcuf_order_model, $post, $wcuf_media_model, $wcuf_upload_field_model, $wcuf_file_model, $wcuf_option_model, $wcuf_product_model;		
		$item_individual_id = $wcuf_order_model->read_order_item_meta($item,'_wcuf_sold_as_individual_unique_key');
		
		if($item_individual_id)
			echo "<strong>".esc_html__('Individual ID:', 'woocommerce-files-upload')."</strong> ".$item_individual_id;
		
		//Product upload preview
		if(isset($_product) && isset($post))
		{
			$order_id = $post->ID;		
			$uploaded_files_metadata = wcuf_get_value_if_set($this->uploaded_files_metadata, $order_id, false) != false ? $this->uploaded_files_metadata[$order_id] : $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($order_id);
			$this->uploaded_files_metadata[$order_id] = $uploaded_files_metadata;
			
			//Compute which files are images and which not
			$current_product_uploads = $product_specific_uploads  = array();
			foreach($uploaded_files_metadata as $upload_field_id => $file_meta)
			{
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();
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
			$disable_image_preview = $wcuf_option_model->disable_admin_order_page_items_table_image_preview();
			if(file_exists ( get_theme_file_path()."/wcuf/admin_order_details_product_uploads_preview.php" ))
				include get_theme_file_path()."/wcuf/admin_order_details_product_uploads_preview.php";
			else
				include WCUF_PLUGIN_ABS_PATH.'/template/admin_order_details_product_uploads_preview.php';
		}
	}
	public function hide_private_metakeys($keys)
	{
		$keys[] = '_wcuf_sold_as_individual_unique_key';
		$keys[] = 'wcuf_sold_as_individual_unique_key';
		return $keys;
	}
	function woocommerce_process_shop_ordermeta( $post_id, $post ) 
	{
		global $wcuf_file_model, $wcuf_option_model, $wcuf_upload_field_model;
		//Used when admin save order from order detail page in backend
		if(isset($_POST['files_to_delete']))
		{
			$file_order_metadata = wcuf_get_value_if_set($this->uploaded_files_metadata, $post_id, false) != false ? $this->uploaded_files_metadata[$post_id] : $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($post_id); 
			$this->uploaded_files_metadata[$order_id] = $file_order_metadata;
			
			foreach($_POST['files_to_delete'] as $value)
			{
				$file_order_metadata = $wcuf_file_model->delete_file($value, $file_order_metadata, $post_id);
			}
		}
	}
	function woocommerce_metaboxes() 
	{

		add_meta_box( 'woocommerce-files-upload', esc_html__('File(s) uploaded', 'woocommerce-files-upload'), array( &$this, 'woocommerce_order_uploaded_files_box' ), 'shop_order', 'side', 'high');

	}
	function woocommerce_order_uploaded_files_box($post) 
	{
		global $wcuf_option_model, $wcuf_upload_field_model, $wcuf_media_model;
		
		$file_fields_meta = $wcuf_option_model->get_fields_meta_data();
		$uploaded_files = wcuf_get_value_if_set($this->uploaded_files_metadata, $post->ID, false) != false ? $this->uploaded_files_metadata[$post->ID] : $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($post->ID); 
		$this->uploaded_files_metadata[$post->ID] = $uploaded_files;
		$num_of_active_upload_fields = 0;
		$number_of_local_upload_fields = 0;
		$disable_force_download_on_admin_order_page =  $wcuf_option_model->get_all_options('disable_force_download_on_admin_order_page');
		$disable_image_preview = $wcuf_option_model->disable_admin_order_page_items_table_image_preview();
		
		wp_enqueue_style( 'wcuf-admin-order-details-page', wcuf_PLUGIN_PATH.'/css/wcuf-admin-order-details-page.css' );
		
		wp_register_script( 'wcuf-admin-order-details-page', wcuf_PLUGIN_PATH.'/js/wcuf-admin-order-details-page.js' );
		wp_localize_script( 'wcuf-admin-order-details-page', 'wcuf', array('order_id' => $post->ID,
																		   'delete_msg' => esc_html__('Are you sure?', 'woocommerce-files-upload')) );
		wp_enqueue_script( 'wcuf-admin-order-details-page' );
		wp_enqueue_script('wcuf-pdf', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdf.js', array('jquery'));
		wp_enqueue_script('wcuf-pdf-2', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdfThumbnails.js', array('jquery'));
		
		?>
		<div id="upload-box">
		<p><?php wcuf_html_escape_allowing_special_tags(__('Here are listed uploaded files <strong>grouped per upload field</strong>.', 'woocommerce-files-upload')); ?>
		<br/><i><?php esc_html_e('Note: Click "Save Order" button after one or more file has been delete in order to save changes.', 'woocommerce-files-upload'); ?></i></p>
			<?php if(empty($uploaded_files)): echo '<p><strong>'.esc_html__('Customer hasn\'t uploaded any file...yet.', 'woocommerce-files-upload').'</strong></p>'; 
			else:?>
			<ul class="totals">
			 <?php foreach($uploaded_files as $upload_field_id => $file_meta): 
				$num_of_active_upload_fields = count($file_meta['original_filename']) > 0 ? $num_of_active_upload_fields+1 : $num_of_active_upload_fields;
				
				$is_zip = $wcuf_upload_field_model->is_upload_field_content_managed_as_zip($file_meta);
				$is_multiple_files = $wcuf_upload_field_model->is_upload_field_content_managed_as_multiple_files($file_meta); 
				$original_name = "";
				$file_meta['url'] = $wcuf_upload_field_model->get_secure_urls($post->ID, $upload_field_id, $uploaded_files);
				
				if($is_zip || $is_multiple_files)
					$original_name = esc_html__('Multiple files', 'woocommerce-files-upload');
				else if(isset($file_meta['original_filename']))
					$original_name = !is_array($file_meta['original_filename']) ? $file_meta['original_filename'] : $file_meta['original_filename'][0];
				
				if($original_name == "")
					continue;
				?>
				<li style="margin-bottom:40px;">
					<h4 class="wcuf_upload_field_title">
					<?php echo $file_meta['title']." : ".$original_name;?></h4>
					<?php 
						$quantity = 1;
						if(!$is_zip && !$is_multiple_files)
							echo esc_html__('Quantity: ', 'woocommerce-files-upload')."<i>".$quantity."</i></br></br>";
					
					 
					if($is_zip || $is_multiple_files) //old multiple file managment
					{
						$files_name = "<p><ol>";
						foreach( $file_meta['original_filename'] as $counter => $temp_file_name)
						{
							if(isset($file_meta['quantity'][$counter]))
								$quantity = is_array($file_meta['quantity'][$counter]) ? array_sum($file_meta['quantity'][$counter]) : $file_meta['quantity'][$counter];
							
							$delete_icon = '<i data-id="'.$counter.'" data-field-id="'.$upload_field_id.'" class="wcuf_delete_single_file_stored_on_server wcuf_delete_file_icon"></i>';
								
							if($is_zip) //No longer used
							{
								$zip_file_name = basename ($file_meta['absolute_path']);
								if(!$wcuf_upload_field_model->is_dropbox_stored($file_meta))
									$files_name .= '<li><a class="wcuf_link" target="_blank" href="'.get_site_url().'?wcuf_zip_name='.$zip_file_name.'&wcuf_single_file_name='.$temp_file_name.'&wcuf_order_id='.$post->ID.'">'.$temp_file_name.'</a> ('.esc_html__('Quantity: ', 'woocommerce-files-upload').$quantity.')</li>';
								else
									$files_name .= '<li>'.$temp_file_name.' ('.esc_html__('Quantity: ', 'woocommerce-files-upload').$quantity.')</li>';
							}
							else
							{
								
								$preview_html = $disable_image_preview ? "" : $wcuf_media_model->get_media_preview_html($file_meta, $temp_file_name, $is_zip, $post->ID, $counter, false, array('preview_type' => 'cart_product_preview'));
								$download_attribute = $disable_force_download_on_admin_order_page ? "  " : " download ";
								$files_name .= '<li><a class="wcuf_link" target="_blank" href="'.$file_meta['url'][$counter].'" '.$download_attribute.'>'.$preview_html.'<br/>'.$temp_file_name.'</a> '.$delete_icon.' ('.esc_html__('Quantity: ', 'woocommerce-files-upload').$quantity.')</li>';
							}
							$counter++;
						}
						$files_name .= "</ol></p>";
						echo $files_name;
					}
					?>
					
					
					<?php if(isset($file_meta['user_feedback']) && $file_meta['user_feedback'] != "" && $file_meta['user_feedback'] != "undefined"):?>
						<p style="margin-top:5px;">
							<strong><?php echo esc_html_e('User feedback', 'woocommerce-files-upload'); ?></strong></br>
							<?php echo $file_meta['user_feedback'];?>
						</p>
					<?php endif;?>
					<?php $media_counter = 0;
						if(isset($file_meta['ID3_info']) && $file_meta['ID3_info'] != "none"): ?>
						<p style="margin-top:5px;">
							<strong><?php echo esc_html_e('Media info', 'woocommerce-files-upload') ?></strong></br>
							<?php	foreach($file_meta['ID3_info'] as $file_media_info):?>
											<?php if($media_counter > 0) echo "<br/>";?>
											<?php  echo esc_html__('Name: ', 'woocommerce-files-upload')."<i>".$file_media_info['file_name']."</i>";?></br> 
											<?php echo esc_html__('Duration: ', 'woocommerce-files-upload')."<i>".$file_media_info['playtime_string']."</i>"?></br>
											<!-- <?php echo esc_html__('Quantity: ', 'woocommerce-files-upload')."<i>".$file_media_info['quantity']."</i>"?></br> -->
											<?php $media_counter++; 
									endforeach; ?>
						</p>
					<?php endif;?>
						
					<p style="margin-top:3px;">
						<?php if($is_zip || !$is_multiple_files): 
							$file_url = !is_array($file_meta['url']) ? $file_meta['url'] : $file_meta['url'][0];
						?>
							<a target="_blank" class="button button-primary wcuf_primary_button" style="text-decoration:none; color:white;  margin-bottom:4px;" href="<?php echo $file_url; ?>"><?php esc_html_e('Download', 'woocommerce-files-upload'); ?></a>
						<?php elseif($wcuf_upload_field_model->can_be_zip_file_created_upload_field_content($file_meta)):
							$number_of_local_upload_fields++;
							?>
							<p><strong><?php esc_html_e('Note:', 'woocommerce-files-upload') ?></strong> <?php esc_html_e('Only local files can be zipped.', 'woocommerce-files-upload') ?>
							<a target="_blank" class="button button-primary wcuf_primary_button" style="text-decoration:none; color:white;" href="<?php echo get_site_url();?>?wcuf_create_zip_for_field=<?php echo $file_meta['id']; ?>&wcuf_order_id=<?php echo $post->ID;?>"><?php esc_html_e('Download as zip', 'woocommerce-files-upload'); ?></a>
							</p>
						<?php endif; ?>
						<input  type="submit" class="button delete_button wcuf_delete_button" data-fileid="<?php echo $file_meta['id'] ?>" value="<?php esc_html_e('Delete file(s)', 'woocommerce-files-upload'); ?>" ></input>
					</p>
				</li>
			  <?php endforeach;?>
			</ul>
			<?php if($num_of_active_upload_fields > 0 && $number_of_local_upload_fields>1): ?>
			<a class="button button-primary wcuf_primary_button" id="wcuf_download_files_as_single_zip" target="_blank" href="<?php echo admin_url( "?wcuf_create_single_zip_for_order={$post->ID}" ); ?>"><?php esc_html_e('Download all files as zip', 'woocommerce-files-upload') ?></a>
			<?php endif; ?>
			<a class="button button-primary wcuf_primary_button" target="_blank" href="<?php echo admin_url( "?wcuf_page=uploads_details_sheet&wcuf_order_id={$post->ID}" ); ?>"><?php esc_html_e('Uploads details sheet', 'woocommerce-files-upload') ?></a>
			<?php endif; ?>
		</div>
		<div class="clear"></div>
		<?php 
	}	
}
?>