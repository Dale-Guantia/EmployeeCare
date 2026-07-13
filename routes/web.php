<?php

use App\Http\Controllers\ArtaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SurveyController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;



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

Route::get('/', function () {
    return redirect(backpack_url());
});

// Survey Form Routes
Route::get('/survey', [SurveyController::class, 'showForm'])->name('survey.form');
Route::post('/survey', [SurveyController::class, 'submitForm'])->name('survey.submit');

// Survey Form Routes
Route::get('/arta-form', [ArtaController::class, 'showForm'])->name('arta.form');
Route::post('/arta-form', [ArtaController::class, 'submitForm'])->name('arta.submit');


