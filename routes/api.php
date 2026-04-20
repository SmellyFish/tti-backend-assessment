<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\InstrumentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'store']);
Route::middleware('auth:sanctum')->get('/auth-test', function () {
    return response()->json([
        'message' => 'Authenticated',
    ]);
});
Route::post('/patients', [PatientController::class, 'store']);
Route::post('/instruments', [InstrumentController::class, 'store']);
Route::post('/patients/{patient}/submissions', [SubmissionController::class, 'store']);
Route::get('/patients/{patient}/submissions', [SubmissionController::class, 'index']);
Route::get('/patients/{patient}/submissions/{submission}', [SubmissionController::class, 'show']);
Route::get('/patients/{patient}/summary', [SubmissionController::class, 'summary']);
