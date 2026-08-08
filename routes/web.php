<?php

use App\Http\Controllers\LineController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/', [LineController::class, 'index'])->name('lines.index');
    Route::get('/search', [LineController::class, 'search'])->name('lines.search');
});
