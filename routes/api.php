<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/account', [AccountController::class, 'show']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions/credit', [TransactionController::class, 'credit']);
    Route::post('/transactions/debit', [TransactionController::class, 'debit']);
    Route::post('/transactions/{id}/reverse', [TransactionController::class, 'reverse'])->whereNumber('id');
});
