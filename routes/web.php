<?php

use App\Http\Controllers\InterviewerRotationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/interviewer-rotation', 'App\Http\Controllers\InterviewerRotationController@index')->name('interviewer-rotation.index');
// Upload of the Csvs
Route::post('/upload/{type}', [InterviewerRotationController::class, 'upload'])->name('upload');

Route::get('/downloadTemplate/{type}', 'App\Http\Controllers\InterviewerRotationController@downloadTemplate')->name('downloadTemplate');

Route::get('/calculate-rotation', [InterviewerRotationController::class, 'calculateRotation'])->name('calculateRotation');
