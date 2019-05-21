import {Model} from './model';

export class User extends Model {
	displayName: string;
	type: 'users';
	created: string;
}
