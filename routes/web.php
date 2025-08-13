<?php

use App\Http\Controllers\SummarizerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::post('/summarize', [SummarizerController::class, 'summarize']);
