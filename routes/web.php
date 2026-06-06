<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Dashboard Principal
Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats'])->name('dashboard.stats');

// Routes Relevés
Route::prefix('releves')->group(function () {
    Route::get('/', [\App\Http\Controllers\ReleveController::class, 'index'])->name('releves.index');
    Route::post('/', [\App\Http\Controllers\ReleveController::class, 'store'])->name('releves.store');
    Route::get('/list', [\App\Http\Controllers\ReleveController::class, 'list'])->name('releves.list');
    Route::delete('/{id}', [\App\Http\Controllers\ReleveController::class, 'destroy'])->name('releves.destroy');
});

// Routes Factures
Route::prefix('factures')->group(function () {
    Route::get('/', [\App\Http\Controllers\FactureController::class, 'index'])->name('factures.index');
    Route::get('/list', [\App\Http\Controllers\FactureController::class, 'list'])->name('factures.list');
    Route::post('/grace', [\App\Http\Controllers\FactureController::class, 'grace'])->name('factures.grace');
    Route::post('/recouvrement', [\App\Http\Controllers\FactureController::class, 'recouvrement'])->name('factures.recouvrement');
    Route::get('/generate-bulk-page', [\App\Http\Controllers\FactureController::class, 'generateBulkPage'])->name('factures.generate.page');
    Route::post('/generate-bulk', [\App\Http\Controllers\FactureController::class, 'generateBulk'])->name('factures.generate');
    Route::get('/update-echeance-page', [\App\Http\Controllers\FactureController::class, 'updateEcheanceBulkPage'])->name('factures.echeance.page');
    Route::post('/update-echeance', [\App\Http\Controllers\FactureController::class, 'updateEcheanceBulk'])->name('factures.echeance');
    // Wildcard routes last to avoid swallowing named paths above
    Route::get('/{numero}', [\App\Http\Controllers\FactureController::class, 'show'])->name('factures.show');
});

// Routes Caisse
Route::prefix('caisse')->group(function () {
    Route::get('/', [\App\Http\Controllers\CaisseController::class, 'index'])->name('caisse.index');
    Route::post('/paiement', [\App\Http\Controllers\CaisseController::class, 'paiement'])->name('caisse.paiement');
    Route::post('/abonnement', [\App\Http\Controllers\CaisseController::class, 'payerAbonnement'])->name('caisse.abonnement');
    Route::post('/pret', [\App\Http\Controllers\CaisseController::class, 'payerPret'])->name('caisse.pret');
    Route::post('/revenues', [\App\Http\Controllers\CaisseController::class, 'enregistrerRevenues'])->name('caisse.revenues');
    Route::post('/depenses', [\App\Http\Controllers\CaisseController::class, 'enregistrerDepenses'])->name('caisse.depenses');
    Route::post('/operation/confirm', [\App\Http\Controllers\CaisseController::class, 'confirmOperation'])->name('caisse.confirm');
    Route::post('/operation/cancel', [\App\Http\Controllers\CaisseController::class, 'cancelOperation'])->name('caisse.cancel');
    Route::get('/operations/list', [\App\Http\Controllers\CaisseController::class, 'listOperations'])->name('caisse.operations');
});

// Routes Clients
Route::prefix('clients')->group(function () {
    Route::get('/', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('/list', [\App\Http\Controllers\ClientController::class, 'list'])->name('clients.list');
    Route::get('/{num_client}', [\App\Http\Controllers\ClientController::class, 'show'])->name('clients.show');
    Route::get('/{num_client}/prets', [\App\Http\Controllers\ClientController::class, 'getPrets'])->name('clients.prets');
    Route::post('/', [\App\Http\Controllers\ClientController::class, 'store'])->name('clients.store');
    Route::put('/{num_client}', [\App\Http\Controllers\ClientController::class, 'update'])->name('clients.update');
    Route::delete('/{num_client}', [\App\Http\Controllers\ClientController::class, 'destroy'])->name('clients.destroy');
});

// Routes Compteurs
Route::prefix('compteurs')->group(function () {
    Route::get('/', [\App\Http\Controllers\CompteurController::class, 'index'])->name('compteurs.index');
    Route::get('/list', [\App\Http\Controllers\CompteurController::class, 'list'])->name('compteurs.list');
    Route::get('/client/{numClient}', [\App\Http\Controllers\CompteurController::class, 'getByClient'])->name('compteurs.client');
    Route::post('/', [\App\Http\Controllers\CompteurController::class, 'store'])->name('compteurs.store');
    Route::put('/{id}', [\App\Http\Controllers\CompteurController::class, 'update'])->name('compteurs.update');
    Route::delete('/{id}', [\App\Http\Controllers\CompteurController::class, 'destroy'])->name('compteurs.destroy');
});

// Routes Prêts
Route::prefix('prets')->group(function () {
    Route::get('/', [\App\Http\Controllers\PretController::class, 'index'])->name('prets.index');
    Route::get('/list', [\App\Http\Controllers\PretController::class, 'list'])->name('prets.list');
    Route::post('/', [\App\Http\Controllers\PretController::class, 'store'])->name('prets.store');
    Route::put('/{id}', [\App\Http\Controllers\PretController::class, 'update'])->name('prets.update');
    Route::post('/{id}/suspend', [\App\Http\Controllers\PretController::class, 'suspend'])->name('prets.suspend');
    Route::post('/{id}/reactivate', [\App\Http\Controllers\PretController::class, 'reactivate'])->name('prets.reactivate');
    Route::delete('/{id}', [\App\Http\Controllers\PretController::class, 'destroy'])->name('prets.destroy');
});

// Routes Paramètres
Route::prefix('parametres')->group(function () {
    Route::get('/', [\App\Http\Controllers\ParametreController::class, 'index'])->name('parametres.index');

    // Général
    Route::post('/general', [\App\Http\Controllers\ParametreController::class, 'updateGeneral'])->name('parametres.general');

    // Usages
    Route::get('/usages/list', [\App\Http\Controllers\ParametreController::class, 'listUsages'])->name('parametres.usages.list');
    Route::post('/usages', [\App\Http\Controllers\ParametreController::class, 'storeUsage'])->name('parametres.usages.store');
    Route::put('/usages/{id}', [\App\Http\Controllers\ParametreController::class, 'updateUsage'])->name('parametres.usages.update');
    Route::delete('/usages/{id}', [\App\Http\Controllers\ParametreController::class, 'destroyUsage'])->name('parametres.usages.destroy');

    // Type Opérations
    Route::get('/typeoperations/list', [\App\Http\Controllers\ParametreController::class, 'listTypeOperations'])->name('parametres.typeoperations.list');
    Route::post('/typeoperations', [\App\Http\Controllers\ParametreController::class, 'storeTypeOperation'])->name('parametres.typeoperations.store');
    Route::put('/typeoperations/{id}', [\App\Http\Controllers\ParametreController::class, 'updateTypeOperation'])->name('parametres.typeoperations.update');
    Route::delete('/typeoperations/{id}', [\App\Http\Controllers\ParametreController::class, 'destroyTypeOperation'])->name('parametres.typeoperations.destroy');

    // Utilisateurs
    Route::get('/users/list', [\App\Http\Controllers\ParametreController::class, 'listUsers'])->name('parametres.users.list');
    Route::post('/users', [\App\Http\Controllers\ParametreController::class, 'storeUser'])->name('parametres.users.store');
    Route::put('/users/{id}', [\App\Http\Controllers\ParametreController::class, 'updateUser'])->name('parametres.users.update');
    Route::delete('/users/{id}', [\App\Http\Controllers\ParametreController::class, 'destroyUser'])->name('parametres.users.destroy');

    // Rôles
    Route::post('/roles', [\App\Http\Controllers\ParametreController::class, 'storeRole'])->name('parametres.roles.store');
    Route::delete('/roles/{id}', [\App\Http\Controllers\ParametreController::class, 'destroyRole'])->name('parametres.roles.destroy');

    // Permissions
    Route::post('/permissions', [\App\Http\Controllers\ParametreController::class, 'updatePermissions'])->name('parametres.permissions');

    // Réductions
    Route::get('/reductions/list', [\App\Http\Controllers\ParametreController::class, 'listReductions'])->name('parametres.reductions.list');
    Route::post('/reductions', [\App\Http\Controllers\ParametreController::class, 'storeReduction'])->name('parametres.reductions.store');
    Route::put('/reductions/{id}', [\App\Http\Controllers\ParametreController::class, 'updateReduction'])->name('parametres.reductions.update');
    Route::delete('/reductions/{id}', [\App\Http\Controllers\ParametreController::class, 'destroyReduction'])->name('parametres.reductions.destroy');
    Route::post('/reductions/{id}/toggle', [\App\Http\Controllers\ParametreController::class, 'toggleReduction'])->name('parametres.reductions.toggle');
});

// Routes Impression PDF
Route::prefix('print')->group(function () {
    // Factures individuelles A4 (coupon + ciseaux + arriérés + prêts)
    Route::post('/factures',          [\App\Http\Controllers\PrintController::class, 'printFactures'])->name('print.factures');
    // Bons de coupure A4 (frais 2000 FCFA + REMISE 48H)
    Route::post('/bons-coupure',      [\App\Http\Controllers\PrintController::class, 'printBonsCoupure'])->name('print.bons');
    // Fiche de relevé A4 paysage (tableau agents de terrain)
    Route::post('/fiche-releve',      [\App\Http\Controllers\PrintController::class, 'printFicheReleve'])->name('print.releve');
    // Ticket thermique 62mm (règlement facture ou opération caisse)
    Route::get('/ticket',             [\App\Http\Controllers\PrintController::class, 'printTicket'])->name('print.ticket');
    // Liste générique paysage (clients / factures / relevés / prêts / opérations)
    Route::post('/list',              [\App\Http\Controllers\PrintController::class, 'printList'])->name('print.list');
    // Journal de caisse A4 portrait
    Route::post('/operations',        [\App\Http\Controllers\PrintController::class, 'printOperations'])->name('print.operations');
    // Clients suspendus A4 portrait
    Route::post('/clients-suspendus', [\App\Http\Controllers\PrintController::class, 'printClientsSuspendus'])->name('print.suspendus');
    // Tableau récapitulatif factures A4 paysage
    Route::post('/factures-list',     [\App\Http\Controllers\PrintController::class, 'printFacturesList'])->name('print.factures.list');
});
