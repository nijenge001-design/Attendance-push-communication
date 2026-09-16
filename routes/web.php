<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/','pages::-welcome' )->name('home');
Route::livewire('/org-chart','org-chart' )->name('org-chart');

