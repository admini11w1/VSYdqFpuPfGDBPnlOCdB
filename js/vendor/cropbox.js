/**
 * Created by ezgoing on 14/9/2014.
 */
'use strict';
var cropbox = function(options){
    var el = document.querySelector(options.imageBox),
    obj =
    {
        state : {},
        ratio : 1,
        options : options,
        imageBox : el,
        thumbBox : el.querySelector(options.thumbBox),
        spinner : el.querySelector(options.spinner),
        image : new Image(),
		rotation : 0,
        getDataURL: function ()
        {
		
             var position_offset_width = options.controller_real_width/el.clientWidth,
				position_offset_height = options.controller_real_height/el.clientHeight,
				width = options.cropped_real_image_width,//this.thumbBox.clientWidth, //edit this for clipped image width
                height = options.cropped_real_image_height,//this.thumbBox.clientHeight, //edit this for clipped image height
                canvas = document.createElement("canvas"),
                dim = el.style.backgroundPosition.split(' '),
                size = el.style.backgroundSize.split(' '),
                dx = parseInt(dim[0])*position_offset_width - options.controller_real_width/2 + width/2,//parseInt(dim[0]) - el.clientWidth/2 + width/2,
                dy = parseInt(dim[1])*position_offset_height - options.controller_real_height/2 + height/2, //parseInt(dim[1]) - el.clientHeight/2 + height/2,
                dw = /* width, */parseFloat(size[0])*(position_offset_width),//parseInt(size[0]), //clip area width
                dh = /* height, */parseFloat(size[1])*(position_offset_height),//parseInt(size[1]), //clip area height
                sh = parseInt(this.image.height),
                sw = parseInt(this.image.width), 
				elem_to_render = this.image;
				/* var elem_to_render = this.rotateAndCache(this.image, this.rotation);
				var sh = parseInt(elem_to_render.height),
					sw = parseInt(elem_to_render.width); */
				
			/* console.log(position_offset_width);	
			console.log(position_offset_height);		
			console.log(parseInt(size[0])*position_offset_width);	
			console.log(parseInt(size[1])*position_offset_height);	
			console.log(dx);	
			console.log(dy);	
			console.log(dw);	
			console.log(dh);	 */
            canvas.width = width;
            canvas.height = height;
            var context = canvas.getContext("2d");
			var angle = this.rotation * Math.PI / 180.0;
			var temp_dx = dx, temp_dy = dy, temp_dw = dw, temp_dh = dh;
			/* switch(obj.rotation)
			{
				//case 0: temp_dx = dx; temp_dy = dy; temp_dw = dw; temp_dh = dh; break;
				case -270:
				case 90: temp_dx = Math.cos(angle) * (dx-(sw/2)) - Math.sin(angle) * (dy-(sh/2)) + (sw/2);
						 temp_dy =  Math.sin(angle) * (dx-(sw/2)) + Math.cos(angle) * (dy-(sh/2)) + (sh/2);
						  temp_dw = dh; temp_dh = dw;  break;
				case -180:
				case 180:  temp_dx = -dx; temp_dy = -dy; break;
				case -90:
				case 270: temp_dx = -dy; temp_dy = dx; temp_dw = dh; temp_dh = dw; break;
			} */
			
			//White background
			//context.fillStyle = "white"; //"white";
			//context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(elem_to_render/* this.image */, 0, 0, sw, sh, temp_dx, temp_dy, temp_dw, temp_dh);
			
			var mime_type = wcuf_dataURItoBlob(this.image.src).type;
			//var imageData = canvas.toDataURL('image/jpeg', 0.85);
			var imageData = mime_type == 'image/png' ? canvas.toDataURL() : canvas.toDataURL(mime_type, 0.85);
            return imageData;
        },
		rotateAndCache: function(image,angle) {
		  var offscreenCanvas = document.createElement('canvas');
		  var offscreenCtx = offscreenCanvas.getContext('2d');

		  //var size = Math.max(image.width, image.height);
		  offscreenCanvas.width = image.width/* size */;
		  offscreenCanvas.height = image.height/* size */;

		  //offscreenCtx.translate(size/2, size/2);
		  offscreenCtx.translate(image.width/2, image.height/2);
		  //offscreenCtx.rotate(angle + Math.PI/2);
		  offscreenCtx.rotate((angle * Math.PI)/180);
		  offscreenCtx.drawImage(image, -(image.width/2), -(image.height/2));

		  return offscreenCanvas;
		},
		getImageDataURL: function()
		{
			var canvas = document.createElement('canvas');
			var context = canvas.getContext('2d');
			canvas.width = this.image.width;
		    canvas.height = this.image.height;
			context.drawImage(this.image , 0, 0, this.image.width, this.image.height);
			var mime_type = wcuf_dataURItoBlob(this.image.src).type;
			//return canvas.toDataURL('image/jpeg', 1);
			return  mime_type == 'image/png' ? canvas.toDataURL() : canvas.toDataURL(mime_type, 1);
		},
        getBlob: function()
        {
            var imageData = this.getDataURL();
			var mime_type = wcuf_dataURItoBlob(this.image.src).type;
            var b64 = imageData.replace('data:'+mime_type+';base64,','');
            var binary = atob(b64);
            var array = [];
            for (var i = 0; i < binary.length; i++) {
                array.push(binary.charCodeAt(i));
            }
            //return  new Blob([new Uint8Array(array)], {type: 'image/jpeg'});
            return  new Blob([new Uint8Array(array)], {type: mime_type});
        },
        zoomIn: function ()
        {
            this.ratio*=1.1;
            setBackground();
        },
        zoomOut: function ()
        {
            this.ratio*=0.9;
            setBackground();
        },
		rotateLeft: function ()
        {
           /* var c = document.createElement("canvas");
			c.width = this.image.width;
			c.height = this.image.height;    
			var ctx = c.getContext("2d");    
			ctx.rotate(90);
			var imgData = ctx.createImageData(this.image.width, this.image.height);
			ctx.putImageData(imgData, 0,0);
			this.image = new Image(imgData); */
			this.rotation -= 90;
			this.rotation = this.rotation == -360 ? 0 : this.rotation;
			//this.rotation = this.rotation == 360 || this.rotation < 0 ? 0 : this.rotation;
			/* jQuery(el).css({'-webkit-transform' : 'rotate('+this.rotation+'deg) ',
                     '-moz-transform' : 'rotate('+this.rotation+'deg)',
                     '-ms-transform' : 'rotate('+this.rotation+'deg)',
                     'transform' : 'rotate('+this.rotation+'deg)'}); */
			
			 setBackground(); 
        },
        rotateRight: function ()
        {
           /*  var c = document.createElement("canvas");
			c.width = this.image.width;
			c.height = this.image.height;    
			var ctx = c.getContext("2d");    
			ctx.rotate(90);
			var imgData = ctx.createImageData(this.image.width, this.image.height);
			ctx.putImageData(imgData, 0,0);
			this.image = new Image(imgData); */
			this.rotation += 90;
			this.rotation = this.rotation == 360 ? 0 : this.rotation;
					
			/* jQuery(el).css({'-webkit-transform' : 'rotate('+this.rotation+'deg)',
                     '-moz-transform' : 'rotate('+this.rotation+'deg)',
                     '-ms-transform' : 'rotate('+this.rotation+'deg)',
                     'transform' : 'rotate('+this.rotation+'deg)'});  */
			 setBackground();
        }
    },
    attachEvent = function(node, event, cb)
    {
        if (node.attachEvent)
            node.attachEvent('on'+event, cb);
        else if (node.addEventListener)
            node.addEventListener(event, cb);
    },
    detachEvent = function(node, event, cb)
    {
        if(node.detachEvent) {
            node.detachEvent('on'+event, cb);
        }
        else if(node.removeEventListener) {
            node.removeEventListener(event, render);
        }
    },
    stopEvent = function (e) {
		e.preventDefault();
        if(window.event) e.cancelBubble = true;
        else e.stopImmediatePropagation();
    },
    setBackground = function()
    {
        var w =  parseInt(obj.image.width)*obj.ratio;
        var h =  parseInt(obj.image.height)*obj.ratio;
      
	
		//console.log(el.style.backgroundPosition == "");
		/* if(el.style.backgroundPosition == "") */
		{
			var pw = (el.clientWidth - w) / 2;
			var ph = (el.clientHeight - h) / 2; 
		}
		/* else
		{
			var position = el.style.backgroundPosition.split(' ');
			var size = el.style.backgroundSize.split(' ');
			var pw = parseInt(position[0]);
			var ph = parseInt(position[1]);
		} */
		
        el.setAttribute('style',
                'background-image: url(' + obj.image.src + '); ' +
                'background-size: ' + w +'px ' + h + 'px; ' +
                'background-position: ' + pw + 'px ' + ph + 'px; ' +
                'background-repeat: no-repeat; '  +
				'-webkit-transform: rotate('+obj.rotation+'deg);' +
				 '-moz-transform: rotate('+obj.rotation+'deg);' +
				 '-ms-transform: rotate('+obj.rotation+'deg);' +
				 'transform: rotate('+obj.rotation+'deg); '  );
		//console.log(obj.rotation);
		//console.log(el.style);
    },
    imgMouseDown = function(e)
    {
        stopEvent(e);

        obj.state.dragable = true;
        obj.state.mouseX = e.clientX;
        obj.state.mouseY = e.clientY;
    },
	imgTouchDown = function(e)
    {
       stopEvent(e);

        obj.state.dragable = true;
        obj.state.mouseX = e.touches[0].clientX;
        obj.state.mouseY = e.touches[0].clientY;
    },
    imgMouseMove = function(e)
    {
        stopEvent(e);

        if (obj.state.dragable)
        {
			var a = (obj.rotation * Math.PI) / 180;
			var cos = Math.cos(a);
			var sin = Math.sin(a); 
			
            var x = e.clientX - obj.state.mouseX;
            var y = e.clientY - obj.state.mouseY;

            var bg = el.style.backgroundPosition.split(' ');
			
         /*  var bgX = x + parseInt(bg[0]);
             var bgY = y + parseInt(bg[1]); */
			
			/* var bgX = ((x - parseInt(bg[0])) * cos) + ((y - parseInt(bg[1])) * sin) + parseInt(bg[0]);
			var bgY = ((y - parseInt(bg[1])) * cos) - ((x - parseInt(bg[0])) * sin) + parseInt(bg[1]); */
			/* 
			bgX = ((bgX * cos) - (bgY * sin));
			bgY = ((bgX * sin) + (bgY * cos)); */
			
			//console.log(el.style);
			var temp_x = x;
			var  temp_y= y;
			
			switch(obj.rotation)
			{
				case 0: temp_x= x; temp_y= y; break;
				case -270:
				case 90: temp_x = y; temp_y = -x; break;
				case -180:
				case 180: temp_x = -x; temp_y= -y; break;
				case -90:
				case 270: temp_x = -y; temp_y= x; break;
			}
			
			  var bgX = temp_x + parseInt(bg[0]);
             var bgY = temp_y + parseInt(bg[1]);
			
            el.style.backgroundPosition = bgX +'px ' + bgY + 'px';
			//el.style.transform = obj.rotation+'deg';
         	 
            obj.state.mouseX = e.clientX;
            obj.state.mouseY = e.clientY;
        }
    },
	 imgTouchMove = function(e)
    {
        stopEvent(e);

        if (obj.state.dragable)
        {
            var x = e.touches[0].clientX - obj.state.mouseX;
            var y = e.touches[0].clientY - obj.state.mouseY;

            var bg = el.style.backgroundPosition.split(' ');

            /* var bgX = x + parseInt(bg[0]);
            var bgY = y + parseInt(bg[1]); */
			var temp_x = x;
			var  temp_y= y;
			
			switch(obj.rotation)
			{
				case 0: temp_x= x; temp_y= y; break;
				case -270:
				case 90: temp_x = y; temp_y = -x; break;
				case -180:
				case 180: temp_x = -x; temp_y= -y; break;
				case -90:
				case 270: temp_x = -y; temp_y= x; break;
			}
			
			  var bgX = temp_x + parseInt(bg[0]);
             var bgY = temp_y + parseInt(bg[1]);
			 
            el.style.backgroundPosition = bgX +'px ' + bgY + 'px';

            obj.state.mouseX = e.touches[0].clientX;
            obj.state.mouseY = e.touches[0].clientY;
        }
    },
    imgMouseUp = function(e)
    {
        stopEvent(e);
        obj.state.dragable = false;
    },
    zoomImage = function(e)
    {
        var evt=window.event || e;
        var delta=evt.detail? evt.detail*(-120) : evt.wheelDelta;
        delta > -120 ? obj.ratio*=1.1 : obj.ratio*=0.9;
        setBackground();
    },
	setInitialZoomRatio = function()
	{
		obj.ratio = options.pixel_ratio;
		//console.log(ratio);
		/* if(cropped_image_height > cropped_image_width)
		{
			ratio = cropped_image_width/cropped_image_height;
			cropped_image_height = Math.round(controller_height*2/3);
			cropped_image_width = Math.round(cropped_image_height*ratio);
		} 
		else
		{
			ratio = cropped_image_heightcropped_image_width;
			cropped_image_width =  Math.round(controller_width*2/3);
			cropped_image_height =  Math.round(controller_height*ratio);
		}  */
	};
	
    obj.spinner.style.display = 'block';
    obj.image.onload = function() {
        obj.spinner.style.display = 'none';
        attachEvent(el, 'mousedown', imgMouseDown);
        attachEvent(el, 'touchstart', imgTouchDown);
        attachEvent(el, 'mousemove', imgMouseMove);
        attachEvent(el, 'touchmove', imgTouchMove);
		
        attachEvent(document.body, 'mouseup', imgMouseUp);
        attachEvent(document.body, 'mouseover', imgMouseUp);
        attachEvent(document.body, 'mouseout', imgMouseUp);
       
		attachEvent(el, 'mouseover', imgMouseUp);
        attachEvent(el, 'mouseout', imgMouseUp);
        attachEvent(el, 'mouseup', imgMouseUp);
		attachEvent(el, 'touchend', imgMouseUp);
		
		setInitialZoomRatio();
        setBackground();
       /*  var mousewheel = (/Firefox/i.test(navigator.userAgent))? 'DOMMouseScroll' : 'mousewheel';
        attachEvent(el, mousewheel, zoomImage); */
    };
    obj.image.src = options.imgSrc;
    attachEvent(el, 'DOMNodeRemoved', function(){detachEvent(document.body, 'DOMNodeRemoved', imgMouseUp)});

    return obj;
};
