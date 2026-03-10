<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProfessionalController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/register/client', [AuthController::class, 'registerClient']);
Route::post('/register/professional', [AuthController::class, 'registerProfessional']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/professions',[AuthController::class, 'index']);


// rutas protegidas (acceso mediante token)
Route::middleware('auth:sanctum')->group(function (){

    Route::get('/perfil', function (Request $request){
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/professionals', [AuthController::class, 'index']);
    Route::get('/professionals/{id}', [AuthController::class, 'show']);

});


