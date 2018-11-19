{% if _config.environment === 'dev' and _config.debug === true %}
	{{ content() }}
{% endif %}
{% if registered %}
	<script>
		window.opener.location.href = '{{ 'auth'|url({'action':'profile'}) }}';
		window.close();
	</script>
{% else %}
	An error occurred, please contact us.
{% endif %}
