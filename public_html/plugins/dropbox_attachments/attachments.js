if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {
  	if(!Dropbox.isBrowserSupported()){
  		console.log('Browser not supported for Drop-ins');
  		//return;
  	}
  	
  	Dropbox.appKey = rcmail.env.dropbox_appKey;
  	var button = Dropbox.createChooseButton({
	    success: function(files) {
    		var ts = new Date().getTime(),
    			content = '<span>' + rcmail.get_label('uploading' + (files.length > 1 ? 'many' : '')) + '</span>';       
				rcmail.add2attachment_list(ts,{
					html : content,
					classname:"uploading",
					complete:false
				});

				var data = {
					files: files,
					_uploadid: ts,
					_id: rcmail.env.compose_id
				};
				
				var lock = rcmail.set_busy(true, 'uploading');										    			
				rcmail.http_post('plugin.dropbox_attachments', data, lock);	        
	    },
	    cancel: function() {

	    },
	    linkType: "direct", 
	    multiselect: rcmail.env.dropbox_multiselect,
	    extensions:  rcmail.env.dropbox_extensions,
  	});

  	if(button){
  		var buttonDiv = $('<div style="text-align:center; margin-bottom:20px"></div>');
  		$(rcmail.gui_objects.filedrop).prepend($(buttonDiv).append(button));	
  	}
  	
  })
}