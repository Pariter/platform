{% extends 'base.volt' %}

{% block title %}
	Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">{{ 'pariter_platform'|trans }}</h1>

		<div class="text-center">
			{{ 'register_for_updates'|trans }}{{ 'space_before_column'|trans }}:<br/><br/>
			{% for provider in providers %}
				<button class="btn btn-default" onclick="return _hybrid.auth('{{ provider }}');">{{ 'register_with'|trans }} {{ provider }}</button><br/><br/>
			{% endfor %}
		</div>
	</div>
{% endblock %}

{% block scripts %}
	<script>
		var _hybrid = {
			auth: function (provider)
			{
				window.open(_pariter.getUrl('auth', 'hybrid', 'provider=' + provider), 'hybrid', 'width=600,height=400');
				return false;
			}
		};
	</script>
{% endblock %}