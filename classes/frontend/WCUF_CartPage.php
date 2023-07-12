<?php  
class WCUF_CartPage
{
	var $upload_form_is_active = false;
	var $form_was_already_rendered = false;
	public function __construct()
	{
		add_action( 'init', array( &$this, 'init' ));
		add_action( 'wp_ajax_reload_upload_fields_on_cart', array( &$this, 'ajax_reload_upload_fields' ));
		add_action( 'wp_ajax_nopriv_reload_upload_fields_on_cart', array( &$this, 'ajax_reload_upload_fields' ));
		
		//Upload form
		add_action('wp_head', array( &$this,'add_meta'));
		add_action('wp', array( &$this,'add_headers_meta'));
	}
	function init()
	{
		global $wcuf_option_model;
		$position = 'woocommerce_before_cart_table';
		try
		{
			$all_options = $wcuf_option_model->get_all_options();
			$position = $all_options['cart_page_positioning'];
		}catch(Exception $e){};
		
		add_action( $position, array( &$this, 'add_uploads_cart_page' ), 10, 1 ); //Cart page	
	}
	function ajax_reload_upload_fields()
	{
		$product_id = isset($_POST['product_id']) ? $_POST['product_id'] : 0;
		$this->add_uploads_cart_page(true, false, true );
		wp_die();
	}
	public function add_uploads_cart_page($checkout, $used_by_shortcode = false, $is_ajax_request = false)
	{
		if(wcuf_is_request_to_rest_api())
			return;
		
		if($this->form_was_already_rendered)
			return;
		
		if(!wcuf_is_a_supported_browser())
			return;
		
		global $wcuf_option_model, $wcuf_wpml_helper, $wcuf_session_model, $wcuf_cart_model, $wcuf_shortcodes,$wcuf_media_model,
		       $wcuf_product_model,$wcuf_text_model, $sitepress, $wcuf_customer_model, $wcuf_upload_field_model, $wcuf_time_model; 
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
		$current_page = 'cart';
		$current_locale = $wcuf_wpml_helper->get_current_locale();
		
	
		if($this->upload_form_is_active || (in_array($current_page,$check_if_standard_managment_is_disabled) && !$used_by_shortcode))
			return;
		else
			$this->upload_form_is_active = true;
		if(!$is_ajax_request)
		{
			wp_enqueue_script('wcuf-image-size-checker', wcuf_PLUGIN_PATH. '/js/wcuf-image-size-checker.js' ,array('jquery'));	
			wp_register_script('wcuf-ajax-upload-file', wcuf_PLUGIN_PATH. '/js/wcuf-frontend-cart-checkout-product-page'.'_'.$current_locale.'.js' ,array('jquery')); 
			wp_register_script('wcuf-multiple-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-multiple-file-manager.js', array('jquery') );
			wp_enqueue_script( 'wcuf-generic-file-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-generic-file-uploader.js', array('jquery') );
			wp_enqueue_script( 'wcuf-feedback-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-feedback-manager.js', array('jquery') );
			wp_register_script('wcuf-frontend-ui-manager', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-ui-manager.js', array('jquery'));
			
			
			wp_enqueue_script( 'wcuf-cart-page', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-cart-page.js', array('jquery') );
			
			wp_enqueue_script('wcuf-audio-video-file-manager', wcuf_PLUGIN_PATH. '/js/wcuf-audio-video-file-manager.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-load-image', wcuf_PLUGIN_PATH. '/js/load-image.all.min.js' ,array('jquery'));  
			wp_enqueue_script('wcuf-cropbox', wcuf_PLUGIN_PATH. '/js/vendor/cropbox.js' ,array('jquery'));		
			wp_register_script('wcuf-image-cropper', wcuf_PLUGIN_PATH. '/js/wcuf-frontend-cropper.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-image-cropper-multiple', wcuf_PLUGIN_PATH. '/js/wcuf-frontend-cropper-multiple.js' ,array('jquery')); 
			wp_enqueue_script('wcuf-magnific-popup', wcuf_PLUGIN_PATH.'/js/vendor/jquery.magnific-popup.js', array('jquery'));
			wp_enqueue_script('wcuf-frontend-multiple-file-uploader', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-multiple-file-uploader.js', array('jquery'));
			wp_enqueue_script('wcuf-croppie', wcuf_PLUGIN_PATH.'/js/vendor/croppie.min.js', array('jquery'));
			wp_enqueue_script('wcuf-pdf', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdf.js', array('jquery'));
			wp_enqueue_script('wcuf-pdf-2', wcuf_PLUGIN_PATH.'/js/vendor/pdf/pdfThumbnails.js', array('jquery'));
			
			wp_enqueue_style('wcuf-magnific-popup', wcuf_PLUGIN_PATH.'/css/vendor/magnific-popup.css');
			wp_enqueue_style('wcuf-frontend-common', wcuf_PLUGIN_PATH.'/css/wcuf-frontend-common.css');
			wp_enqueue_style('wcuf-croppie', wcuf_PLUGIN_PATH.'/css/vendor/croppie.css');
			wp_enqueue_style('wcuf-cropbox', wcuf_PLUGIN_PATH.'/css/vendor/cropbox.css'); 
			wp_enqueue_style('wcuf-checkout', wcuf_PLUGIN_PATH. '/css/wcuf-frontend-cart.css');  
			
			
			include WCUF_PLUGIN_ABS_PATH.'/template/alert_popup.php';
			echo '<div id="wcuf_cart_ajax_container_loading_container"></div>';
			echo '<div id="wcuf_cart_ajax_container" style="opacity:0;">';
			
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
				'icon_path' => wcuf_PLUGIN_PATH."/img/icons/",
				'current_item_cart_id' => "",
				'current_product_id' => 0,
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
			wp_localize_script( 'wcuf-multiple-file-manager', 'wcuf_options', $js_options );
			wp_localize_script( 'wcuf-ajax-upload-file', 'wcuf_options', $js_options );
			wp_localize_script( 'wcuf-image-cropper', 'wcuf_options', $js_options );
			
			wp_enqueue_script( 'wcuf-frontend-ui-manager' );
			wp_enqueue_script( 'wcuf-ajax-upload-file' );
			wp_enqueue_script( 'wcuf-multiple-file-manager' ); 
			wp_enqueue_script( 'wcuf-image-cropper' );
			wp_enqueue_script( 'wcuf-global-error-catcher', wcuf_PLUGIN_PATH.'/js/wcuf-frontend-global-error-catcher.js', array('jquery') );
		}
		else
		{
			wp_die();
		}
	}
	function add_meta()
	{
		if(function_exists('is_cart') && @is_cart())
		{
			
			 echo '<meta http-equiv="Cache-control" content="no-cache">';
			echo '<meta http-equiv="Expires" content="-1">';
		}
	}
	function add_headers_meta()
	{
		if(function_exists('is_cart') && @is_cart())
		{
			header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
			header('Pragma: no-cache');
		}
	}
}
?>