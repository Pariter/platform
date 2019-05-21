import {switchMap} from 'rxjs/operators';
import {Component, OnInit} from '@angular/core';
import {Router, ActivatedRoute, ParamMap} from '@angular/router';
import {Observable} from 'rxjs';

import {ModelService} from '../services/model';
import {Model} from '../models/model';

@Component({
	selector: 'app-edit',
	templateUrl: 'edit.page.html',
	styleUrls: ['edit.page.scss'],
})
export class EditPage {
	model: Observable<Model>;

	constructor(
		private route: ActivatedRoute,
		private router: Router,
		private service: ModelService
	) {}

	ngOnInit() {
		//		this.model = this.route.paramMap.pipe(
		//			switchMap((params: ParamMap) =>
		//				this.service.getModel(params.get('model'), params.get('id')))
		//		);
	}

}
