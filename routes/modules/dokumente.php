<?php

use App\Http\Controllers\Admin\SftpController;
use App\Http\Controllers\ContractAmendmentController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul: Dokumente & Verträge (Abschnitte 52-65 Masterprompt)
|--------------------------------------------------------------------------
| Wird innerhalb der auth+active+two-factor-Gruppe geladen (routes/web.php).
*/

// ---------- Dokumentenmanagement ----------
Route::prefix('dokumente')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])
        ->middleware('permission:documents.view')->name('documents.index');
    Route::get('/neu', [DocumentController::class, 'create'])
        ->middleware('permission:documents.upload')->name('documents.create');
    Route::post('/', [DocumentController::class, 'store'])
        ->middleware('permission:documents.upload')->name('documents.store');
    Route::get('/{document}', [DocumentController::class, 'show'])
        ->middleware('permission:documents.view')->name('documents.show');
    Route::get('/{document}/download', [DocumentController::class, 'download'])
        ->middleware('permission:documents.download')->name('documents.download');
    Route::post('/{document}/versionen', [DocumentController::class, 'storeVersion'])
        ->middleware('permission:documents.upload')->name('documents.versions.store');
    Route::post('/{document}/verknuepfen', [DocumentController::class, 'link'])
        ->middleware('permission:documents.upload')->name('documents.link');
    Route::post('/{document}/archivieren', [DocumentController::class, 'archive'])
        ->middleware('permission:documents.archive')->name('documents.archive');
    Route::delete('/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:documents.delete')->name('documents.destroy');
});

// ---------- Vertragsvorlagen ----------
Route::prefix('vertragsvorlagen')->group(function () {
    Route::get('/', [ContractTemplateController::class, 'index'])
        ->middleware('permission:contracts.view')->name('contract-templates.index');
    Route::get('/neu', [ContractTemplateController::class, 'create'])
        ->middleware('permission:contracts.update')->name('contract-templates.create');
    Route::post('/', [ContractTemplateController::class, 'store'])
        ->middleware('permission:contracts.update')->name('contract-templates.store');
    Route::get('/{contractTemplate}', [ContractTemplateController::class, 'show'])
        ->middleware('permission:contracts.view')->name('contract-templates.show');
    Route::get('/{contractTemplate}/bearbeiten', [ContractTemplateController::class, 'edit'])
        ->middleware('permission:contracts.update')->name('contract-templates.edit');
    Route::put('/{contractTemplate}', [ContractTemplateController::class, 'update'])
        ->middleware('permission:contracts.update')->name('contract-templates.update');
    Route::post('/{contractTemplate}/versionen', [ContractTemplateController::class, 'storeVersion'])
        ->middleware('permission:contracts.update')->name('contract-templates.versions.store');
});

// ---------- Verträge ----------
Route::prefix('vertraege')->group(function () {
    Route::get('/', [ContractController::class, 'index'])
        ->middleware('permission:contracts.view')->name('contracts.index');
    Route::get('/neu', [ContractController::class, 'create'])
        ->middleware('permission:contracts.create')->name('contracts.create');
    Route::post('/', [ContractController::class, 'store'])
        ->middleware('permission:contracts.create')->name('contracts.store');
    Route::get('/{contract}', [ContractController::class, 'show'])
        ->middleware('permission:contracts.view')->name('contracts.show');
    Route::post('/{contract}/finalisieren', [ContractController::class, 'finalize'])
        ->middleware('permission:contracts.finalize')->name('contracts.finalize');
    Route::get('/{contract}/pdf', [ContractController::class, 'pdf'])
        ->middleware('permission:contracts.view')->name('contracts.pdf');
    Route::post('/{contract}/nachtraege', [ContractAmendmentController::class, 'store'])
        ->middleware('permission:contracts.update')->name('contracts.amendments.store');
});

// ---------- Administration: SFTP-Status ----------
Route::prefix('admin/sftp')->middleware('permission:admin.sftp')->group(function () {
    Route::get('/', [SftpController::class, 'index'])->name('admin.sftp.index');
    Route::post('/test', [SftpController::class, 'test'])->name('admin.sftp.test');
});

// ---------- Administration: DocuSign (Abschnitte 99 bis 102) ----------
Route::prefix('admin/docusign')->middleware('permission:admin.settings')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\DocuSignController::class, 'index'])->name('admin.docusign.index');
    Route::post('/test', [\App\Http\Controllers\Admin\DocuSignController::class, 'test'])
        ->middleware('throttle:10,1')->name('admin.docusign.test');
});
