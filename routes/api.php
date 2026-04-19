<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/welcome', function () {
    return response()->json([
        'message' => 'Hello, world',
    ]);
});

Route::get('/error_test', function () {
    throw ValidationException::withMessages([
        'example_field' => ['Sample validation error for API error format testing.'],
    ]);
});
