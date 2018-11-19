{% extends 'base.volt' %}

{% block title %}
	Pariter
{% endblock %}

{% block body %}
	<div class="container">
		<h1 class="text-center">Pariter platform</h1>

		<div class="text-center col-xs-12 col-sm-6 col-sm-offset-3">
			Please update your profile settings:
			<form action="{{ 'auth'|url({'action':'profile'}) }}" method="post">
				<div class="form-group">
					<label for="email">Email address</label>
					<input type="text" class="form-control" name="email" id="email" placeholder="Email" value="{{ email }}"/>
				</div>
				<div class="form-group">
					<label for="displayName">Display name</label>
					<input type="text" class="form-control" name="displayName" id="displayName" placeholder="Display Name" value="{{ displayName }}"/>
				</div>
				<button type="submit" class="btn btn-default">Submit</button>
			</form>
		</div>
	</div>
{% endblock %}