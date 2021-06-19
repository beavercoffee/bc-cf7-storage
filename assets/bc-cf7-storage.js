if('undefined' === typeof(bc_cf7_storage)){
    var bc_cf7_storage = {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        init: function(){
            jQuery('.wpcf7-form').on({
                wpcf7mailsent: bc_cf7_storage.wpcf7mailsent,
			});
        },

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        wpcf7mailsent: function(event){
            var message = '', unit_tag = '';
			unit_tag = event.detail.unitTag;
            if(!jQuery('#' + unit_tag).find('input[name="bc_redirect"]').length){
                if(jQuery('#' + unit_tag).find('input[name="bc_storage_message"]').length){
                    message = jQuery('#' + unit_tag).find('input[name="bc_storage_message"]').val();
                    jQuery('#' + unit_tag).find('.wpcf7-form').children().hide().end().prepend('<div class="alert alert-success bc-cf7-storage-message" role="alert">' + message + '</div>');
                }
            }
        },

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    };
}
