import {Component} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {Observable} from 'rxjs';

import {Router} from '../services/router';
import {ModelService} from '../services/model';
import {Model} from '../models/model';

@Component({
	selector: 'app-view',
	templateUrl: 'view.page.html',
	styleUrls: ['view.page.scss'],
})
export class ViewPage {
	type: string;
	id: number;
	model: Observable<Model>;

	constructor(private route: ActivatedRoute, private service: ModelService, private router: Router) {}

	ngOnInit() {
		this.route.paramMap.subscribe(params => {
			this.type = params.get('type');
			this.id = parseInt(params.get('id'));
			this.service.getModel(this.type, this.id, (data) => {
				this.model = data;
			});
		})
	}

}
