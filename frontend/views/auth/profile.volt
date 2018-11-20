{% extends 'base.volt' %}

{% block title %}
	Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">{{ 'pariter_platform'|trans }}</h1>

		<div class="text-center col-xs-12 col-sm-6 col-sm-offset-3">
			{{ 'update_your_settings'|trans }}{{ 'space_before_column'|trans }}:
			<form action="{{ 'auth'|url({'action':'profile'}) }}" method="post">
				<div class="form-group">
					<label for="email">{{ 'email'|trans }}</label>
					<input type="text" class="form-control" name="email" id="email" placeholder="{{ 'email'|trans|escape }}" value="{{ email|escape }}"/>
				</div>
				<div class="form-group">
					<label for="displayName">{{ 'display_name'|trans }}</label>
					<input type="text" class="form-control" name="displayName" id="displayName" placeholder="{{ 'display_name'|trans|escape }}" value="{{ displayName|escape }}"/>
				</div>
				<button type="submit" class="btn btn-default">{{ 'submit'|trans }}</button>
			</form>
		</div>
	</div>
{% endblock %}