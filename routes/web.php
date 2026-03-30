<?php

use Illuminate\Support\Facades\Route;

// API routes are handled in routes/api.php
// All other routes should serve the Vue SPA
// This catch-all route must be last
Route::get('/login', function () {
    return view('welcome');
})->name('login');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|build|storage).*$');
