<?php  
class WCUF_ProductPage
{
	var $upload_form_is_active = false;
	var $form_was_already_rendered = false;
	public function __construct()
	{
		
		add_action( 'init', array( &$this, 'init' ));
		add_action( 'wp_ajax_reload_upload_fields', array( &$this, 'ajax_reload_upload_fields' ));
		add_action( 'wp_ajax_nopriv_reload_upload_fields', array( &$this, 'ajax_reload_upload_fields' ));
		
		add_action('wp_head', array( &$this,'add_meta'));
		add_action('wp', array( &$this,'add_headers_meta'));
	}
	function init()
	{
		global $wcuf_option_model;
		$position = 'woocommerce_before_add_to_cart_form';
		try
		{
			$all_options = $wcuf_option_model->get_all_options();
			$position = $all_options['browse_button_position'];
		}catch(Exception $e){};
		
		add_action( $position, array( &$this, 'add_uploads_on_product_page' ), 99 ); 
	}
	function add_headers_meta()
	{
		if(function_exists('is_product') && @is_product())
		{
			header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
			header('Pragma: no-cache');
		}
	}
	function add_meta()
	{
		if(function_exists('is_product') && @is_product())
		{
			
			 echo '<meta http-equiv="Cache-control" content="no-cache">';
			echo '<meta http-equiv="Expires" content="-1">';
		}
	}
	function ajax_reload_upload_fields()
	{
		$product_id = isset($_POST['product_id']) ? $_POST['product_id'] : 0;
		$variation_id = isset($_POST['variation_id']) ? $_POST['variation_id'] : 0;
		$this->add_uploads_on_product_page(true, $product_id,false, $variation_id );
		wp_die();
	}
	function add_uploads_on_product_page($is_ajax_request = false, $post_id = 0, $used_by_shortcode = false, $variation_id = 0)
	{
		if(wcuf_is_request_to_rest_api() || wcuf_get_value_if_set($_POST, 'action', "") == 'flatsome_quickview')
			return;
		
		if($this->form_was_already_rendered)
			return;
		
		if(!wcuf_is_a_supported_browser())
			return;
		
		$is_ajax_request = $is_ajax_request == "" ? false : $is_ajax_request;
		global $wcuf_option_model, $post,$wcuf_wpml_helper,$wcuf_session_model, $wcuf_cart_model, $wcuf_shortcodes, $wcuf_media_model,
		       $wcuf_product_model,$wcuf_text_model, $sitepress, $wcuf_customer_model, $wcuf_upload_field_model, $wcuf_time_model;
		$button_texts  = $wcuf_text_model->get_button_texts();
		$this->upload_form_is_active = false;
		$current_product_id = $post_id == 0 ? $post->ID : $post_id;
		$current_page = 'product';
		$current_item_data = array("product_id" => $current_product_id, "variation_id" => $variation_id, "data" => $variation_id == 0 ? wc_get_product($current_product_id) : wc_get_product($variation_id));
		$is_variable_product_page = is_a(wc_get_product($current_product_id), 'WC_Product_Variable');
		$current_item_cart_id = isset($_GET['cart_item_key']) ? $_GET['cart_item_key'] : ""; //WooCommerce TM Extra Product Options
		$current_item_cart_id = isset($_POST['current_item_cart_id']) ? $_POST['current_item_cart_id'] : $current_item_cart_id ; //Ajax: WooCommerce TM Extra Product Options
		$current_locale = $wcuf_wpml_helper->get_current_locale();
		
		if(wc_get_product($current_product_id) == false || ($variation_id != 0 && wc_get_product($current_product_id) == false))
			return;
		
		 //WooCommerce TM Extra Product Options
		if($current_item_cart_id != "")
			return; 
		
		{
			$item_to_show_upload_fields = $wcuf_cart_model->get_sorted_cart_contents();
			$file_fields_groups = $wcuf_option_model->get_fields_meta_data();
			$style_options = $wcuf_option_model->get_style_options();
			$crop_area_options = $wcuf_option_model->get_crop_area_options();
			$all_options = $wcuf_option_model->get_all_options();
			$additional_button_class = $all_options['additional_button_class'];
			$check_if_standard_managment_is_disabled = $all_options['pages_in_which_standard_upload_fields_managment_is_disabled'];
			$display_summary_box = 'no';
			
			if($this->upload_form_is_active || (in_array($current_page,$check_if_standard_managment_is_disabled) && !$is_ajax_request && !$used_by_shortcode))
			{
				return;
			}
			else
				$this->upload_form_is_active = true;
		
			//Has the current product added to cart?
			$has_already_added_to_cart = false;
			foreach( (array)$item_to_show_upload_fields as $cart_item_key => $item ) 
			{
				if( $current_product_id == $item["product_id"] && !$wcuf_product_model->sold_as_individual_product($item["product_id"], $item["variation_id"]))
					$has_already_added_to_cart = true;
				
				if($current_item_cart_id == wcuf_get_value_if_set($item, 'key', false))  //WooCommerce TM Extra Product Options
				{
					$current_item_data[WCUF_Cart::$sold_as_individual_item_cart_key_name] = $wcuf_cart_model->retrieve_my_unique_individual_id($item);
					$has_already_added_to_cart = true;
				} 
			}
			
			
			
			if(!$is_ajax_request)
			{
				wp_enqueue_script('wcuf-image-all', wcuf_PLUGIN_PATH.'/js/load-image.all.min.js', array('jquery') );
				wp_register_script('wcuf-ajax-upload-file', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-cart-checkout-product-page'.'_'.$current_locale.'.js', array('jquery') );
				wp_register_script('wcuf-product-page', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-product-page.js', array('jquery') );
				wp_register_script('wcuf-multiple-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-multiple-file-manager.js', array('jquery') );
				wp_enqueue_script('wcuf-generic-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-generic-file-uploader.js', array('jquery') );
				wp_enqueue_script( 'wcuf-feedback-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-feedback-manager.js', array('jquery') );
				wp_register_script('wcuf-frontend-ui-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-ui-manager.js', array('jquery') );
				
				
				wp_enqueue_script('wcuf-croppie', wcuf_PLUGIN_PATH.'/js/vendor/croppie.min.js', array('jquery'));
				wp_enqueue_script('wcuf-audio-video-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-audio-video-file-manager.js', array('jquery') );
				wp_enqueue_script('wcuf-imaga-size-checker', wcuf_PLUGIN_PATH.'/js/wcuf-image-size-checker.js', array('jquery') );
				wp_enqueue_script('wcuf-cropbox', wcuf_PLUGIN_PATH.'/js/vendor/cropbox.js', array('jquery') );
				wp_register_script('wcuf-frontend-cropper', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-cropper.js', array('jquery') );
				wp_enqueue_script('wcuf-frontend-cropper-multiple', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-cropper-multiple.js', array('jquery') );
				wp_enqueue_script('wcuf-.magnific-popup', wcuf_PLUGIN_PATH.'/js/vendor/jquery.magnific-popup.js', array('jquery') );
				wp_enqueue_script('wcuf-frontend-multiple-file-uploader', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-multiple-file-uploader.js', array('jquery') );
				
				wp_enqueue_style('wcuf-croppie', wcuf_PLUGIN_PATH.'/css/vendor/croppie.css');
				//wp_enqueue_style('wcuf-cropbox', wcuf_PLUGIN_PATH.'/css/vendor/cropbox.css?'.http_build_query($crop_area_options).'');
				wp_enqueue_style('wcuf-cropbox', wcuf_PLUGIN_PATH.'/css/vendor/cropbox.css');
				wp_enqueue_style('wcuf-magnific-popup', wcuf_PLUGIN_PATH.'/css/vendor/magnific-popup.css');
				wp_enqueue_style('wcuf-frontend-common', wcuf_PLUGIN_PATH.'/css/wcuf-frontend-common.css');
				wp_enqueue_style('wcuf-frontend-product-page', wcuf_PLUGIN_PATH.'/css/wcuf-frontend-product-page.css');
				wp_enqueue_script('wcuf-pdf', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdf.js', array('jquery')); 
				wp_enqueue_script('wcuf-pdf-2', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdfThumbnails.js', array('jquery'));
			
				include WCUF_PLUGIN_ABS_PATH.'/template/alert_popup.php';
				echo '<div id="wcuf_product_ajax_container_loading_container"></div>';
				echo '<div id="wcuf_product_ajax_container" style="display:none;">';
				
			}
			if(file_exists ( get_theme_file_path()."/wcuf/checkout_cart_product_page_template.php" ))
				include get_theme_file_path()."/wcuf/checkout_cart_product_page_template.php";
			else
				include WCUF_PLUGIN_ABS_PATH.'/template/checkout_cart_product_page_template.php';
			$this->form_was_already_rendered = true;
			if(!$is_ajax_request)
			{
				echo '</div>';		
				$js_options = array(
									'cart_quantity_as_number_of_uploaded_files' => $all_options['cart_quantity_as_number_of_uploaded_files'] ? 'true' : 'false',
									'icon_path' => wcuf_PLUGIN_PATH."/img/icons/",
									'current_item_cart_id' => $current_item_cart_id,
									'current_product_id' => $current_product_id,
									'current_page' => $current_page,
									'exists_a_field_to_show_before_adding_item_to_cart' => $exists_a_field_to_show_before_adding_item_to_cart ? "true" : "false",
									'has_already_added_to_cart' => isset($has_already_added_to_cart) && $has_already_added_to_cart? "true" : "false",
									'exists_at_least_one_upload_field_bounded_to_variations' => $exists_at_least_one_upload_field_bounded_to_variations ? "true" : "false",
									'exists_at_least_one_upload_field_bounded_to_gateway' => $exists_at_least_one_upload_field_bounded_to_gateway ? "true" : "false",
									'hide_add_to_cart_button' => $all_options['mandatory_hide_add_to_cart_button'] ? 'yes' : 'no',
									'crop_method' => $all_options['crop_rotation_method'],
									'security' => wp_create_nonce('wcuf_security_upload')
								);
				
				wp_localize_script( 'wcuf-frontend-ui-manager', 'wcuf_options', $js_options );				
				wp_localize_script( 'wcuf-product-page', 'wcuf_options', $js_options );
				wp_localize_script( 'wcuf-multiple-file-manager', 'wcuf_options', $js_options );
				wp_localize_script( 'wcuf-ajax-upload-file', 'wcuf_options', $js_options );
				wp_localize_script( 'wcuf-frontend-cropper', 'wcuf_options', $js_options );		

				wp_enqueue_script( 'wcuf-frontend-ui-manager' );
				wp_enqueue_script( 'wcuf-ajax-upload-file' );
				wp_enqueue_script( 'wcuf-product-page' );
				wp_enqueue_script( 'wcuf-multiple-file-manager' );
				wp_enqueue_script( 'wcuf-frontend-cropper' );			
				wp_enqueue_script( 'wcuf-global-error-catcher', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-global-error-catcher.js', array('jquery') );				
			}
		}
	}
}
?>