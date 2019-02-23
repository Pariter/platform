/* include jquery.js */
/* include bootstrap.js */

var _pariter = {
	language: 'en',
	init: function (language) {
		this.language = language;
	},
	getUrl: function (controller, action, parameters) {
		var url = '/' + this.language + '/' + controller + '/' + action;
		parameters = parameters || '';
		if (parameters) {
			url += '?' + parameters;
		}
		return url;
	},
	handleForm: function (form) {
		/* For debug: uncomment this line */
		// return true;

		form = $(form);
		var messages = form.find('.form-messages').first(),
				button = form.find('button[type="submit"]').first();
		messages.removeClass('alert-okay,alert-danger').hide();
		button.addClass('btn-loader');
		$.ajax({
			url: form.attr('action'),
			method: 'post',
			data: form.serialize(),
			success: function (data) {
				button.removeClass('btn-loader');
				console.log(data);
				if (data && data.id) {
					if (data.reload) {
						document.location.href = data.reload;
						return;
					}
					if (form.attr('data-reload')) {
						document.location.reload();
						return;
					}
					messages.html(data.message).addClass('alert-success').show();
				} else {
					messages.html(data && data.message || data).addClass('alert-danger').show();
				}
			},
			error: function () {
				button.removeClass('btn-loader');
				messages.html('An error occurred').addClass('alert-danger').show();
			}
		});
		return false;
	}
};
