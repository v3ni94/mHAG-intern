<?php

use App\Http\Controllers\CorporateBodyController;
use App\Http\Controllers\HoldingDashboardController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\ResolutionController;
use App\Http\Controllers\ResolutionVoteController;
use App\Http\Controllers\ShareholderController;
use App\Http\Controllers\ShareholderListController;
use App\Http\Controllers\ShareTransactionController;
use App\Http\Controllers\SignatureRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Holding (Agent E): Aktionäre, Aktienbewegungen, Beteiligungen,
| Organe, Beschlüsse, Signaturen (Abschnitte 75 bis 106 Masterprompt)
|--------------------------------------------------------------------------
| Wird von routes/web.php innerhalb der auth+active+two-factor-Gruppe geladen.
*/

// ---------- Holding-Dashboard (Abschnitt 106) ----------
Route::get('/holding', [HoldingDashboardController::class, 'index'])
    ->middleware('permission:shares.view')->name('holding.dashboard');

// ---------- Aktionäre (Abschnitte 77, 81 bis 83) ----------
Route::get('/aktionaere', [ShareholderController::class, 'index'])
    ->middleware('permission:shares.view')->name('shareholders.index');
Route::post('/aktionaere', [ShareholderController::class, 'store'])
    ->middleware('permission:shares.prepare')->name('shareholders.store');

// Aktionärslisten-Snapshots vor der Wildcard-Route registrieren
Route::post('/aktionaere/liste', [ShareholderListController::class, 'create'])
    ->middleware('permission:shares.list')->name('shareholders.list.create');
Route::get('/aktionaere/liste/{snapshot}', [ShareholderListController::class, 'download'])
    ->middleware('permission:shares.view')->name('shareholders.list.download');

Route::get('/aktionaere/{shareholder}', [ShareholderController::class, 'show'])
    ->middleware('permission:shares.view')->name('shareholders.show');
Route::put('/aktionaere/{shareholder}', [ShareholderController::class, 'update'])
    ->middleware('permission:shares.prepare')->name('shareholders.update');

// ---------- Aktienbewegungen (Abschnitte 78 bis 80) ----------
Route::get('/aktienbewegungen', [ShareTransactionController::class, 'index'])
    ->middleware('permission:shares.view')->name('share-transactions.index');
Route::get('/aktienbewegungen/neu', [ShareTransactionController::class, 'create'])
    ->middleware('permission:shares.prepare')->name('share-transactions.create');
Route::post('/aktienbewegungen', [ShareTransactionController::class, 'store'])
    ->middleware('permission:shares.prepare')->name('share-transactions.store');
Route::get('/aktienbewegungen/{share_transaction}', [ShareTransactionController::class, 'show'])
    ->middleware('permission:shares.view')->name('share-transactions.show');
Route::post('/aktienbewegungen/{share_transaction}/wirksam', [ShareTransactionController::class, 'makeEffective'])
    ->middleware('permission:shares.finalize')->name('share-transactions.make-effective');
Route::post('/aktienbewegungen/{share_transaction}/storno', [ShareTransactionController::class, 'cancel'])
    ->middleware('permission:shares.finalize')->name('share-transactions.cancel');

// ---------- Beteiligungen (Abschnitt 84) ----------
Route::get('/beteiligungen', [InvestmentController::class, 'index'])
    ->middleware('permission:shares.view')->name('investments.index');
Route::get('/beteiligungen/neu', [InvestmentController::class, 'create'])
    ->middleware('permission:shares.prepare')->name('investments.create');
Route::post('/beteiligungen', [InvestmentController::class, 'store'])
    ->middleware('permission:shares.prepare')->name('investments.store');
Route::get('/beteiligungen/{investment}', [InvestmentController::class, 'show'])
    ->middleware('permission:shares.view')->name('investments.show');
Route::get('/beteiligungen/{investment}/bearbeiten', [InvestmentController::class, 'edit'])
    ->middleware('permission:shares.prepare')->name('investments.edit');
Route::put('/beteiligungen/{investment}', [InvestmentController::class, 'update'])
    ->middleware('permission:shares.prepare')->name('investments.update');

// ---------- Vorstand & Aufsichtsrat (Abschnitte 85 bis 87) ----------
Route::get('/organe', [CorporateBodyController::class, 'index'])
    ->middleware('permission:shares.view')->name('corporate-bodies.index');
Route::get('/organe/{corporate_body}', [CorporateBodyController::class, 'show'])
    ->middleware('permission:shares.view')->name('corporate-bodies.show');
Route::post('/organe/{corporate_body}/mitglieder', [CorporateBodyController::class, 'storeMember'])
    ->middleware('permission:shares.prepare')->name('corporate-bodies.members.store');
Route::post('/organe/{corporate_body}/mitglieder/{member}/beenden', [CorporateBodyController::class, 'endMember'])
    ->middleware('permission:shares.prepare')->name('corporate-bodies.members.end');

// ---------- Beschlüsse (Abschnitte 88 bis 98) ----------
Route::get('/beschluesse', [ResolutionController::class, 'index'])
    ->middleware('permission:resolutions.view')->name('resolutions.index');
Route::get('/beschluesse/neu', [ResolutionController::class, 'create'])
    ->middleware('permission:resolutions.create')->name('resolutions.create');
Route::post('/beschluesse', [ResolutionController::class, 'store'])
    ->middleware('permission:resolutions.create')->name('resolutions.store');
Route::get('/beschluesse/{resolution}', [ResolutionController::class, 'show'])
    ->middleware('permission:resolutions.view')->name('resolutions.show');
Route::get('/beschluesse/{resolution}/bearbeiten', [ResolutionController::class, 'edit'])
    ->middleware('permission:resolutions.update')->name('resolutions.edit');
Route::put('/beschluesse/{resolution}', [ResolutionController::class, 'update'])
    ->middleware('permission:resolutions.update')->name('resolutions.update');
Route::post('/beschluesse/{resolution}/status', [ResolutionController::class, 'updateStatus'])
    ->middleware('permission:resolutions.update')->name('resolutions.status');
Route::post('/beschluesse/{resolution}/abstimmung', [ResolutionVoteController::class, 'store'])
    ->middleware('permission:resolutions.vote')->name('resolutions.vote');
Route::post('/beschluesse/{resolution}/finalisieren', [ResolutionController::class, 'finalize'])
    ->middleware('permission:resolutions.finalize')->name('resolutions.finalize');
Route::get('/beschluesse/{resolution}/pdf', [ResolutionController::class, 'pdf'])
    ->middleware('permission:resolutions.view')->name('resolutions.pdf');
Route::post('/beschluesse/{resolution}/verknuepfungen', [ResolutionController::class, 'storeLink'])
    ->middleware('permission:resolutions.update')->name('resolutions.links.store');
Route::delete('/beschluesse/{resolution}/verknuepfungen/{link}', [ResolutionController::class, 'destroyLink'])
    ->middleware('permission:resolutions.update')->name('resolutions.links.destroy');

// ---------- Digitale Signaturen (Abschnitte 99 bis 102) ----------
Route::get('/signaturen', [SignatureRequestController::class, 'index'])
    ->middleware('permission:resolutions.view')->name('signatures.index');
Route::get('/signaturen/neu', [SignatureRequestController::class, 'create'])
    ->middleware('permission:resolutions.sign')->name('signatures.create');
Route::post('/signaturen', [SignatureRequestController::class, 'store'])
    ->middleware('permission:resolutions.sign')->name('signatures.store');
Route::get('/signaturen/{signature_request}', [SignatureRequestController::class, 'show'])
    ->middleware('permission:resolutions.view')->name('signatures.show');
Route::post('/signaturen/{signature_request}/versenden', [SignatureRequestController::class, 'send'])
    ->middleware('permission:resolutions.sign')->name('signatures.send');
Route::post('/signaturen/{signature_request}/status-abfragen', [SignatureRequestController::class, 'sync'])
    ->middleware('permission:resolutions.sign')->name('signatures.sync');
Route::post('/signaturen/{signature_request}/teilnehmerstatus', [SignatureRequestController::class, 'mark'])
    ->middleware('permission:resolutions.sign')->name('signatures.mark');
Route::post('/signaturen/{signature_request}/signiertes-dokument', [SignatureRequestController::class, 'attachSigned'])
    ->middleware('permission:resolutions.sign')->name('signatures.attach-signed');
