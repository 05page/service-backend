<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeIntermediaireController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function (){
Route::get('/profil', [ProfileController::class, 'userInfo']);
Route::post('/logout', [AuthController::class, 'logout']); 
Route::put('/update', [ProfileController::class, 'updateProfile']);
Route::post('/password/change', [PasswordController::class, 'changePassword']);

Route::prefix('admin/employe_intermediaire')->group(function(){
Route::post('/createUser', [EmployeIntermediaireController::class, 'createUser']);
});
});
Route::post('activate-account', [EmployeIntermediaireController::class, 'activateAccount']);    

Route::post('/password/forgot', [PasswordController::class, 'forgotPassword']);
Route::post('/password/check-token', [PasswordController::class, 'checkResetToken']);
Route::post('/password/reset', [PasswordController::class, 'resetPassword']);