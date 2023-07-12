"use strict";
function wcuf_ui_delete_file()
{
	jQuery("#wcuf_file_uploads_container").fadeOut(400);
	jQuery("#wcuf_deleting_message").delay(500).fadeIn(400,function()
	{
		//Smooth scroll
		try{
			jQuery('html, body').animate({
				  scrollTop: jQuery('#wcuf_deleting_message').offset().top - 200 //#wcmca_address_form_container ?
				}, 500);
		}catch(error){}
	});
}
function wcuf_ui_delete_file_on_order_details_page()
{
	jQuery("#wcuf_file_uploads_container").fadeOut(400);
	jQuery("#wcuf_deleting_message").delay(500).fadeIn(400,function()
	{
		//Smooth scroll
		try{
			jQuery('html, body').animate({
				  scrollTop: jQuery('#wcuf_deleting_message').offset().top - 200 //#wcmca_address_form_container ?
				}, 500);
		}catch(error){}
	});
}
function wcuf_show_popup_alert(text)
{
	jQuery('#wcuf_alert_popup').css({'display':'block'});
	jQuery('#wcuf_alert_popup_content').html(text);
	jQuery('#wcuf_show_popup_button').trigger('click');
}

function wcuf_ui_after_delete()
{  
	var event = new Event('onSingleFileDeleComplete');
	document.dispatchEvent(event);
		
	if(wcuf_current_page != "cart" && wcuf_current_page != "order_details" && wcuf_current_page != "thank_you")
	{
		setTimeout(function(){wcuf_ajax_reload_upload_fields_container() }, 1500); 
		
	}
	 else
		wcuf_reload_page_with_anchor();
}
function wcuf_reload_page(time)
{
	wcuf_is_force_reloading = true;
	setTimeout(function(){ window.location.reload(true);   ;  }, time); 
}
function wcuf_reload_page_with_anchor()
{
	var url = window.location.href;
	if(!wcuf_reload_param_exists())
		url += url.indexOf('?') > -1 ? '&wcuf_pagereload=1' : '?wcuf_pagereload=1';
	window.location.href = url;
}
function wcuf_jump_to_upload_area_by_url_param()
{
	var params = wcuf_getUrlVars();
	if(params["wcuf_id"] !== undefined)
	{		
		const id = params["wcuf_id"].replace('/','');
		jQuery('html, body').animate({
			scrollTop: jQuery('#wcuf_upload_field_container_'+id).offset().top - 100
		}, 1000);
	}
}
function wcuf_getUrlVars() {
    var vars = {};
    var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = value;
    });
    return vars;
}
function wcuf_smooth_scroll_to_upload_area()
{
	if(wcuf_reload_param_exists())
	{
		jQuery('html, body').animate({
			  scrollTop: jQuery('#wcuf_file_uploads_container').offset().top - 100
			}, 1000);
	}
	else 
		wcuf_jump_to_upload_area_by_url_param();
}
function wcuf_reload_param_exists()
{
	var url_string = window.location.href;
	var url = new URL(url_string);
	var param = url.searchParams.get("wcuf_pagereload");
	
	return param != null;
}
function wcuf_hide_add_to_cart_button_in_case_of_required_upload(fadeInTime, fadeOutTime)
{
	
	if(wcuf_current_page != 'product' )
		return;
	
	if(!wcuf_exist_a_field_before_add_to_cart || (wcuf_all_required_uploads_have_been_performed(true) && wcuf_check_multiple_upload_status(null)))
	{
		wcuf_show_hide_add_to_cart_area(true, true, fadeInTime, fadeOutTime)
	}
	else
	{
		wcuf_show_hide_add_to_cart_area(false, true, fadeInTime, fadeOutTime)
	}
	
}
function wcuf_show_hide_add_to_cart_area(show, manageRequiredMessage, fadeInTime, fadeOutTime)
{
	if(show)
	{
		//hide
		if(wcuf_options.hide_add_to_cart_button == 'yes')
		{
			jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .quantity, button.add-to-cart ').css('display', 'inline-block');
			jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .quantity, button.add-to-cart').animate({opacity: 1}, 100); 
		}
		else 
		{
			//New method: the controllers are disabled:
			jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .qty, button.add-to-cart ').removeAttr("disabled");
			jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .qty, button.add-to-cart').fadeIn();
		}
		if(manageRequiredMessage)
			jQuery('.wcuf_required_upload_add_to_cart_warning_message').fadeOut(fadeOutTime); //400
		
		//hide qty selector (in case the product quantity has been setted to be equal to number of uploaded files)
		if(wcuf_options.cart_quantity_as_number_of_uploaded_files == 'true' && jQuery('.wcuf_upload_fields_row_element').length > 0)
			jQuery('div.quantity, .quantity input.qty, #qty').hide();
	}
	else 
	{
		if(wcuf_options.hide_add_to_cart_button == 'yes')
		{
			//hide
			jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .quantity, button.add-to-cart').animate({opacity: 0}, 200,
			function()
			{
				jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .quantity, button.add-to-cart').css('display', 'none');
			}); 
		}
		else 
			jQuery('.single_add_to_cart_button, div.paypal-button, form.cart .add_to_cart_button, .wc_quick_buy_button, .qty, button.add-to-cart').attr("disabled", true);
		
		if(manageRequiredMessage)
			jQuery('.wcuf_required_upload_add_to_cart_warning_message').fadeIn(fadeInTime); //600
	}
}

function wcuf_show_upload_field_area()
{
	jQuery('#wcuf_'+wcuf_current_page+'_ajax_container').css('display', 'block');
	jQuery('#wcuf_'+wcuf_current_page+'_ajax_container').animate({opacity: 1}, 200);
}