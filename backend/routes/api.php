<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DocumentController;
use App\Http\Middleware\MFilesAuth;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(MFilesAuth::class)->group(function () {
    // Clients routes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
    
    // Documents routes
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
    Route::get('/documents/{objectId}/files', [DocumentController::class, 'files']);
    Route::get('/documents/{objectId}/download/{fileId?}', [DocumentController::class, 'download']);
});
