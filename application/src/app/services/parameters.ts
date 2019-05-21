import {Injectable} from '@angular/core';
import {DomSanitizer, SafeResourceUrl} from '@angular/platform-browser';
import {Router} from '@angular/router';

declare var cordovaBrowser;

@Injectable()
export class Parameters {
	dev: boolean = false;
	debug: boolean = false;
	browser: boolean = true;
	logEntries: Array<string> = [];
	language: string = 'en';
	endpoint: string = 'https://platform.pariter.io/';
	auth: SafeResourceUrl = '';
	redirect: string = '';
	token: string = '';

	constructor(private router: Router, private sanitizer: DomSanitizer) {
		this.log('Parameters: init');

		// For now, force browser mode
		if (true || (typeof cordovaBrowser) !== 'undefined' && cordovaBrowser) {
			this.browser = true;
		}

		var location = window && window.location || {hostname: '', pathname: '', hash: ''},
			host = location.hostname || '',
			hash = location.hash.replace('#', '') || '';
		if (host.indexOf('192.168.0.') !== -1 || host.indexOf('dev.') === 0 || host.indexOf('localhost') === 0) {
			this.dev = true;
			this.endpoint = this.endpoint.replace('//', '//dev.');
		}
		if (hash.indexOf('debug-me') !== -1) {
			this.debug = true;
		}
		this.auth = this.sanitizer.bypassSecurityTrustResourceUrl(this.endpoint + this.language + '/?from=' + (this.dev ? (host.indexOf('localhost') === 0 ? 'l' : 'd') : 'p'));

		window.addEventListener('message', (event: any) => {
			if (event.data && event.data.action && event.data.action === 'auth' && event.data.token) {
				this.token = event.data.token;
				this.router.navigateByUrl(this.redirect);
			}
		}, false);

		this.log('Parameters: initiated');
	}

	log(text: string) {
		if (this.debug) {
			this.logEntries.push(text);
		}
		else if (this.dev) {
			console.log(text);
		}
	}

}