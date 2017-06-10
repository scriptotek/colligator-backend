<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::resource('collections', 'CollectionsController');
Route::resource('documents', 'DocumentsController',
    ['only' => ['index', 'show']]);
Route::resource('ontosaurs', 'OntosaurController',
    ['only' => ['index', 'show']]);

Route::get('documents/{document}/cover', [
	'as' => 'api.documents.cover.show',
	'uses' => 'DocumentsController@cover',
]);
Route::post('documents/{document}/cover', [
	'as' => 'api.documents.cover.store',
	'uses' => 'DocumentsController@storeCover',
    'middleware' => 'iplimit',
]);
Route::post('documents/{document}/description', [
	'as' => 'api.documents.description.store',
	'uses' => 'DocumentsController@storeDescription',
    'middleware' => 'iplimit',
]);
Route::post('documents/{document}/cannotFindCover', [
	'as' => 'api.documents.cover.cannotfind',
	'uses' => 'DocumentsController@cannotFindCover',
    'middleware' => 'iplimit',
]);

Route::get('ipcheck', function () {
    return 'OK';
})->middleware('iplimit');
