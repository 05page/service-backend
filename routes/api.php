<?php

use App\Http\Controllers\AchatsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeIntermediaireController;
use App\Http\Controllers\FacturesController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\VentesController;
use App\Models\Achats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::post('/set-password', [AuthController::class, 'setPassword']);

Route::post('activate-account', [EmployeIntermediaireController::class, 'activateAccount']);

Route::post('/password/forgot', [PasswordController::class, 'forgotPassword']);
Route::post('/password/check-token', [PasswordController::class, 'checkResetToken']);
Route::post('/password/reset', [PasswordController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profil', [ProfileController::class, 'userInfo']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/update', [ProfileController::class, 'updateProfile']);
    Route::post('/password/change', [PasswordController::class, 'changePassword']);

    Route::get('/dashboard', [StatsController::class, 'statsDashboard']);
    Route::get('/allStats', [StatsController::class, 'allStats']);

    Route::prefix('admin')->group(function () {
        Route::post('/createUser', [EmployeIntermediaireController::class, 'createUser']);
        Route::get('/showEmploye', [EmployeIntermediaireController::class, 'showEmploye']);
        Route::get('/show/{id}', [EmployeIntermediaireController::class, 'show']);
        Route::put('/updateEmploye', [EmployeIntermediaireController::class, 'updateEmploye']);
        Route::post('/deleteEmployes', [EmployeIntermediaireController::class, 'deleteAllEmployes']);
        Route::post('/deleteEmploye/{id}', [EmployeIntermediaireController::class, 'deleteEmploye']);
        Route::post('/activateEmploye/{id}', [EmployeIntermediaireController::class, 'activateUser']);
        Route::post('/desactivateEmploye/{id}', [EmployeIntermediaireController::class, 'desActivateUser']);

        Route::post('/createPermission', [PermissionsController::class, 'createPermission']);
        Route::get('/showPermissions', [PermissionsController::class, 'showPermission']);
        Route::get('/showPermission/{id}', [PermissionsController::class, 'selectPermission']);
        Route::post('/permission/{id}', [PermissionsController::class, 'activePermission']);
    });

    Route::prefix('fournisseurs')->group(function () {
        Route::get('/', [FournisseurController::class, 'showFournisseur']);           // Lister (view_suppliers)
        Route::post('/', [FournisseurController::class, 'createFournisseur']);          // Créer (add_suppliers)
        Route::get('/stats', [FournisseurController::class, 'statsFournisseurs']);
        Route::post('desactive/{id}', [FournisseurController::class, 'desactiverFournisseur']);
        Route::post('reactive/{id}', [FournisseurController::class, 'reactiverFournisseur']);
        Route::get('{id}', [FournisseurController::class, 'selectFournisseur']);         // Voir détail (view_suppliers)
        Route::put('{id}', [FournisseurController::class, 'updateFournisseur']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [FournisseurController::class, 'destroy']);   // Supprimer (delete_suppliers)
    });

    Route::prefix('stock')->group(function () {
        Route::post('/', [StockController::class, 'addStock']);
        Route::get('/', [StockController::class, 'showStocks']);
        Route::get('/stats', [StockController::class, 'statStock']);
        Route::post('/desactive/{id}', [StockController::class, 'desactiveStock']);       // Modifier (edit_suppliers)
        Route::post('/active/{id}', [StockController::class, 'activeStock']);       // Modifier (edit_suppliers)
        Route::get('{id}', [StockController::class, 'show']);         // Voir détail (view_suppliers)
        Route::put('{id}', [StockController::class, 'updateStock']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [StockController::class, 'delete']);   // Supprimer (delete_suppliers)
        Route::delete('/', [StockController::class, 'deleteAll']);   // Supprimer (delete_suppliers)
    });

    Route::prefix('ventes')->group(function () {
        Route::post('/', [VentesController::class, 'createVente']);
        Route::get('/', [VentesController::class, 'showVentes']);         // Voir détail (view_suppliers)
        Route::get('/allStats', [StatsController::class, 'allStats']);
        Route::get('/client', [VentesController::class, 'client']);
        Route::get('/myStats', [VentesController::class, 'myStats']);
        Route::get('{id}', [VentesController::class, 'selectVente']);
        Route::put('{id}', [VentesController::class, 'update']);       // Modifier (edit_suppliers)
        Route::post('/validePaye/{id}', [VentesController::class, 'marquePayer']);       // Modifier (edit_suppliers)
        Route::post('/annuler/{id}', [VentesController::class, 'marqueAnnuler']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [VentesController::class, 'deleteVente']);   // Supprimer (delete_suppliers)
        Route::delete('/', [VentesController::class, 'deleteAll']);   // Supprimer (delete_suppliers)
    });

    Route::prefix('achat')->group(function () {
        Route::post('/', [AchatsController::class, 'createAchat']);
         Route::get('/', [AchatsController::class, 'showAchats']);         // Voir détail (view_suppliers)
        Route::get('/stats', [AchatsController::class, 'statsAchat']);
        // Route::get('/myStats/{userdId}', [StatsController::class, 'myStats']);
        Route::get('{id}', [AchatsController::class, 'selectAchat']);
        Route::put('{id}', [AchatsController::class, 'updateAchat']);       // Modifier (edit_suppliers)
        // Route::post('/validePaye/{id}', [AchatsController::class, 'marquePaye']); 
        // Route::post('/ConfirmeAchat/{id}', [AchatsController::class, 'marqueConfirme']);
        Route::post('/annuler/{id}', [AchatsController::class, 'marqueAnnule']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [AchatsController::class, 'deleteAchat']);   // Supprimer (delete_suppliers)
    });

    Route::prefix('factures')->group(function () {
        // Génération directe PDF (workflow unique)
        Route::get('/vente/{id}/pdf', [FacturesController::class, 'generateFacturePDFFromVente']);
        Route::get('/achat/{id}/pdf', [FacturesController::class, 'generateFacturePDFFromAchat']);
        
        // Consultation
        Route::get('/', [FacturesController::class, 'index']);
        Route::get('/{id}', [FacturesController::class, 'show']);
    });

});
