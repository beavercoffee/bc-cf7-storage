if('undefined' === typeof(bc_cf7_storage)){
    var bc_cf7_storage = {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        init: function(){
            jQuery('.wpcf7-form').on({
                wpcf7mailsent: bc_cf7_storage.wpcf7mailsent,
                wpcf7reset: bc_cf7_storage.wpcf7reset,
			});
        },

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        wpcf7mailsent: function(event){
            var unit_tag = '';
			unit_tag = event.detail.unitTag;
            if(!jQuery('#' + unit_tag).find('input[name="bc_redirect"]').length){
                if(jQuery('#' + unit_tag).find('input[name="bc_loading"]').length){
                    jQuery('#' + unit_tag).find('.bc-submit-wrap').removeClass('d-flex').addClass('d-none');
                    jQuery('#' + unit_tag).find('.wpcf7-form').children().hide();
                    jQuery('#' + unit_tag).find('.wpcf7-form').prepend('<div class="alert alert-info bc-cf7-storage-alert" role="alert">' + jQuery('#' + unit_tag).find('input[name="bc_loading"]').val() + '</div>');
                }
            }
        },

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        wpcf7reset: function(event){
            var message = '', unit_tag = '';
			unit_tag = event.detail.unitTag;
            if(!jQuery('#' + unit_tag).find('input[name="bc_redirect"]').length){
                if(jQuery('#' + unit_tag).find('input[name="bc_thank_you"]').length){
                    message = jQuery('#' + unit_tag).find('input[name="bc_thank_you"]').val();
                }
                if('' === message){
                    message = jQuery('#' + unit_tag).find('.wpcf7-response-output').text();
                }
                jQuery('#' + unit_tag).find('.bc-cf7-storage-alert').removeClass('alert-info').addClass('alert-success').text(message);
            }
        },

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    };
}
