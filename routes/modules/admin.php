<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ChangelogController;
use App\Http\Controllers\Admin\DailyFactController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration (Abschnitte 9, 12, 15, 120, 129, 136 Masterprompt)
|--------------------------------------------------------------------------
| Jede Gruppe ist über die spezifische admin.*-Berechtigung geschützt.
*/

Route::prefix('administration')->name('admin.')->group(function () {

    // Benutzer und Einladungen (permission admin.users)
    Route::middleware('permission:admin.users')->group(function () {
        Route::prefix('benutzer')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/neu', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('/{user}', [UserController::class, 'show'])->whereNumber('user')->name('show');
            Route::get('/{user}/bearbeiten', [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::post('/{user}/deaktivieren', [UserController::class, 'deactivate'])->name('deactivate');
            Route::post('/{user}/aktivieren', [UserController::class, 'activate'])->name('activate');

            // Zugangsdaten und Kontopflege (Abschnitt 9)
            Route::post('/{user}/zugangsdaten-senden', [UserController::class, 'sendCredentials'])->name('send-credentials');
            Route::post('/{user}/passwort-zuruecksetzen', [UserController::class, 'sendPasswordReset'])->name('send-password-reset');
            Route::post('/{user}/zwei-faktor-zuruecksetzen', [UserController::class, 'resetTwoFactor'])->name('reset-two-factor');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::post('/{user}/wiederherstellen', [UserController::class, 'restore'])->whereNumber('user')->name('restore');
        });

        Route::prefix('einladungen')->name('invitations.')->group(function () {
            Route::get('/', [InvitationController::class, 'index'])->name('index');
            Route::post('/', [InvitationController::class, 'store'])->name('store');
            Route::post('/{invitation}/erneut-senden', [InvitationController::class, 'resend'])->name('resend');
            Route::post('/{invitation}/widerrufen', [InvitationController::class, 'revoke'])->name('revoke');
        });
    });

    // Rollen (permission admin.roles)
    Route::middleware('permission:admin.roles')->prefix('rollen')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/neu', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}/bearbeiten', [RoleController::class, 'edit'])->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
    });

    // Einstellungen (permission admin.settings)
    Route::middleware('permission:admin.settings')->group(function () {
        Route::get('/einstellungen', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/einstellungen', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/einstellungen/testmail', [SettingController::class, 'sendTestMail'])
            ->middleware('throttle:5,1')->name('settings.test-mail');

        // Systemstatus (Abschnitt 136)
        Route::get('/systemstatus', [SystemStatusController::class, 'index'])->name('status');

        // FAQ-Verwaltung (Abschnitt 114)
        Route::prefix('faq')->name('faq.')->group(function () {
            Route::get('/', [FaqController::class, 'index'])->name('index');
            Route::get('/neu', [FaqController::class, 'create'])->name('create');
            Route::post('/', [FaqController::class, 'store'])->name('store');
            Route::get('/{faq}/bearbeiten', [FaqController::class, 'edit'])->name('edit');
            Route::put('/{faq}', [FaqController::class, 'update'])->name('update');
            Route::delete('/{faq}', [FaqController::class, 'destroy'])->name('destroy');
        });

        // Changelog-Verwaltung "Was ist neu?" (Abschnitt 118)
        Route::prefix('changelog')->name('changelog.')->group(function () {
            Route::get('/', [ChangelogController::class, 'index'])->name('index');
            Route::get('/neu', [ChangelogController::class, 'create'])->name('create');
            Route::post('/', [ChangelogController::class, 'store'])->name('store');
            Route::get('/{changelog}/bearbeiten', [ChangelogController::class, 'edit'])->whereNumber('changelog')->name('edit');
            Route::put('/{changelog}', [ChangelogController::class, 'update'])->whereNumber('changelog')->name('update');
            Route::delete('/{changelog}', [ChangelogController::class, 'destroy'])->whereNumber('changelog')->name('destroy');
        });

        // Einträge "Wussten Sie?" für die Fußzeile (Abschnitt 119)
        Route::prefix('wussten-sie')->name('daily-facts.')->group(function () {
            Route::get('/', [DailyFactController::class, 'index'])->name('index');
            Route::get('/neu', [DailyFactController::class, 'create'])->name('create');
            Route::post('/', [DailyFactController::class, 'store'])->name('store');
            Route::get('/{daily_fact}/bearbeiten', [DailyFactController::class, 'edit'])->whereNumber('daily_fact')->name('edit');
            Route::put('/{daily_fact}', [DailyFactController::class, 'update'])->whereNumber('daily_fact')->name('update');
            Route::delete('/{daily_fact}', [DailyFactController::class, 'destroy'])->whereNumber('daily_fact')->name('destroy');
        });
    });

    // Audit-Trail (permission admin.audit)
    Route::middleware('permission:admin.audit')->prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/{audit}', [AuditLogController::class, 'show'])->name('show');
    });

    // Backups (permission admin.backups)
    Route::middleware('permission:admin.backups')->prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/ausfuehren', [BackupController::class, 'run'])->name('run');
        Route::get('/download/{file}', [BackupController::class, 'download'])->name('download');
    });
});
