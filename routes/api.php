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

Route::group(["prefix" => (getenv("APP_PREFIX") ?? "")], function(){
	Route::get('/', function() {
		return "THIS ROUTE DOES NOT EXISTS.";
	});

	Route::group(["prefix" => "redirect"], function(){
		Route::get("/", "RedirectController@index");
		Route::post("/", "RedirectController@index");
	});

	Route::group(["prefix" => "front"], function(){
		Route::post("/", "FrontController@index");
	});
	
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
});