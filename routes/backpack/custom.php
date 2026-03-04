<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\LoginController;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\Base.
// Routes you generate using Backpack\Generators will be placed here.


// Manually override the Register routes
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => 'web',
], function () {
    Route::get('register', 'App\Http\Controllers\Admin\Auth\RegisterController@showRegistrationForm')->name('backpack.auth.register');
    Route::post('register', 'App\Http\Controllers\Admin\Auth\RegisterController@register');
});

// Manually override the Login routes
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => 'web',
], function () {

    Route::get('login', [LoginController::class, 'showLoginForm'])->name('backpack.auth.login');
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->name('backpack.auth.logout');

});

Route::get('api/department/{id}/divisions', function($id) {
    return App\Models\Division::where('department_id', $id)->get(['id', 'division_name']);
});

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace'  => 'App\Http\Controllers\Admin',
], function () {

    Route::crud('ticket', 'TicketCrudController');
    Route::crud('issue', 'IssueCrudController');
    Route::crud('department', 'DepartmentCrudController');
    Route::crud('division', 'DivisionCrudController');
    Route::crud('priority', 'PriorityCrudController');
    Route::crud('status', 'StatusCrudController');
    Route::crud('user', 'UserCrudController');
});
