<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::group(['middleware' => 'cors'], function(){

    Route::get('api/', function () {
        return view('welcome');
    });

    Route::resource('api/collections', 'CollectionsController');

    Route::resource('api/documents', 'DocumentsController',
                    ['only' => ['index', 'show']]);

    Route::get('api/documents/{documents}/cover',
        ['as' => 'api.documents.cover.show', 'uses' => 'DocumentsController@cover']);
    Route::post('api/documents/{documents}/cover',
        ['as' => 'api.documents.cover.store', 'uses' => 'DocumentsController@storeCover']);

});
