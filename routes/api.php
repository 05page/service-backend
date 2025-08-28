<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeIntermediaireController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\StockController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::post('/set-password', [AuthController::class, 'setPassword']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profil', [ProfileController::class, 'userInfo']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/update', [ProfileController::class, 'updateProfile']);
    Route::post('/password/change', [PasswordController::class, 'changePassword']);

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
        Route::get('{id}', [FournisseurController::class, 'show']);         // Voir détail (view_suppliers)
        Route::put('{id}', [FournisseurController::class, 'update']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [FournisseurController::class, 'destroy']);   // Supprimer (delete_suppliers)
    });

    Route::prefix('services')->group(function () {
        Route::post('/', [ServicesController::class, 'addServices']);
        Route::get('/', [ServicesController::class, 'showServices']);
        Route::get('{id}', [ServicesController::class, 'show']);         // Voir détail (view_suppliers)
        Route::put('{id}', [ServicesController::class, 'updateService']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [ServicesController::class, 'delete']);   // Supprimer (delete_suppliers)
        Route::delete('/', [ServicesController::class, 'deleteAll']);   // Supprimer (delete_suppliers)
    });

    Route::prefix('stock')->group(function () {
        Route::post('/', [StockController::class, 'addStock']);
        Route::get('/', [StockController::class, 'showStocks']);
        Route::get('{id}', [StockController::class, 'show']);         // Voir détail (view_suppliers)
        Route::put('{id}', [StockController::class, 'updateStock']);       // Modifier (edit_suppliers)
        Route::delete('{id}', [StockController::class, 'delete']);   // Supprimer (delete_suppliers)
        Route::delete('/', [StockController::class, 'deleteAll']);   // Supprimer (delete_suppliers)
    });
});
Route::post('activate-account', [EmployeIntermediaireController::class, 'activateAccount']);

Route::post('/password/forgot', [PasswordController::class, 'forgotPassword']);
Route::post('/password/check-token', [PasswordController::class, 'checkResetToken']);
Route::post('/password/reset', [PasswordController::class, 'resetPassword']);
