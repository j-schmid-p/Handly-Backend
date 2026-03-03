<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/register/client', [AuthController::class, 'registerClient']);


