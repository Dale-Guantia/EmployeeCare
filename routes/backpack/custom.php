<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\RegisterController;
use App\Http\Controllers\Admin\MyAccountController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SurveyReportsController;
use App\Http\Controllers\Admin\NotificationController;
use \App\Http\Controllers\Admin\ArtaSurveyReportsController;

// Register routes
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => ['web'],
], function () {
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('backpack.auth.register');
    Route::post('register', [RegisterController::class, 'register']);
});

// Login routes
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => ['web'],
], function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('backpack.auth.login');
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->name('backpack.auth.logout');
});

// API route
Route::get('api/department/{id}/divisions', function ($id) {
    return \App\Models\Division::where('department_id', $id)->get(['id', 'division_name']);
});

// Protected Backpack admin routes
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {
    Route::get('dashboard', [DashboardController::class, 'dashboard'])->name('backpack.dashboard');

    // CRUD routes
    Route::crud('ticket', 'TicketCrudController');
    Route::crud('issue', 'IssueCrudController');
    Route::crud('department', 'DepartmentCrudController');
    Route::crud('division', 'DivisionCrudController');
    Route::crud('priority', 'PriorityCrudController');
    Route::crud('status', 'StatusCrudController');
    Route::crud('user', 'UserCrudController');

    // Reports
    Route::get('reports', [ReportsController::class, 'index'])->name('page.reports.index');
    Route::post('reports/download-pdf', [ReportsController::class, 'downloadPdf'])->name('page.reports.download_pdf');

    // Survey Reports
    Route::get('survey-reports', [SurveyReportsController::class, 'index'])->name('page.survey_reports.index');
    Route::post('survey-reports/download-pdf', [SurveyReportsController::class, 'downloadPdf'])->name('page.survey_reports.download_pdf');

    // ARTA Survey Reports
    Route::get('arta-survey-reports', [ArtaSurveyReportsController::class, 'index'])->name('page.arta_survey_reports.index');
    Route::post('arta-survey-reports/download-pdf', [ArtaSurveyReportsController::class, 'downloadPdf'])->name('page.arta_survey_reports.download_pdf');

    // Notifications
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::delete('notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear_all');

    // My Account
    Route::get('my-account', [MyAccountController::class, 'getAccountInfoForm'])->name('backpack.account.info');
    Route::post('my-account/info', [MyAccountController::class, 'postAccountInfoForm'])->name('backpack.account.info.store');
    Route::post('my-account/password', [MyAccountController::class, 'postChangePasswordForm'])->name('backpack.account.password');
    Route::post('my-account/notifications', [MyAccountController::class, 'postNotificationsForm'])->name('backpack.account.notifications');
    Route::post('my-account/notification-settings', [MyAccountController::class, 'updateNotificationSettings'])->name('backpack.account.notification_settings');
});
