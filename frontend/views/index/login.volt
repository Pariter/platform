{% extends 'base.volt' %}

{% block title %}
	Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center with-logo"><div>Pariter</div></h1>

		<div id="signin" class="text-center col-xs-12 col-sm-6 col-sm-offset-3">
			<h2>{{ 'signin'|trans }}</h2>
			{% if from !== 'application' %}{{ 'register_for_updates'|trans }}{{ 'space_before_column'|trans }}:<br/><br/>{% endif %}
			{% for provider in providers %}
				<div class="col-xs-12 col-sm-6">
					<button class="btn btn-default btn-block btn-auth btn-{{ provider|lower }}" onclick="return _hybrid.auth('{{ provider }}');">{{ 'register_with'|trans }} {{ provider }}</button>
				</div>
			{% endfor %}
		</div>
	</div>
{% endblock %}

{% block scripts %}
	<script>
		var _hybrid = {
			auth: function (provider) {
				window.open(_pariter.getUrl('auth', 'hybrid', 'provider=' + provider), 'hybrid', 'width=600,height=400');
				return false;
			},
			redirect: function (token) {
				if ('{{ from }}' === 'application') {
					window.parent.postMessage({action: 'auth', token: token}, '{{ origin }}');
				} else {
					window.location.href = '{{ 'auth'|url({'action':'profile'}) }}';
				}
			}
		};
	</script>
{% endblock %}