{% extends 'base.volt' %}

{% block title %}
	{{ 'users'|trans }} - Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">{{ 'users'|trans }}</h1>

		<div class="text-center">
			{% for user in users %}
				<a href="{{ 'view'|url(['controller':'user', 'id':user.id]) }}">{{ user.displayName }}</a>
				({{ 'registered'|trans }}{{ 'space_before_column'|trans }}: {{ user.created }})<br/>
			{% endfor %}
		</div>
	</div>
{% endblock %}