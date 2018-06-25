rcmail.addEventListener('init', function(evt) {
		$( "#_bcc, #_cc, #_replyto, #_followupto, #_to").droppable({
			drop: function( event, ui ) {
				console.log($(this).val());
				if($(this).val() == '') 
				{
					$(this).val(ui.draggable.find('span').attr('title'));
				}
				else 
				{
					$(this).val($(this).val() + ', ' + ui.draggable.find('span').attr('title'));
				}
			}
		});
});

rcmail.addEventListener('insertrow', function(evt) {
	$(".contact span").parent().parent().draggable({revert: true});
});
