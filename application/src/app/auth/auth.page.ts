import {Component} from '@angular/core';

import {Parameters} from '../services/parameters';

@Component({
	selector: 'app-auth',
	templateUrl: 'auth.page.html',
	styleUrls: ['auth.page.scss'],
})
export class AuthPage {
	constructor(public parameters: Parameters) {

	}
}
