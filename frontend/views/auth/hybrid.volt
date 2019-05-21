{% if _config.environment === 'dev' and _config.debug === true %}
	{{ content() }}
{% endif %}
{% if registered %}
	<script>
		window.opener._hybrid.redirect('{{ token }}');
		window.close();
	</script>
{% else %}
	An error occurred, please contact us.
{% endif %}
