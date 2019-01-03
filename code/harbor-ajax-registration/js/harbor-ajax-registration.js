
jQuery(document).ready(function () {

	jQuery(document).on('submit', '.ajax_ofie', function(event) {
		event.preventDefault();
		if (!jQuery(this).valid()) {
			return false;
		}

		jQuery('p.status', this).show().text(ajax_ofie_object.loadingmessage);

		action = 'process_ajax_ofie';

		var user_id = jQuery('.user_id', this).val();
		var user_email = jQuery('.user_email', this).val();
		var first_name = jQuery('.first_name', this).val();
		var last_name = jQuery('.last_name', this).val();
		var hbsc = jQuery('.hbsc', this).val();
		var prd_url = jQuery('.prd_url', this).val();

		ctrl = jQuery(this);
		jQuery.ajax({
			type: 'POST',
			dataType: 'json',
			url: ajax_ofie_object.ajaxurl,
			data: {
				'action': action,
				'user_id': user_id,
				'user_email': user_email,
				'first_name': first_name,
				'last_name': last_name,
				'hbsc': hbsc,
				'prd_url': prd_url
			},
			success: function (r) {
				jQuery('p.status', ctrl).html(r.message);
				if ((r.finished == true)&&(r.prd_url)) {
					window.location.href = r.prd_url;
				}
			},
			error: function (r) {
				jQuery('p.status', ctrl).text('Please review the form and resubmit.');
			}
		});
	});
});
