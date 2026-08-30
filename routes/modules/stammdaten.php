<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactDetailController;
use App\Http\Controllers\EntityRelationshipController;
use App\Http\Controllers\EntitySearchController;
use App\Http\Controllers\IdentityDocumentController;
use App\Http\Controllers\OrganizationRoleController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\TaxDetailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Stammdaten (Agent A): Personen, Unternehmen, globale Suche
|--------------------------------------------------------------------------
| Personen- und Unternehmensakten (Abschnitte 5-8, 103-105 Masterprompt).
| Unterressourcen (Adressen, Kontaktdaten, Bankkonten, Steuerdaten,
| Identitätsdokumente, Beziehungen, Organstellungen) laufen über die
| jeweilige Akte; die Controller sind Eltern-agnostisch (Entity-basiert).
*/

// Globale Suche (Abschnitt 105) - Gruppen werden je Berechtigung gefiltert.
Route::get('/suche', [EntitySearchController::class, 'index'])->name('search.index');

/*
|--------------------------------------------------------------------------
| Personen
|--------------------------------------------------------------------------
*/
Route::prefix('personen')->name('persons.')->group(function () {
    Route::middleware('permission:persons.view')->group(function () {
        Route::get('/', [PersonController::class, 'index'])->name('index');
        Route::get('/{entity}', [PersonController::class, 'show'])->whereNumber('entity')->name('show');
    });

    Route::middleware('permission:persons.create')->group(function () {
        Route::get('/neu', [PersonController::class, 'create'])->name('create');
        Route::post('/', [PersonController::class, 'store'])->name('store');
    });

    Route::middleware('permission:persons.update')->group(function () {
        Route::get('/{entity}/bearbeiten', [PersonController::class, 'edit'])->whereNumber('entity')->name('edit');
        Route::put('/{entity}', [PersonController::class, 'update'])->whereNumber('entity')->name('update');

        // Adressen
        Route::post('/{entity}/adressen', [AddressController::class, 'store'])->whereNumber('entity')->name('addresses.store');
        Route::put('/{entity}/adressen/{address}', [AddressController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('addresses.update');
        Route::delete('/{entity}/adressen/{address}', [AddressController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('addresses.destroy');

        // Kontaktdaten
        Route::post('/{entity}/kontakte', [ContactDetailController::class, 'store'])->whereNumber('entity')->name('contacts.store');
        Route::put('/{entity}/kontakte/{contactDetail}', [ContactDetailController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('contacts.update');
        Route::delete('/{entity}/kontakte/{contactDetail}', [ContactDetailController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('contacts.destroy');

        // Bankkonten
        Route::post('/{entity}/bankkonten', [BankAccountController::class, 'store'])->whereNumber('entity')->name('bank-accounts.store');
        Route::put('/{entity}/bankkonten/{bankAccount}', [BankAccountController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('bank-accounts.update');
        Route::delete('/{entity}/bankkonten/{bankAccount}', [BankAccountController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('bank-accounts.destroy');

        // Steuerdaten (eine Zeile je Entity, Anlage/Aktualisierung in einem Schritt)
        Route::post('/{entity}/steuerdaten', [TaxDetailController::class, 'store'])->whereNumber('entity')->name('tax-details.store');
        Route::delete('/{entity}/steuerdaten', [TaxDetailController::class, 'destroy'])->whereNumber('entity')->name('tax-details.destroy');

        // Identitätsdokumente (Ablaufdatum erzeugt automatisch eine Wiedervorlage)
        Route::post('/{entity}/identitaetsdokumente', [IdentityDocumentController::class, 'store'])->whereNumber('entity')->name('identity-documents.store');
        Route::put('/{entity}/identitaetsdokumente/{identityDocument}', [IdentityDocumentController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('identity-documents.update');
        Route::delete('/{entity}/identitaetsdokumente/{identityDocument}', [IdentityDocumentController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('identity-documents.destroy');

        // Organstellungen aus Sicht der Person (Historie: nie löschen, nur beenden)
        Route::post('/{entity}/organstellungen', [OrganizationRoleController::class, 'store'])->whereNumber('entity')->name('organization-roles.store');
        Route::put('/{entity}/organstellungen/{organizationRole}', [OrganizationRoleController::class, 'update'])->whereNumber('entity')->name('organization-roles.update');
        Route::post('/{entity}/organstellungen/{organizationRole}/beenden', [OrganizationRoleController::class, 'end'])->whereNumber('entity')->name('organization-roles.end');
    });

    Route::middleware('permission:persons.archive')->group(function () {
        Route::post('/{entity}/archivieren', [PersonController::class, 'archive'])->whereNumber('entity')->name('archive');
    });
});

/*
|--------------------------------------------------------------------------
| Unternehmen
|--------------------------------------------------------------------------
*/
Route::prefix('unternehmen')->name('companies.')->group(function () {
    Route::middleware('permission:companies.view')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/{entity}', [CompanyController::class, 'show'])->whereNumber('entity')->name('show');
    });

    Route::middleware('permission:companies.create')->group(function () {
        Route::get('/neu', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
    });

    Route::middleware('permission:companies.update')->group(function () {
        Route::get('/{entity}/bearbeiten', [CompanyController::class, 'edit'])->whereNumber('entity')->name('edit');
        Route::put('/{entity}', [CompanyController::class, 'update'])->whereNumber('entity')->name('update');

        // Adressen
        Route::post('/{entity}/adressen', [AddressController::class, 'store'])->whereNumber('entity')->name('addresses.store');
        Route::put('/{entity}/adressen/{address}', [AddressController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('addresses.update');
        Route::delete('/{entity}/adressen/{address}', [AddressController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('addresses.destroy');

        // Kontaktdaten
        Route::post('/{entity}/kontakte', [ContactDetailController::class, 'store'])->whereNumber('entity')->name('contacts.store');
        Route::put('/{entity}/kontakte/{contactDetail}', [ContactDetailController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('contacts.update');
        Route::delete('/{entity}/kontakte/{contactDetail}', [ContactDetailController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('contacts.destroy');

        // Bankkonten
        Route::post('/{entity}/bankkonten', [BankAccountController::class, 'store'])->whereNumber('entity')->name('bank-accounts.store');
        Route::put('/{entity}/bankkonten/{bankAccount}', [BankAccountController::class, 'update'])->whereNumber('entity')->scopeBindings()->name('bank-accounts.update');
        Route::delete('/{entity}/bankkonten/{bankAccount}', [BankAccountController::class, 'destroy'])->whereNumber('entity')->scopeBindings()->name('bank-accounts.destroy');

        // Steuerdaten
        Route::post('/{entity}/steuerdaten', [TaxDetailController::class, 'store'])->whereNumber('entity')->name('tax-details.store');
        Route::delete('/{entity}/steuerdaten', [TaxDetailController::class, 'destroy'])->whereNumber('entity')->name('tax-details.destroy');

        // Unternehmensbeziehungen (Abschnitt 8)
        Route::post('/{entity}/beziehungen', [EntityRelationshipController::class, 'store'])->whereNumber('entity')->name('relationships.store');
        Route::put('/{entity}/beziehungen/{relationship}', [EntityRelationshipController::class, 'update'])->whereNumber('entity')->name('relationships.update');
        Route::delete('/{entity}/beziehungen/{relationship}', [EntityRelationshipController::class, 'destroy'])->whereNumber('entity')->name('relationships.destroy');

        // Organe / Organstellungen (Historie: nie löschen, nur beenden)
        Route::post('/{entity}/organe', [OrganizationRoleController::class, 'store'])->whereNumber('entity')->name('organization-roles.store');
        Route::put('/{entity}/organe/{organizationRole}', [OrganizationRoleController::class, 'update'])->whereNumber('entity')->name('organization-roles.update');
        Route::post('/{entity}/organe/{organizationRole}/beenden', [OrganizationRoleController::class, 'end'])->whereNumber('entity')->name('organization-roles.end');
    });

    Route::middleware('permission:companies.archive')->group(function () {
        Route::post('/{entity}/archivieren', [CompanyController::class, 'archive'])->whereNumber('entity')->name('archive');
    });
});
