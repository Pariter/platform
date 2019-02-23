{% extends 'base.volt' %}

{% block title %}
	{{ user.displayName }} - Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">{{ user.displayName }}</h1>

		<div class="text-center col-xs-12 col-sm-6 col-sm-offset-3">
			<form action="{{ 'editAjax'|url({'controller':'user'}) }}" method="post" onsubmit="return _pariter.handleForm(this);">
				<input type="hidden" name="id" value="{{ user.id|escape }}"/>
				<div class="form-group">
					<label for="email">{{ 'email'|trans }}</label>
					<input type="text" class="form-control" name="email" id="email" placeholder="{{ 'email'|trans|escape }}" value="{{ user.email|escape }}"/>
				</div>
				<div class="form-group">
					<label for="displayName">{{ 'display_name'|trans }}</label>
					<input type="text" class="form-control" name="displayName" id="displayName" placeholder="{{ 'display_name'|trans|escape }}" value="{{ user.displayName|escape }}"/>
				</div>
				<div class="alert form-messages"></div>
				<button type="submit" class="btn btn-default">{{ 'update'|trans }}</button>
			</form>
		</div>
	</div>
{% endblock %}