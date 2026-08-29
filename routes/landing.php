<?php

use App\Http\Middleware\HandleLandingInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.home');
})->name('home');

Route::get('/about', function () {
    return view('landing.about');
})->name('about');

// with landing inertia middleware
Route::middleware(HandleLandingInertiaRequests::class)->group(function () {

    Route::get('/contact', function () {
        return inertia('landing/contact', [
            'syndicate' => config('syndicate'),
        ]);
    })->name('contact');
});
