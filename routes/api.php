<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/welcome', function () {
    return response()->json([
        'message' => __('api.welcome_message'),
    ]);
});

Route::get('/error_test', function () {
    throw ValidationException::withMessages([
        'example_field' => [__('api.error_test_example_field')],
    ]);
});
