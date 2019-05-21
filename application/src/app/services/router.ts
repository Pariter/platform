import {Injectable} from '@angular/core';

@Injectable()
export class Router {

	constructor() {
	}

	get(route: string, identifier?: number): string {
		if (route.indexOf('::') === -1) {
			if (route === 'home') {
				return '/home';
			}
			return '';
		}
		var path = route.split('::');
		return '/' + path[0] + '/' + path[1] + (identifier ? '/' + identifier : '');
	}

}