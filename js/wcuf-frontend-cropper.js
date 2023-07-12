"use strict";
jQuery(document).on('click', '.wcuf_crop_button', wcuf_prevent_default);

function wcuf_image_crop(evt, myId, callback)
{
	this.evt = evt;
	this.id = myId;
	this.crop_button_id = "";
	this.callback = callback;
	this.reader = new FileReader();
	this.image_loaded_result = null;
	var mySelf = this;
	this.image_url;
	this.zoom = 0;
	this.rotation = 0;
	this.reader.onload = function(e)
	{
		//New 
		mySelf.image_loaded_result = wcuf_dataURItoBlob(e.target.result);
		if(mySelf.image_loaded_result.type == "image/jpeg" || mySelf.image_loaded_result.type == "image/png")
		{
			wcuf_croppie_init(e);			
		}
		
	}
	//UI
	if(!jQuery('#wcuf_crop_container_'+myId).hasClass("wcuf_not_to_be_showed"))
		jQuery('#wcuf_crop_container_'+myId).addClass("wcuf_not_to_be_showed");
	jQuery('#wcuf_crop_container_'+myId).hide();
	
	jQuery('#wcuf_crop_image_box_'+myId).croppie('destroy');
	this.reader.readAsDataURL(evt.target.files[0]);
	
	if(typeof evt.file_unique_id !== "undefined")
		this.crop_button_id = evt.file_unique_id;
	
	//New 
	function wcuf_croppie_init(e)
	{
		var sizes = wcuf_croppie_get_initial_size_values();
		
		mySelf.cropper = jQuery('#wcuf_crop_image_box_'+mySelf.id).croppie({
					viewport: {
						width: sizes.width,
						height: sizes.height,
						type: jQuery('#wcuf_upload_field_'+mySelf.id).data('crop-area-shape')
					},
					enableExif: true,
					enableOrientation: true, 
					enableZoom: !wcuf_crop_disable_zoom_controller,
					mouseWheelZoom: !wcuf_crop_disable_zoom_controller
			});
	
		mySelf.image_url = e.target.result;
		mySelf.cropper.croppie('bind', {
						url: e.target.result,
						zoom: 0
					});
						
		//Clear previous event listeners
		wcuf_clear_all_event_listener('btnCrop_'+mySelf.id);
		wcuf_clear_all_event_listener('btnRotateLeft_'+mySelf.id);
		wcuf_clear_all_event_listener('btnRotateRight_'+mySelf.id); 
		
		
		jQuery('#wcuf_crop_container_'+mySelf.id).fadeIn();
		jQuery('#wcuf_crop_container_'+mySelf.id).removeClass('wcuf_not_to_be_showed');
		
		jQuery('#btnRotateLeft_'+mySelf.id).on('click', wcuf_croppie_rotate_left);
		jQuery('#btnRotateRight_'+mySelf.id).on('click', wcuf_croppie_rotate_right);
		jQuery('#btnCrop_'+mySelf.id).on('click', wcuf_croppie_crop_and_upload);
		jQuery('#btnCancelCrop_'+mySelf.id).on('click', mySelf.wcuf_croppie_destroy_and_hide);
	}
	this.wcuf_croppie_destroy = function()
	{
		wcuf_clear_all_event_listener('btnCrop_'+mySelf.id);
		wcuf_clear_all_event_listener('btnRotateLeft_'+mySelf.id);
		wcuf_clear_all_event_listener('btnRotateRight_'+mySelf.id); 
		wcuf_clear_all_event_listener('btnCancelCrop_'+mySelf.id); 
		
	}
	this.wcuf_croppie_destroy_and_hide = function()
	{
		mySelf.wcuf_croppie_destroy();
		
		jQuery('#wcuf_crop_container_'+mySelf.id).fadeOut();
		jQuery('#wcuf_crop_container_'+mySelf.id).addClass('wcuf_not_to_be_showed');
	}
	function wcuf_croppie_rotate_left(event)
	{
		var myId = jQuery(event.currentTarget).data('id');
		
		
		//new method
		mySelf.rotation += 90;
		if(wcuf_options.crop_method != 'server_side')
			wcuf_reload_image(mySelf.image_url);//mySelf.cropper.croppie('rotate',mySelf.rotation);
		else
			wcuf_crop_rotate_upload_image_to_rotate(mySelf.rotation, myId);
		
	}
	function wcuf_croppie_rotate_right(event)
	{
		var myId = jQuery(event.currentTarget).data('id');
		
		
		//new method
		mySelf.rotation -= 90;	
		if(wcuf_options.crop_method != 'server_side')
			wcuf_reload_image(mySelf.image_url); //mySelf.cropper.croppie('rotate',mySelf.rotation);
		else
			wcuf_crop_rotate_upload_image_to_rotate(mySelf.rotation, myId);
		
	}
	function wcuf_crop_rotate_upload_image_to_rotate(degrees, id)
	{
		var xhr = new XMLHttpRequest();
		if(!xhr.upload)
			return;
		
		var blob = mySelf.image_loaded_result;
		blob.name =  mySelf.evt.target.files[0].name;
		
		//UI
		wcuf_hide_actions_ui(id);
		wcuf_reset_crop_upload_image_for_rotating_loading_ui(id);
		xhr.onreadystatechange = function(event) 
		{
			if (xhr.readyState == 4) 
			{
				//1.
				jQuery('#wcuf_status_'+id).html(xhr.status == 200 ? wcuf_success_msg : wcuf_failure_msg);
				if(xhr.status == 200)
				{
					//2
					wcuf_hide_rotating_status_ui(id);
					wcuf_show_actions_ui(id);
					
					//Reload image
					event.target.result = event.target.response;
					wcuf_reload_image(event.target.response);
				}
			}
		};
		
		
		var a = function(event)
		{
				jQuery('#wcuf_crop_rotating_upload_bar_'+id).css('width', event.pc+"%");
				jQuery('#wcuf_crop_rotating_upload_percent_'+id).html(event.pc + "%");
		};
		var b = function(event)
		{
					var formData = new FormData();
					formData.append('action', 'wcuf_rotate_image'); 
					formData.append('degrees', degrees);
					formData.append('session_id', event.session_id);
					formData.append('file_name', event.file_name);
					xhr.open("POST", wcuf_ajaxurl, true); //3rd parameter: async ->true/false
					xhr.send(formData); 
		};
		var multiple_file_uploader = new Van_MultipleFileUploader({files: [mySelf.evt.target.files[0]], on_progess_callback: a, on_single_file_upload_complete_callback:b});
		multiple_file_uploader.continueUploading();
	}
	function wcuf_reload_image(image)
	{
		let orientation = 1;
		const croppie_data = mySelf.cropper.croppie('get');
		mySelf.rotation = Math.abs(mySelf.rotation) == 360 ? 0 : mySelf.rotation;
	
		if(wcuf_options.crop_method != 'server_side')
			switch(mySelf.rotation)
			{
				case -270: 
				case 90: orientation = 8; break;
				case 270:			
				case -90: orientation = 6; break;
				case -180:
				case 180: orientation = 3; break;
			}			
		
		mySelf.cropper.croppie('bind', { 
						url: image,
						zoom: croppie_data.zoom,
						orientation: orientation
					});
	}
	
	function wcuf_croppie_crop_and_upload(event)
	{
		var evt = mySelf.evt; 
		var blob = jQuery('#wcuf_crop_image_box_'+mySelf.id).croppie('result', {
					type: 'blob', //https://foliotek.github.io/Croppie/
					
					 size: {
							width: jQuery("#wcuf_upload_field_"+mySelf.id).data('cropped-width'),
							height: jQuery("#wcuf_upload_field_"+mySelf.id).data('cropped-height')
							}, 
					format : mySelf.image_loaded_result.type.replace("image/", "")
				}).then(function (resp) 
					{
						resp.name = evt.target.files[0].name;
						evt.blob = resp;
						
						callback(evt);
					});
					
		const crop_button_id = mySelf.crop_button_id !== "" ? mySelf.crop_button_id : mySelf.id;
		jQuery('#wcuf_single_crop_button_'+crop_button_id).hide();
		return false;
	}
	function wcuf_croppie_get_initial_size_values()
	{
		var controller_width = jQuery("#wcuf_crop_container_"+mySelf.id).width();
		var controller_height = jQuery("#wcuf_crop_container_"+mySelf.id).height();
		var cropped_image_width = jQuery("#wcuf_upload_field_"+mySelf.id).data('cropped-width');
		var cropped_image_height = jQuery("#wcuf_upload_field_"+mySelf.id).data('cropped-height');
		var ratio = 1;
		var controller_real_width = 0;
		var controller_real_height = 0;
		var container_ratio = 1.5 //the container has to be greater that the real cropper area 
		
		if(cropped_image_height > cropped_image_width)
		{
			ratio = cropped_image_width/cropped_image_height;		
			controller_real_height = cropped_image_height * container_ratio;
			cropped_image_height = Math.round((cropped_image_height/controller_real_height)*controller_height) + 2;
			cropped_image_width = Math.round(cropped_image_height*ratio) + 2 ; //2: border thick
			ratio = controller_height/controller_real_height; 
			controller_real_width =  jQuery('#wcuf_crop_image_box_'+mySelf.id).width() / ratio ;
			
		} 
		else if(cropped_image_height < cropped_image_width)
		{
			ratio = cropped_image_height/cropped_image_width;
			controller_real_width = cropped_image_width * container_ratio;
			//2: border thick
			cropped_image_width =  Math.round((cropped_image_width/controller_real_width)*controller_width) + 2;
			cropped_image_height =  Math.round(cropped_image_width*ratio) + 2;
			ratio = controller_width/controller_real_width;
			controller_real_height = jQuery('#wcuf_crop_image_box_'+mySelf.id).height() / ratio;
		} 
		else
		{
			if(controller_height < controller_width)
			{
				controller_real_height = cropped_image_height * container_ratio;
				controller_real_width = controller_real_height * (controller_width/controller_height);
				ratio = controller_height/controller_real_height;
			}
			else
			{
				controller_real_width = cropped_image_width * container_ratio;
				controller_real_height = controller_real_width * (controller_height/controller_width); 
				ratio = controller_width/controller_real_width;
			}
			
			//2: border thick
			cropped_image_width =  Math.round((cropped_image_width/controller_real_width)*controller_width) + 2;
			cropped_image_height =  Math.round((cropped_image_height/controller_real_height)*controller_height) + 2;
			ratio = controller_height/controller_real_height;	
		}
		
		return {width: cropped_image_width, height: cropped_image_height, zoom: ratio};
	}
	
}
function wcuf_clear_all_event_listener(element_id)
{
	var old_element = document.getElementById(element_id);
	if( old_element === null)
		return;
	var new_element = old_element.cloneNode(true);
	old_element.parentNode.replaceChild(new_element, old_element);
}
function wcuf_reset_crop_upload_image_for_rotating_loading_ui(id)
{
	wcuf_set_bar_background();
	jQuery('#wcuf_crop_upload_image_for_rotating_status_box_'+id).fadeIn();	
	jQuery('#wcuf_crop_rotating_upload_bar_'+id).css('width', "0%");
}
function wcuf_hide_rotating_status_ui(id)
{
	jQuery('#wcuf_crop_upload_image_for_rotating_status_box_'+id).fadeOut();	
	jQuery('#wcuf_crop_rotating_upload_bar_'+id).css('width', "0%");
}
function wcuf_hide_actions_ui(id)
{
	wcuf_hide_control_buttons();
	jQuery('#wcuf_crop_container_actions_'+id).fadeOut();	
	/*To add scroll effect after rotating: add here an "animate" on body,html and then a scrollToTop */ 
}
function wcuf_show_actions_ui(id)
{
	wcuf_show_control_buttons(id);
	jQuery('#wcuf_crop_container_'+id).fadeIn();
	jQuery('#wcuf_crop_container_'+id).removeClass('wcuf_not_to_be_showed');
	jQuery('#wcuf_crop_container_actions_'+id).fadeIn();	
}





//Old managment: not used any more

function wcuf_on_image_to_crop_loaded(e,evt,id,callback)
{
	var result = wcuf_dataURItoBlob(e.target.result);
	var cropper, ratio;
	var cropped_image_width = jQuery("#wcuf_upload_field_"+id).data('cropped-width');
	var cropped_image_height = jQuery("#wcuf_upload_field_"+id).data('cropped-height');
	var controller_width = jQuery("#wcuf_crop_image_box_"+id).width();
	var controller_height = jQuery("#wcuf_crop_image_box_"+id).height();
	var controller_real_width,controller_real_height = 0;
	var ratio = 1;
	//Clear previous event listeners
	wcuf_clear_all_event_listener('btnCrop_'+id);
	wcuf_clear_all_event_listener('btnZoomIn_'+id);
	wcuf_clear_all_event_listener('btnRotateLeft_'+id);
	wcuf_clear_all_event_listener('btnRotateRight_'+id);  
	wcuf_clear_all_event_listener('btnZoomOut_'+id);
	
	//Set size
	if(cropped_image_height > cropped_image_width)
	{
		ratio = cropped_image_width/cropped_image_height;		
		controller_real_height = cropped_image_height * 1.3;
		cropped_image_height = Math.round((cropped_image_height/controller_real_height)*controller_height) + 2;//Math.round(controller_height*2/3);
		cropped_image_width = Math.round(cropped_image_height*ratio) + 2 ; //2: border thick
		ratio = controller_height/controller_real_height; 
		controller_real_width =  jQuery('#wcuf_crop_image_box_'+id).width() / ratio ;
		
	} 
	else if(cropped_image_height < cropped_image_width)
	{
		ratio = cropped_image_height/cropped_image_width;
		controller_real_width = cropped_image_width * 1.3;
		//2: border thick
		cropped_image_width =  Math.round((cropped_image_width/controller_real_width)*controller_width) + 2;//Math.round(controller_width*2/3);
		cropped_image_height =  Math.round(cropped_image_width*ratio) + 2;
		ratio = controller_width/controller_real_width;
		controller_real_height = jQuery('#wcuf_crop_image_box_'+id).height() / ratio;
	} 
	else
	{
		if(controller_height < controller_width)
		{
			controller_real_height = cropped_image_height * 1.3;
			controller_real_width = controller_real_height * (controller_width/controller_height);
			ratio = controller_height/controller_real_height;
		}
		else
		{
			controller_real_width = cropped_image_width * 1.3;
			controller_real_height = controller_real_width * (controller_height/controller_width); 
			ratio = controller_width/controller_real_width;
		}
		
		//2: border thick
		cropped_image_width =  Math.round((cropped_image_width/controller_real_width)*controller_width) + 2;//Math.round(controller_width*2/3);
		cropped_image_height =  Math.round((cropped_image_height/controller_real_height)*controller_height) + 2;//Math.round(controller_height*2/3);
		ratio = controller_height/controller_real_height;
	}
	var options =
    {
        imageBox: '#wcuf_crop_image_box_'+id,
        thumbBox: '#wcuf_crop_thumb_box_'+id,
        spinner: '#wcuf_crop_thumb_spinner_'+id,
        cropped_image_width: cropped_image_width,
        cropped_image_height: cropped_image_height,
        controller_real_width: controller_real_width,
        controller_real_height: controller_real_height,
        cropped_real_image_width: jQuery("#wcuf_upload_field_"+id).data('cropped-width'),
        cropped_real_image_height:  jQuery("#wcuf_upload_field_"+id).data('cropped-height'),
		pixel_ratio: ratio
    }
	
	
	
	jQuery('#wcuf_crop_thumb_box_'+id).css({'width': cropped_image_width+'px',
											'height': cropped_image_height+'px',
											'margin-top': "-"+(cropped_image_height/2)+'px',
											'margin-left': "-"+(cropped_image_width/2)+'px'});
		
	if(result.type == "image/jpeg" || result.type == "image/png")
	{
		//UI
		jQuery('#wcuf_crop_container_'+id).fadeIn();
		jQuery('#wcuf_crop_container_'+id).removeClass('wcuf_not_to_be_showed');
		options.imgSrc = e.target.result;
		cropper = new cropbox(options);	
	}	
	else
	{
		jQuery('#btnCrop_'+id).on('click', '.wcuf_crop_button', wcuf_prevent_default);
		jQuery('#btnZoomIn_'+id).on('click', '.wcuf_crop_button', wcuf_prevent_default);
		jQuery('#btnRotateLeft_'+id).on('click', '.wcuf_crop_button', wcuf_prevent_default);
		jQuery('#btnRotateRight_'+id).on('click', '.wcuf_crop_button', wcuf_prevent_default);  
		jQuery('#btnZoomOut_'+id).on('click', '.wcuf_crop_button', wcuf_prevent_default);
		jQuery('#wcuf_crop_container_'+id).fadeOut();
		jQuery('#wcuf_crop_container_'+id).addClass('wcuf_not_to_be_showed');
		alert(wcuf_image_file_error);
		return false;
	}
	document.querySelector('#btnCrop_'+id).addEventListener('click', wcuf_crop_and_upload);
	document.querySelector('#btnZoomIn_'+id).addEventListener('click', wcuf_crop_zoom_in);
	document.querySelector('#btnZoomOut_'+id).addEventListener('click',wcuf_crop_zoom_out);
	document.querySelector('#btnRotateLeft_'+id).addEventListener('click',wcuf_crop_rotate_left);
	document.querySelector('#btnRotateRight_'+id).addEventListener('click',wcuf_crop_rotate_right);  

	function wcuf_crop_rotate_left(event)
	{
		event.preventDefault();
		event.stopImmediatePropagation();
		
		//cropper.rotateLeft();
		if(wcuf_options.crop_method != 'server_side')
			mySelf.cropper.croppie('rotate',mySelf.rotation);
		else
			wcuf_crop_rotate_upload_image_to_rotate('left', jQuery(event.currentTarget).data('id'));
		return false;
	}
	function wcuf_crop_rotate_right(event)
	{
		event.preventDefault();
		event.stopImmediatePropagation();
		
		//cropper.rotateRight();
		if(wcuf_options.crop_method != 'server_side')
			mySelf.cropper.croppie('rotate',mySelf.rotation);
		else
			wcuf_crop_rotate_upload_image_to_rotate('right', jQuery(event.currentTarget).data('id'));
		return false;
	}
	function wcuf_crop_zoom_in(event)
	{
		event.preventDefault();
		event.stopImmediatePropagation();
		
		cropper.zoomIn();
		return false;
	}
	function wcuf_crop_zoom_out(event)
	{
		event.preventDefault();
			event.stopImmediatePropagation();
			cropper.zoomOut();
			return false;
	}
	function wcuf_crop_and_upload(event)
	{
		event.preventDefault();
		event.stopImmediatePropagation();
		var img = cropper.getDataURL();
		//document.querySelector('.cropped').innerHTML += '<img src="'+img+'">';
		
		var blob = wcuf_dataURItoBlob(img);
		blob.name = evt.target.files[0].name;
		//evt.target.files[0] = blob;
		
		evt.blob = blob;
		callback(evt);
		return false;
	}

	function wcuf_crop_rotate_upload_image_to_rotate(direction, id)
	{
		var xhr = new XMLHttpRequest();
		if(!xhr.upload)
			return;
		
		var img = cropper.getImageDataURL();
		var blob = wcuf_dataURItoBlob(img);
		blob.name = evt.target.files[0].name;
		
		//UI
		wcuf_hide_actions_ui(id);
		wcuf_reset_crop_upload_image_for_rotating_loading_ui(id);
		
		//Setup
		xhr.upload.addEventListener("progress", function(e) 
		{
			var pc = parseInt((e.loaded / e.total * 100));
			jQuery('#wcuf_crop_rotating_upload_bar_'+id).css('width', pc+"%");
			jQuery('#wcuf_crop_rotating_upload_percent_'+id).html(pc + "%");
		}, false);
		xhr.upload.addEventListener("load",function(e)
		{
			//2
			
		},false);
		// file received/failed
		xhr.onreadystatechange = function(event) {
			if (xhr.readyState == 4) 
			{
				//1.
				jQuery('#wcuf_status_'+id).html(xhr.status == 200 ? wcuf_success_msg : wcuf_failure_msg);
				if(xhr.status == 200)
				{
					//3
					wcuf_hide_rotating_status_ui(id);
					wcuf_show_actions_ui(id);
					
					//Reload image
					event.target.result = event.target.response;
					wcuf_on_image_to_crop_loaded(event, evt,id,callback);
					
				}
			}
		};
		//Start upload
		var formData = new FormData();
		xhr.open("POST", wcuf_ajaxurl, true); //3rd parameter: async ->true/false
		formData.append('action', 'wcuf_rotate_image'); 
		formData.append('direction', direction);
		formData.append('image', blob);
		xhr.send(formData);
	}
}
function wcuf_prevent_default(event)
{
	event.preventDefault();
	event.stopImmediatePropagation();
	return false;
}
function wcuf_dataURItoBlob(dataURI) {
    // convert base64/URLEncoded data component to raw binary data held in a string
    var byteString;
    if (dataURI.split(',')[0].indexOf('base64') >= 0)
        byteString = atob(dataURI.split(',')[1]);
    else
        byteString = unescape(dataURI.split(',')[1]);

    // separate out the mime component
    var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];

    // write the bytes of the string to a typed array
    var ia = new Uint8Array(byteString.length);
    for (var i = 0; i < byteString.length; i++) {
        ia[i] = byteString.charCodeAt(i);
    }

    return new Blob([ia], {type:mimeString});
}