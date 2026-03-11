<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SurveyController;


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

// Route::get('/download/report', [ReportController::class, 'report'])->name('download_report');
// Route::get('/download/css-report', [ReportController::class, 'cssReport'])->name('download_css_report');
