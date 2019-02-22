{% extends 'base.volt' %}

{% block title %}
	{{ user.displayName }} - Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">{{ user.displayName }}</h1>

		<div class="text-center">
			{{ 'registered'|trans }}{{ 'space_before_column'|trans }}: {{ user.created }}
		</div>
	</div>
{% endblock %}