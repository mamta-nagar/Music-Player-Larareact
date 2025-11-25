<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/aws-test', function () {
    return Storage::disk('s3')->exists('/') ? '✅ Connected to AWS!' : '❌ Not connected';
});
