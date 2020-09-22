<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

Route::group([
	'prefix' => 'v1',
	'middleware' => [
		'authorization',
		'response',
	],
], function () {
	Route::get('{route:.*}', 'ApiController@index');
	Route::post('{route:.*}', 'ApiController@index');
	Route::put('{route:.*}', 'ApiController@index');
	Route::options('{route:.*}', 'ApiController@index');
});