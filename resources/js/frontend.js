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
	}
};
