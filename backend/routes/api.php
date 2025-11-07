<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DocumentController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('mfiles.auth')->group(function () {
    // Clients routes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    
    // Documents routes
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
});
