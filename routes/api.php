<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\BudgetController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/register/client', [AuthController::class, 'registerClient']);
Route::post('/register/professional', [AuthController::class, 'registerProfessional']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/professions',[ProfessionController::class, 'index']);


// rutas protegidas (acceso mediante token)
Route::middleware('auth:sanctum')->group(function (){

    Route::get('/perfil', function (Request $request){
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/professionals', [ProfessionalController::class, 'index']);
    Route::get('/professionals/{id}', [ProfessionalController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']); // soloicitar trabajo 
    Route::get('/tasks/professional', [TaskController::class, 'getProfessionalTasks']);
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::post('/tasks/{id}/budget', [BudgetController::class, 'store']); // enviar presupuesto

});


