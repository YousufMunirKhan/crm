<?php

use Illuminate\Support\Facades\Route;

// API routes are handled in routes/api.php
// All other routes should serve the Vue SPA
// This catch-all route must be last
Route::get('/login', function () {
    return view('welcome');
})->name('login');

Route::get('/downloads/email-merge-tags-guide.md', function () {
    $path = base_path('docs/EMAIL_MERGE_TAGS_AI_PROMPT_PACK.md');
    if (! is_file($path)) {
        abort(404);
    }

    return response()->download($path, 'CRM-Email-Merge-Tags-and-Placeholders.md', [
        'Content-Type' => 'text/markdown; charset=UTF-8',
    ]);
})->name('downloads.email-merge-tags-guide');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|build|storage|downloads).*$');
