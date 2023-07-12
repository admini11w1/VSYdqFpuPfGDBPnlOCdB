<?php  
class WCUF_CheckoutPage
{
	var $upload_form_is_active = false;
	var $popup_can_be_rendered = true;
	var $upload_metadata_saved_on_order = false;
	public function __construct()
	{
		
		add_action( 'init', array( &$this, 'init' ));
		//Upload form
		add_action( 'woocommerce_after_checkout_form', array( &$this, 'add_popup' ), 10, 1 ); //Checkout page
		
		//Before Checkout
		add_action('woocommerce_checkout_process', array( &$this, 'check_required_uploads_before_checkout_is_complete' )); 
		
		add_action( 'wp_ajax_reload_upload_fields_on_checkout', array( &$this, 'ajax_add_uploads_checkout_page' ));
		add_action( 'wp_ajax_nopriv_reload_upload_fields_on_checkout', array( &$this, 'ajax_add_uploads_checkout_page' ));
		
		add_action('wp', array( &$this,'add_headers_meta'));
		add_action('wp_head', array( &$this,'add_meta'));
	}
	function init()
	{
		global $wcuf_option_model;
		$position = 'woocommerce_after_checkout_billing_form';
		$checkout_file_association_type = 'thank_you';
		try
		{
			$all_options = $wcuf_option_model->get_all_options();
			$position = $all_options['checkout_page_positioning'];
			$checkout_file_association_type =  $all_options['checkout_file_association_method'];
		}catch(Exception $e){};
		
		add_action( $position, array( &$this, 'add_uploads_checkout_page' ), 10, 1 ); //Checkout page
		
		if(defined('WC_VERSION') && version_compare( WC_VERSION, '3.0.7', '<' ))
			add_action('woocommerce_add_order_item_meta', array( &$this, 'update_order_item_meta' ),10,3); //Update order items meta
		else
			add_action('woocommerce_new_order_item', array( &$this, 'update_order_item_meta' ),10,3);
		
		
		//After Checkout
		if($checkout_file_association_type != 'thank_you')	
			add_action('woocommerce_checkout_order_processed', array( &$this, 'save_uploads_after_checkout' )); //After checkout
		else
			add_action('woocommerce_thankyou', array( &$this, 'save_uploads_after_checkout' ), 1, 1); //After checkout
	}
	function ajax_add_uploads_checkout_page() 
	{
		$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'none';
		$shipping_method = isset($_POST['shipping_method']) ? $_POST['shipping_method'] : 'none';
		
		$this->add_uploads_checkout_page("",true, false, $payment_method, $shipping_method);
	}
	function add_popup($checkout)
	{
		if(wcuf_is_request_to_rest_api())
			return;
		
		global $wcuf_option_model;
		if(!$this->popup_can_be_rendered)
			return;
		
		$this->popup_can_be_rendered = false;
		$all_options = $wcuf_option_model->get_all_options();
		include WCUF_PLUGIN_ABS_PATH.'/template/alert_popup.php';
	}
	function add_uploads_checkout_page($checkout,$is_ajax_request = false, $used_by_shortcode = false, $current_payment_method = 'none', $current_shipping_method = 'none') 
	{
		if(wcuf_is_request_to_rest_api())
			return;
		
		if(!wcuf_is_a_supported_browser())
			return;
		
		
		global $wcuf_option_model, $wcuf_order_model, $wcuf_wpml_helper, $wcuf_session_model, $wcuf_cart_model, $wcuf_media_model,
		       $wcuf_shortcodes,$wcuf_product_model,$wcuf_text_model, $sitepress, $wcuf_customer_model, $wcuf_upload_field_model, $wcuf_time_model;
		$button_texts  = $wcuf_text_model->get_button_texts();
		$item_to_show_upload_fields = $wcuf_cart_model->get_sorted_cart_contents();
		$file_order_metadata = array();
		$file_fields_groups = $wcuf_option_model->get_fields_meta_data();
		$style_options = $wcuf_option_model->get_style_options();
		$crop_area_options = $wcuf_option_model->get_crop_area_options();
		$display_summary_box = $wcuf_option_model->get_all_options('display_summary_box_strategy');
		$summary_box_info_to_display = $wcuf_option_model->get_all_options('summary_box_info_to_display');
		$all_options = $wcuf_option_model->get_all_options();
		$additional_button_class = $all_options['additional_button_class'];
		$check_if_standard_managment_is_disabled = $all_options['pages_in_which_standard_upload_fields_managment_is_disabled'];
		$current_page = 'checkout';
		$current_locale = $wcuf_wpml_helper->get_current_locale();
		
		
		//When rendering on checkout page, before "place order" the upload area is reloaded twice. In order to avoid to lose the posted value, check in this way.
		$current_payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : $current_payment_method; 
		$dafault_choosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' ) ? WC()->session->get( 'chosen_shipping_methods' )[0] : "";
		$current_shipping_method = $current_shipping_method == 'none' ? $dafault_choosen_shipping_method : $current_shipping_method;
		$current_shipping_method = isset($_POST['shipping_method']) ? $_POST['shipping_method'] : $current_shipping_method; 
		
		if($this->upload_form_is_active || (in_array($current_page,$check_if_standard_managment_is_disabled) && !$is_ajax_request && !$used_by_shortcode) )
		{
			$this->popup_can_be_rendered = false;
			return;
		}
		else
			$this->upload_form_is_active = true;
		
		if(!$is_ajax_request)
		{
			wp_enqueue_script('wcuf-load-image', wcuf_PLUGIN_PATH. '/js/load-image.all.min.js' ,array('jquery')); 
			wp_register_script('wcuf-ajax-upload-file', wcuf_PLUGIN_PATH. '/js/wcuf-frontend-cart-checkout-product-page'.'_'.$current_locale.'.js' ,array('jquery'));  
			wp_register_script( 'wcuf-multiple-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-multiple-file-manager.js', array('jquery') );
			wp_enqueue_script( 'wcuf-feedback-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-feedback-manager.js', array('jquery') );
			wp_register_script('wcuf-frontend-ui-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-ui-manager.js', array('jquery'));
			
			
			wp_enqueue_script('wcuf-audio-video-file-manager', wcuf_PLUGIN_PATH. '/js/wcuf-audio-video-file-manager.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-image-size-checker', wcuf_PLUGIN_PATH. '/js/wcuf-image-size-checker.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-cropbox', wcuf_PLUGIN_PATH. '/js/vendor/cropbox.js' ,array('jquery')); 
			wp_register_script('wcuf-image-cropper', wcuf_PLUGIN_PATH. '/js/wcuf-frontend-cropper.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-image-cropper-multiple', wcuf_PLUGIN_PATH. '/js/wcuf-frontend-cropper-multiple.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-magnific-popup', wcuf_PLUGIN_PATH.'/js/vendor/jquery.magnific-popup.js', array('jquery'));
			wp_enqueue_script('wcuf-checkout-page', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-checkout-page.js', array('jquery'));
			wp_enqueue_script('wcuf-frontend-multiple-file-uploader', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-multiple-file-uploader.js', array('jquery'));
			wp_enqueue_script( 'wcuf-generic-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-generic-file-uploader.js', array('jquery') );
			wp_enqueue_script('wcuf-croppie.js', wcuf_PLUGIN_PATH.'/js/vendor/croppie.min.js', array('jquery'));
			wp_enqueue_script('wcuf-pdf', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdf.js', array('jquery')); 
			wp_enqueue_script('wcuf-pdf-2', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdfThumbnails.js', array('jquery'));
			
			wp_enqueue_style('wcuf-magnific-popup', wcuf_PLUGIN_PATH.'/css/vendor/magnific-popup.css');	
			wp_enqueue_style('wcuf-frontend-common', wcuf_PLUGIN_PATH.'/css/wcuf-frontend-common.css');		
			wp_enqueue_style('wcuf-croppie', wcuf_PLUGIN_PATH.'/css/vendor/croppie.css');			
			wp_enqueue_style('wcuf-cropbox', wcuf_PLUGIN_PATH.'/css/vendor/cropbox.css' ); 
			wp_enqueue_style('wcuf-checkout', wcuf_PLUGIN_PATH. '/css/wcuf-frontend-checkout.css' );  
			
		}
		
		if(!$is_ajax_request)
		{
			//include WCUF_PLUGIN_ABS_PATH.'/template/alert_popup.php';
			echo '<div id="wcuf_checkout_ajax_container_loading_container"></div>';
			echo '<div id="wcuf_checkout_ajax_container" style="opacity:0;">';
		}
		if(file_exists ( get_theme_file_path()."/wcuf/checkout_cart_product_page_template.php" ))
				include get_theme_file_path()."/wcuf/checkout_cart_product_page_template.php";
			else
				include WCUF_PLUGIN_ABS_PATH.'/template/checkout_cart_product_page_template.php';
		if(!$is_ajax_request)	
		{
			echo '</div>';
			$js_options = array(
				'icon_path' => wcuf_PLUGIN_PATH."/img/icons/",
				'current_item_cart_id' => "",
				'current_product_id' => 0,
				'current_page' => $current_page,
				'exists_a_field_to_show_before_adding_item_to_cart' => $exists_a_field_to_show_before_adding_item_to_cart ? "true" : "false",
				'has_already_added_to_cart' => isset($has_already_added_to_cart) && $has_already_added_to_cart ? "true" : "false",
				'exists_at_least_one_upload_field_bounded_to_variations' => $exists_at_least_one_upload_field_bounded_to_variations ? "true" : "false",
				'exists_at_least_one_upload_field_bounded_to_gateway' => $exists_at_least_one_upload_field_bounded_to_gateway ? "true" : "false",
				'exists_at_least_one_upload_field_bounded_to_shipping_method' => $exists_at_least_one_upload_field_bounded_to_shipping_method ? "true" : "false",
				'hide_add_to_cart_button' => $all_options['mandatory_hide_add_to_cart_button'] ? 'yes' : 'no',
				'crop_method' => $all_options['crop_rotation_method'],
				'security' => wp_create_nonce('wcuf_security_upload')
			);
			
			wp_localize_script( 'wcuf-frontend-ui-manager', 'wcuf_options', $js_options );
			wp_localize_script( 'wcuf-multiple-file-manager', 'wcuf_options', $js_options );
			wp_localize_script( 'wcuf-ajax-upload-file', 'wcuf_options', $js_options );
			wp_localize_script( 'wcuf-image-cropper', 'wcuf_options', $js_options );
			
			wp_enqueue_script( 'wcuf-ajax-upload-file' );
			wp_enqueue_script( 'wcuf-frontend-ui-manager' );  
			wp_enqueue_script( 'wcuf-multiple-file-manager' ); 
			wp_enqueue_script( 'wcuf-image-cropper' );
			wp_enqueue_script( 'wcuf-global-error-catcher', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-global-error-catcher.js', array('jquery') );			
		}
		else
		{
			wp_die();
		}
	}
	function check_required_uploads_before_checkout_is_complete($checkout_fields)
	{
		global $wcuf_product_model,$woocommerce, $wcuf_cart_model;
		$wcuf_cart_model->cart_update_validation();
		$cart = $woocommerce->cart->cart_contents;
		$upload_fields_already_processed = array();
		foreach((array)$cart as $cart_item)
		{
			$product = array();
			$product['product_id'] = $cart_item['product_id'];
			$product['variation_id'] = !isset($cart_item['variation_id']) || $cart_item['variation_id'] == "" ? 0 : $cart_item['variation_id'];
			$product[WCUF_Cart::$sold_as_individual_item_cart_key_name] = isset($cart_item[WCUF_Cart::$sold_as_individual_item_cart_key_name]) ? $cart_item[WCUF_Cart::$sold_as_individual_item_cart_key_name] : null;
			
			$upload_fields_to_perform_upload = $wcuf_product_model->has_an_upload_in_its_single_page($product, true, $cart_item["quantity"]);
			if(!empty($upload_fields_to_perform_upload))
				foreach((array)$upload_fields_to_perform_upload as $field_id => $upload_field)
				{
					if(in_array($field_id,$upload_fields_already_processed))
						continue;
						
					$upload_fields_already_processed[] = $field_id;
					if(isset($upload_field['num_uploaded_files_error']) && $upload_field['num_uploaded_files_error'])
					{
						if($upload_field['min_uploadable_files'] == $upload_field['max_uploadable_files'])
						{
							$additional_product_text = $upload_field['disable_stacking'] ? sprintf(esc_html__(" for product <strong>%s</strong>",'woocommerce-files-upload'), '<a href="'.get_permalink( $upload_field['product_id'] ).'" target ="_blank">'.$upload_field['product_name'].'</a>') : "";
							wc_add_notice( wcuf_html_escape_allowing_special_tags(sprintf(__('Upload <strong>%s</strong>%s requires <strong>%s file(s)</strong>. You have uploaded: <strong>%s file(s)</strong>. Please upload the requested number of files.','woocommerce-files-upload'), $upload_field['upload_field_name'], $additional_product_text, $upload_field['max_uploadable_files'],  $upload_field['num_uploaded_files']), false) ,'error');
							
						}
						else 
						{
							$additional_product_text = $upload_field['disable_stacking'] ? sprintf(esc_html__(" for product <strong>%s</strong>",'woocommerce-files-upload'), '<a href="'.get_permalink( $upload_field['product_id'] ).'" target ="_blank">'.$upload_field['product_name'].'</a>') : "";
							$num_uploaded_files_error = wcuf_html_escape_allowing_special_tags(sprintf(__("Upload <strong>%s</strong>%s requires", 'woocommerce-files-upload'), $upload_field['upload_field_name'], $additional_product_text), false);
							$num_uploaded_files_error .= $upload_field['min_uploadable_files'] != 0 ? sprintf(esc_html__(" a minimum of <strong>%s file(s)</strong>", 'woocommerce-files-upload'), $upload_field['min_uploadable_files']) : "" ;
							$num_uploaded_files_error .= $upload_field['max_uploadable_files'] != 0 && $upload_field['min_uploadable_files'] != 0 ? esc_html__(" and ", 'woocommerce-files-upload') : "" ;
							$num_uploaded_files_error .= $upload_field['min_uploadable_files'] != 0 ?  sprintf(esc_html__(" a maximum of <strong>%s file(s)</strong>", 'woocommerce-files-upload'),$upload_field['max_uploadable_files']): "" ;
							$num_uploaded_files_error .= ". ".esc_html__('Please upload all the required files.','woocommerce-files-upload');
							wc_add_notice($num_uploaded_files_error,'error');
						}
					}
					else
						wc_add_notice( wcuf_html_escape_allowing_special_tags(sprintf(__('Upload <strong>%s</strong> for product <strong>%s</strong> has not been performed.','woocommerce-files-upload'), $upload_field['upload_field_name'],'<a href="'.get_permalink( $upload_field['product_id'] ).'" target ="_blank">'.$upload_field['product_name'].'</a>'), false) ,'error');
				}					
					
		}
		
	}
	function update_order_item_meta($item_id, $values, $cart_item_key)
	{
		global $wcuf_cart_model;
		if ( is_a( $values, 'WC_Order_Item_Product' ) ) 
		{
			if(!isset($values->legacy_values))
				return;
			
			$values = $values->legacy_values;
			
		} 
		
		if(isset($values[WCUF_Cart::$sold_as_individual_item_cart_key_name]))
		{
			wc_add_order_item_meta($item_id, '_wcuf_sold_as_individual_unique_key', $values[WCUF_Cart::$sold_as_individual_item_cart_key_name], true);
			
		}
		
		//Extra price meta 
		$unique_id = isset($values[WCUF_Cart::$sold_as_individual_item_cart_key_name]) ? $values[WCUF_Cart::$sold_as_individual_item_cart_key_name] : 0;
		$item_data = array('product_id' => $values['product_id'] , 'variant_id'=> $values['variation_id'] , 'unique_product_id'=> $unique_id  );
		$new_item_price = $wcuf_cart_model->apply_or_get_extra_upload_costs(false, $item_data);
		if(isset($values['data']))
			foreach($new_item_price['additional_data'] as $data)
				{
					$cost = 'yes' !== get_option( 'woocommerce_prices_include_tax' ) ? WCUF_Tax::apply_tax_to_price( $values['data'], $data['single_cost']) : $data['single_cost'];  //total_cost
					$quantity_text = $data['quantity'] > 1 ? ' X '.$data['quantity'] : ""; 
					wc_add_order_item_meta($item_id, $data['label'], wc_price($cost).$quantity_text, true);
				}
	}
	function save_uploads_after_checkout( $order_id)
	{
		
		
		global $wcuf_file_model, $wcuf_option_model, $wcuf_session_model, $wcuf_upload_field_model;
		

		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
		  return $order_id;
		 
		$temp_uploads = $wcuf_session_model->get_item_data();
		
		if(!empty($temp_uploads))
		{
			$order = wc_get_order($order_id);
			$status = $order->get_status();
			
			
			$file_fields_groups =  $wcuf_option_model->get_fields_meta_data();
			
			$file_order_metadata = $wcuf_upload_field_model->get_uploaded_files_meta_data_by_order_id($order_id);
			$file_order_metadata = $wcuf_file_model->upload_files($order, $file_order_metadata, $file_fields_groups, $temp_uploads);
			
		}
		//wp_die();
		$wcuf_session_model->remove_item_data();
		$this->upload_metadata_saved_on_order = true;
	}
	function add_meta()
	{
		if(function_exists('is_checkout') && @is_checkout())
		{
			
			 echo '<meta http-equiv="Cache-control" content="no-cache">';
			echo '<meta http-equiv="Expires" content="-1">';
		}
	}
	function add_headers_meta()
	{
		if(function_exists('is_checkout') && @is_checkout())
		{
			header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
			header('Pragma: no-cache');
		}
	}
}
?>