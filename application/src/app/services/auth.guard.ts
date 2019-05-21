import {Injectable} from '@angular/core';
import {Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot} from '@angular/router';

import {Parameters} from './parameters';

@Injectable({
	providedIn: 'root'
})
export class AuthGuard implements CanActivate {

	constructor(private router: Router, private parameters: Parameters) {

	}

	canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): boolean {

		if (!this.parameters.token) {
			this.parameters.redirect = state.url === '/auth' ? '/home' : state.url;
			this.router.navigate(['auth']);
			return false;
		}

		return true;
	}
}
