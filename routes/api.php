<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('test', 'Api\v1\ApiController@test');

Route::prefix('v1')->namespace('Api\v1')->group(function () {
    // Controllers Within The "App\Http\Controllers\Admin" Namespace

    // Route::get('test', 'ApiController@test');
    //兩個api 匯入資料

    Route::post('store','ApiController@store');
    Route::post('user','ApiController@user');


    //功能api
    // Route::get('','ApiController@test');

    // List all book stores that are open at a certain datetime
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // List all book stores that are open on a day of the week, at a certain time
    Route::post('checkopenningdaystores','ApiController@checkopenningdaystores');
    // List all book stores that are open for more or less than x hours per day or week
    Route::post('openhours','ApiController@openhours');
    // List all books that are within a price range, sorted by price or alphabetically
    Route::post('booksprice','ApiController@booksprice');
    // List all book stores that have more or less than x number of books
    Route::post('booksamount','ApiController@booksamount');
    // List all book stores that have more or less than x number of books within a price range
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // Search for book stores or books by name, ranked by relevance to search term
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // The top x users by total transaction amount within a date range
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // The total number and dollar value of transactions that happened within a date range
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // Edit book store name, book name, book price and user name
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // The most popular book stores by transaction volume, either by number of transactions or transaction dollar value
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // Total number of users who made transactions above or below $v within a date range
    Route::post('checkopenningstores','ApiController@checkopenningstores');
    // Process a user purchasing a book from a book store, handling all relevant data changes in an atomic transaction
    Route::post('checkopenningstores','ApiController@checkopenningstores');



    
});
