<?php

use Illuminate\Support\Facades\Route;



Route::middleware(['web'])->group(function () {
//    Route::livewire();
    Route::livewire('/login', 'login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::livewire('/', 'pages::home')->name('home');
    });
});
