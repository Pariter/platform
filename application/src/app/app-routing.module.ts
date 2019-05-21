import {NgModule} from '@angular/core';
import {PreloadAllModules, RouterModule, Routes} from '@angular/router';

import {AuthGuard} from './services/auth.guard';

const routes: Routes = [
	{
		path: '',
		redirectTo: 'home',
		pathMatch: 'full'
	},
	{
		path: 'auth',
		loadChildren: './auth/auth.module#AuthPageModule'
	},
	{
		path: 'home',
		loadChildren: './home/home.module#HomePageModule',
		canActivate: [AuthGuard]
	},
	{
		path: ':type/view/:id',
		loadChildren: './view/view.module#ViewPageModule',
		canActivate: [AuthGuard]
	},
	{
		path: ':type/edit/:id',
		loadChildren: './edit/edit.module#EditPageModule',
		canActivate: [AuthGuard]
	}
];

@NgModule({
	imports: [
		RouterModule.forRoot(routes, {preloadingStrategy: PreloadAllModules})
	],
	exports: [RouterModule]
})
export class AppRoutingModule {}
