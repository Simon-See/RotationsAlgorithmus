<?php

use App\Http\Controllers\Rotationsplan;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RotationsplanAlt;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', [Rotationsplan::class, 'index']);
//Route::get('/tasks', 'TaskController@exportCsv');
Route::get('get_csv', [\App\Http\Controllers\CsvController::class, 'get_csv'])->name('get_csv');
