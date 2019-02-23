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

		<nav class="navbar navbar-default navbar-inverse">
			<div class="container-fluid">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="{{ 'login'|url }}">
						<img alt="Pariter" src="{{ 'logo-20x20.png'|resource }}"/>
					</a>
					<a class="navbar-brand" href="{{ 'login'|url }}">
						{{ 'pariter_platform'|trans }}
					</a>
				</div>
				<div class="collapse navbar-collapse" id="navbar-collapse-1">
					<ul class="nav navbar-nav">
						<li><a href="{{ 'list'|url({'controller':'user'}) }}">{{ 'users'|trans }}</a></li>
					</ul>
					<ul class="nav navbar-nav">
						<li><a href="https://pariter.io/">{{ 'main_site'|trans }}</a></li>
					</ul>
				</div>
			</div>
		</nav>
		{% block body %}
		{% endblock %}

		<script>_pariter.init('{{ _config.language }}');</script>
		{% block scripts %}
		{% endblock %}
	</body>
</html>
