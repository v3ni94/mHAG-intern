<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentifizierung
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.challenge.store');

    Route::get('/passwort-vergessen', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/passwort-vergessen', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/passwort-zuruecksetzen/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/passwort-zuruecksetzen', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');

    // Einladung (Abschnitt 12): persönlicher, zeitlich begrenzter Einmal-Link
    Route::get('/einladung/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/einladung/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Angemeldeter Bereich
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active', 'two-factor'])->group(function () {
    Route::redirect('/', '/dashboard');

    // Profil & Sicherheit
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/passwort', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profil/datenschutzmodus', [ProfileController::class, 'togglePrivacyMode'])->name('profile.privacy');

    // Profilbild (Anforderung 30.08.2026): Ablage ausserhalb von public/,
    // Ausgabe nur ueber den berechtigungsgepruefen Controller.
    Route::post('/profil/bild', [\App\Http\Controllers\AvatarController::class, 'store'])->name('profile.avatar.store');
    Route::delete('/profil/bild', [\App\Http\Controllers\AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
    Route::get('/profilbild/{user}', [\App\Http\Controllers\AvatarController::class, 'show'])
        ->whereNumber('user')->name('profile.avatar.show');

    Route::get('/zwei-faktor', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/zwei-faktor/bestaetigen', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/zwei-faktor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    Route::delete('/zwei-faktor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    // Kontextwechsel (Abschnitt 13)
    Route::post('/kontext', [ContextController::class, 'switch'])->name('context.switch');

    /*
    |----------------------------------------------------------------------
    | Fachmodule (routes/modules/*.php)
    |----------------------------------------------------------------------
    */
    foreach (glob(__DIR__.'/modules/*.php') as $moduleRoutes) {
        require $moduleRoutes;
    }
});
