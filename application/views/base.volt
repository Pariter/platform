<!DOCTYPE html>
<html lang="{{ _config.language }}">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
		<meta name="viewport" content="width=device-width, initial-scale=1"/>
		<script src="{{ 'frontend.js'|resource }}"></script>
		<link rel="stylesheet" type="text/css" href="{{ 'frontend.css'|resource }}"/>
        <title>{% block title %}{% endblock %}</title>
		<meta name="description" content="{% block description %}{% endblock %}"/>
	</head>
	<body>
		{% if _config.environment === 'dev' and _config.debug === true %}
			{{ content() }}
		{% endif %}

		<div id="app">
			{% block body %}
			{% endblock %}
		</div>

		<script>_pariter.init('{{ _config.language }}');</script>
		{% block scripts %}
		{% endblock %}
	</body>
</html>
