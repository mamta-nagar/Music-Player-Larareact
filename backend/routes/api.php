<?php

// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SongController;
use App\Http\Controllers\PlaylistController;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API working successfully 🎉',
        'status' => true
    ]);
});

Route::get('/songs', [SongController::class, 'index']);
Route::post('/songs', [SongController::class, 'store']);
Route::get('/songs/{id}', [SongController::class, 'show']);
Route::delete('/songs/{id}', [SongController::class, 'destroy']);
Route::get('/{id}/signed-url', [SongController::class, 'getSignedUrl']);
Route::get('/{id}/stream', [SongController::class, 'streamAudio']);



Route::get('/playlists', [PlaylistController::class, 'index']);
Route::post('/playlists', [PlaylistController::class, 'store']);


