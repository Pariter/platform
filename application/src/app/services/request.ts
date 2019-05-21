import {Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

import {Parameters} from './parameters';

@Injectable()
export class Request {
	constructor(private http: HttpClient, private parameters: Parameters) {
	}

	getJson(request: {url: string, parameters: any}, onSuccess: (object: any) => any, onError: (object: any) => any) {
		if (this.parameters.browser) {
			this.parameters.log('Request: ' + request.url);
			this.http.get(request.url, {params: request.parameters})
				.subscribe(
					(data) => {
						this.parameters.log('Request: OK');
						onSuccess(data);
					},
					(error) => {
						this.parameters.log('Request: error ' + (error.message || ''));
						onError(error);
					});
		}
		//		else {
		//			HTTP.get(request.url, request.parameters, {}).then(
		//				response => {
		//					var result = JSON.parse(response.data);
		//					if (result.geolocation) {
		//						this.parameters.updateFromServer(result.geolocation);
		//					}
		//					onSuccess(result);
		//				}).catch(error => {
		//			this.parameters.log('Request: error ' + (error.error || ''));
		//					onError(error.error);
		//				});
		//		}
	}

}