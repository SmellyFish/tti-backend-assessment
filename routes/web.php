<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    $markdown = file_get_contents(resource_path('docs/api-schema.md'));

    return view('welcome', [
        'apiDocsHtml' => Str::markdown($markdown),
    ]);
});
