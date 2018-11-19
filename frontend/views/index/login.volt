{% extends 'base.volt' %}

{% block title %}
	Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">Pariter platform</h1>

		<div class="text-center">
			Register for future updates on the project:<br/><br/>
			{% for provider in providers %}
				<button class="btn btn-default" onclick="return _hybrid.auth('{{ provider }}');">Register with {{ provider }}</button><br/><br/>
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