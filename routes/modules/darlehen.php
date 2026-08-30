<?php

use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\DueItemController;
use App\Http\Controllers\GuaranteeController;
use App\Http\Controllers\LiquidityController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanFeeController;
use App\Http\Controllers\LoanInterestTermController;
use App\Http\Controllers\LoanScheduleController;
use App\Http\Controllers\LoanStatementController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SecurityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Darlehen (Agent C): Darlehen, Zahlungen, Sicherheiten, Liquidität
|--------------------------------------------------------------------------
| Abschnitte 18-51, 66-74, 135 Masterprompt. Jede Route ist über eine
| Berechtigung geschützt; der Datenscope läuft zusätzlich über
| Loan::visibleTo($user) in den Controllern (nie über die Route allein).
*/

Route::prefix('darlehen')->name('loans.')->group(function () {
    Route::middleware('permission:loans.view')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/{loan}', [LoanController::class, 'show'])->whereNumber('loan')->name('show');
        // Forderungsaufstellung (Abschnitt 51): Stichtag als Parameter "date", Ausgabe als PDF
        Route::get('/{loan}/forderungsaufstellung', [LoanStatementController::class, 'show'])->whereNumber('loan')->name('statement');
    });

    Route::middleware('permission:loans.create')->group(function () {
        Route::get('/neu', [LoanController::class, 'create'])->name('create');
        Route::post('/', [LoanController::class, 'store'])->name('store');
    });

    Route::middleware('permission:loans.update')->group(function () {
        Route::get('/{loan}/bearbeiten', [LoanController::class, 'edit'])->whereNumber('loan')->name('edit');
        Route::put('/{loan}', [LoanController::class, 'update'])->whereNumber('loan')->name('update');
        Route::post('/{loan}/statuswechsel', [LoanController::class, 'transition'])->whereNumber('loan')->name('transition');
        Route::post('/{loan}/neuberechnung', [LoanController::class, 'recalculate'])->whereNumber('loan')->name('recalculate');

        // Zinssatz-Staffel (Abschnitt 40): historisierte Zinssätze, Änderung löst Neuberechnung aus
        Route::post('/{loan}/zinssaetze', [LoanInterestTermController::class, 'store'])->whereNumber('loan')->name('interest-terms.store');
        Route::delete('/{loan}/zinssaetze/{term}', [LoanInterestTermController::class, 'destroy'])->whereNumber('loan')->whereNumber('term')->name('interest-terms.destroy');

        // Gebühren (Abschnitt 43)
        Route::post('/{loan}/gebuehren', [LoanFeeController::class, 'store'])->whereNumber('loan')->name('fees.store');
        Route::put('/{loan}/gebuehren/{fee}', [LoanFeeController::class, 'update'])->whereNumber('loan')->whereNumber('fee')->name('fees.update');
        Route::delete('/{loan}/gebuehren/{fee}', [LoanFeeController::class, 'destroy'])->whereNumber('loan')->whereNumber('fee')->name('fees.destroy');

        // Auszahlung planen (Abschnitt 31)
        Route::post('/{loan}/auszahlungen', [DisbursementController::class, 'store'])->whereNumber('loan')->name('disbursements.store');

        // Sicherheiten (Abschnitt 66)
        Route::post('/{loan}/sicherheiten', [SecurityController::class, 'store'])->whereNumber('loan')->name('securities.store');
        Route::put('/{loan}/sicherheiten/{security}', [SecurityController::class, 'update'])->whereNumber('loan')->whereNumber('security')->name('securities.update');
        Route::delete('/{loan}/sicherheiten/{security}', [SecurityController::class, 'destroy'])->whereNumber('loan')->whereNumber('security')->name('securities.destroy');

        // Bürgschaften (Abschnitt 67)
        Route::post('/{loan}/buergschaften', [GuaranteeController::class, 'store'])->whereNumber('loan')->name('guarantees.store');
        Route::put('/{loan}/buergschaften/{guarantee}', [GuaranteeController::class, 'update'])->whereNumber('loan')->whereNumber('guarantee')->name('guarantees.update');
        Route::delete('/{loan}/buergschaften/{guarantee}', [GuaranteeController::class, 'destroy'])->whereNumber('loan')->whereNumber('guarantee')->name('guarantees.destroy');
    });

    // Auszahlungen bestätigen / nicht erfolgt / stornieren (Abschnitte 31/32)
    Route::middleware('permission:payments.record')->group(function () {
        Route::post('/auszahlungen/{disbursement}/bestaetigen', [DisbursementController::class, 'confirm'])->whereNumber('disbursement')->name('disbursements.confirm');
        Route::post('/auszahlungen/{disbursement}/nicht-erfolgt', [DisbursementController::class, 'fail'])->whereNumber('disbursement')->name('disbursements.fail');
    });
    Route::middleware('permission:payments.cancel')->group(function () {
        Route::post('/auszahlungen/{disbursement}/stornieren', [DisbursementController::class, 'cancel'])->whereNumber('disbursement')->name('disbursements.cancel');
    });

    // Soll/Ist-Erfassung je Zahlungsplan-Position (Abschnitte 26-28):
    // setzt IST-Werte/Status/Herkunft und stößt die Neuberechnung an.
    Route::middleware('permission:payments.record')->group(function () {
        Route::put('/plan-positionen/{item}', [LoanScheduleController::class, 'update'])->whereNumber('item')->name('schedule.update');
    });

    Route::middleware('permission:loans.archive')->group(function () {
        Route::post('/{loan}/archivieren', [LoanController::class, 'archive'])->whereNumber('loan')->name('archive');
    });
});

/*
|--------------------------------------------------------------------------
| Zahlungen (Abschnitte 46-49): global, Storno nur mit Grund, nie löschen
|--------------------------------------------------------------------------
*/
Route::prefix('zahlungen')->name('payments.')->group(function () {
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('show');
    });
    Route::middleware('permission:payments.record')->group(function () {
        Route::get('/neu', [PaymentController::class, 'create'])->name('create');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
    });
    Route::middleware('permission:payments.cancel')->group(function () {
        Route::post('/{payment}/stornieren', [PaymentController::class, 'cancel'])->whereNumber('payment')->name('cancel');
    });
});

// Fälligkeiten (Abschnitt 72-nah): kommende und überfällige Zahlungsplan-Positionen
Route::get('/faelligkeiten', [DueItemController::class, 'index'])
    ->middleware('permission:payments.view')
    ->name('due-items.index');

// Sicherheiten global inkl. Ablaufwarnung (Abschnitt 66)
Route::get('/sicherheiten', [SecurityController::class, 'index'])
    ->middleware('permission:loans.view')
    ->name('securities.index');

// Liquiditätsplanung (Abschnitt 71)
Route::get('/liquiditaet', [LiquidityController::class, 'index'])
    ->middleware('permission:loans.view')
    ->name('liquidity.index');
