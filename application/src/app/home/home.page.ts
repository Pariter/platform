import {Component} from '@angular/core';
import {Observable} from 'rxjs';

import {User} from '../models/user';
import {ModelService} from '../services/model';
import {Parameters} from '../services/parameters';
import {Router} from '../services/router';

@Component({
	selector: 'app-home',
	templateUrl: 'home.page.html',
	styleUrls: ['home.page.scss'],
})
export class HomePage {
	users: Observable<User[]>;

	constructor(public parameters: Parameters, private service: ModelService, private router: Router) {
		this.service.getModels(
			'users',
			(data) => {
				this.users = data;
			});
	}

}
