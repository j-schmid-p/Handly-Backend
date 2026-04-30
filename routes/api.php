<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\InvoiceController;

Route::post('/register/client', [AuthController::class, 'registerClient']);
Route::post('/register/professional', [AuthController::class, 'registerProfessional']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/professions',[ProfessionController::class, 'index']);
Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);

// rutas protegidas (acceso mediante token)
Route::middleware('auth:sanctum')->group(function (){
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']); // ver user ya sea cliente o profesional
    Route::get('/perfil', [UserController::class, 'getProfile']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::put('/clients/{id}', [UserController::class, 'updateClient']);
    Route::get('/clients', [UserController::class, 'getClients']);
    Route::get('/clients/{id}', [UserController::class, 'getClientDetails']);
    
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/professionals', [ProfessionalController::class, 'index']);
    Route::get('/professionals/{id}', [ProfessionalController::class, 'show']);
    Route::put('/professionals/{id}', [ProfessionalController::class, 'update']);

    Route::post('/tasks', [TaskController::class, 'store']); // soloicitar trabajo 
    Route::get('/tasks/professional', [TaskController::class, 'getProfessionalTasks']);
    Route::get('/tasks/client', [TaskController::class, 'getClientTasks']);
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::get('/tasks/{id}/details', [TaskController::class, 'getTaskDetails']);
    Route::get('/admin/tasks', [TaskController::class, 'getAllTasks']); //ADMIN
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);


    Route::post('/tasks/{id}/budget', [BudgetController::class, 'store']); // enviar presupuesto
    Route::patch('/budgets/{id}/accept', [BudgetController::class, 'accept']); // acepta presupuesto
    Route::get('/budgets/{id}', [BudgetController::class, 'show']);
    Route::put('/budgets/{id}', [BudgetController::class, 'update']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

    Route::post('/tasks/{id}/invoice', [InvoiceController::class, 'store']); //genera facturas
    Route::get('/admin/invoices', [InvoiceController::class, 'getAllInvoices']);//ADMIN
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

    Route::post('/admin/professions', [ProfessionController::class, 'store']);//ADMIN  
});



