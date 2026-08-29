<?php

use App\Http\Controllers\EmailOpenTrackingController;
use Illuminate\Support\Facades\Route;

// Public tracking pixel (signed URL); must be before SPA catch-all
Route::get('/email/track/open/{id}', [EmailOpenTrackingController::class, 'pixel'])
    ->name('email.track.open');

// Signed click redirect. Unsigned requests are refused so this cannot be used
// as an open redirect.
Route::get('/email/track/click/{id}', [EmailOpenTrackingController::class, 'click'])
    ->name('email.track.click');

// API routes are handled in routes/api.php
// All other routes should serve the Vue SPA
// This catch-all route must be last
Route::get('/login', function () {
    return view('welcome');
})->name('login');

// Named so Laravel's password-reset notification can resolve a URL. The SPA
// renders the form; the token is passed through as a query parameter.
Route::get('/reset-password', function () {
    return view('welcome');
})->name('password.reset');

Route::get('/downloads/email-merge-tags-guide.md', function () {
    $path = base_path('docs/EMAIL_MERGE_TAGS_AI_PROMPT_PACK.md');
    if (! is_file($path)) {
        abort(404);
    }

    return response()->download($path, 'CRM-Email-Merge-Tags-and-Placeholders.md', [
        'Content-Type' => 'text/markdown; charset=UTF-8',
    ]);
})->name('downloads.email-merge-tags-guide');

// SPA catch-all. Must exclude /admin (Filament panel) and /livewire
// (Livewire's update endpoint), or they render the Vue shell instead.
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|admin|livewire|build|storage|downloads|email/track).*$');
