<?php

use App\Http\Controllers\Api\InstrumentController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Route;

Route::post('/patients', [PatientController::class, 'store']);
Route::post('/instruments', [InstrumentController::class, 'store']);
