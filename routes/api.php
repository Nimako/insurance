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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


//INCIDENT
  Route::group(['prefix'=>'incident'],function(){
  Route::post('create',  'IncidentController@SaveIncident')->middleware('checkheaders');
  Route::post('show',  'IncidentController@GetIncident')->middleware('checkheaders');
  Route::post('all', 'IncidentController@AllIncident')->middleware('checkheaders');
});

//NOTIFICATIONS
Route::post('SendOTP', 'ClientController@SendOTP');

//CLIENT
Route::group(['prefix'=>'ticket'],function(){
  Route::post('create', 'TicketController@SaveClient');
  Route::post('update', 'ClientController@update_client')->middleware('checkheaders');
  Route::post('reset_password',  'ClientController@reset_password')->middleware('checkheaders');
});
                                                                                                                                                  

//CLIENT
  Route::group(['prefix'=>'client'],function(){
  Route::post('create', 'ClientController@SaveClient');
  Route::post('update', 'ClientController@update_client')->middleware('checkheaders');
  Route::post('login',  'ClientController@login');
  Route::post('reset_password',  'ClientController@reset_password')->middleware('checkheaders');
});


//TRANSACTIONS
Route::group(['prefix' => 'transaction'], function () {
  Route::post('load_wallet', 'TransactionController@load_wallet');
  Route::post('check_balance', 'TransactionController@checkbalance');
});
