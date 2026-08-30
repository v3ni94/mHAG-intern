<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organisation: Dashboard, Kalender, Wiedervorlagen, Benachrichtigungen,
| Reports, Hilfe (Abschnitte 68-74, 107-118 Masterprompt)
|--------------------------------------------------------------------------
| Eingebunden über routes/web.php innerhalb von auth + active + two-factor.
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('permission:dashboard.view')
    ->name('dashboard');

// Kalender (Abschnitt 72)
Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');

// Wiedervorlagen (Abschnitt 73)
Route::prefix('wiedervorlagen')->name('reminders.')->group(function () {
    Route::get('/', [ReminderController::class, 'index'])->name('index');
    Route::get('/neu', [ReminderController::class, 'create'])->name('create');
    Route::post('/', [ReminderController::class, 'store'])->name('store');
    Route::get('/{reminder}/bearbeiten', [ReminderController::class, 'edit'])->name('edit');
    Route::put('/{reminder}', [ReminderController::class, 'update'])->name('update');
    Route::post('/{reminder}/erledigt', [ReminderController::class, 'done'])->name('done');
    Route::post('/{reminder}/abbrechen', [ReminderController::class, 'cancel'])->name('cancel');
});

// Benachrichtigungen (Abschnitt 127)
Route::get('/benachrichtigungen', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/benachrichtigungen/alle-gelesen', [NotificationController::class, 'readAll'])->name('notifications.read-all');

// Reports (Abschnitte 107, 108); Export über ?format=csv|xlsx|pdf
Route::middleware('permission:reports.view')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{key}', [ReportController::class, 'show'])->name('reports.show');
});

// Hilfe, FAQ, Changelog (Abschnitte 110-118)
Route::prefix('hilfe')->group(function () {
    Route::get('/', [HelpController::class, 'index'])->name('help.index');
    Route::get('/suche', [HelpController::class, 'search'])->name('help.search');
    Route::get('/was-ist-neu', [HelpController::class, 'changelog'])->name('help.changelog');
    Route::get('/faq', [HelpController::class, 'faq'])->name('faq.index');
    Route::get('/{slug}', [HelpController::class, 'page'])->name('help.page');
});
