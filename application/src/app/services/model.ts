import {Injectable} from '@angular/core';

import {Model} from '../models/model';

import {Request} from './request';
import {Parameters} from './parameters';

@Injectable({
	providedIn: 'root',
})
export class ModelService {
	models: {[name: string]: Model} = {};

	constructor(private parameters: Parameters, private request: Request) {}

	getModels(modelType: string, success: (object: any) => any) {
		this.request.getJson(
			{
				url: this.parameters.endpoint + this.parameters.language + '/api/' + modelType + '/',
				parameters: {token: this.parameters.token}
			},
			(data) => {
				for (let d of data.data) {
					this.models[modelType + '|' + d.id] = d;
				}
				success(data.data);
			},
			(error) => {
				this.parameters.log('Events: error (' + (error.message || '') + ')');
			});
	}

	getModel(modelType: string, modelId: number | string, callback: (object: any) => any) {
		if (this.models[modelType + '|' + modelId]) {
			callback(this.models[modelType + '|' + modelId]);
			return true;
		}
		this.getModels(modelType, data => {
			var m = data.filter(
				(entry: Model) => entry.id === +modelId
			);
			if (m[0]) {
				callback(m[0]);
			}
		});
		return true;
	}
}
