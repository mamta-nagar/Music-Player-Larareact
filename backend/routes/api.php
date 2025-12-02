<?php

// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SongController;
use App\Http\Controllers\PlaylistController;
use Illuminate\Support\Facades\Storage;
use App\Models\Song;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API working successfully 🎉',
        'status' => true
    ]);
});

Route::get('/fix-all-songs', function() {
    $songs = Song::all();
    $fixed = [];
    
    foreach ($songs as $song) {
        try {
            // Set file to public
            if ($song->file_path && Storage::disk('s3')->exists($song->file_path)) {
                Storage::disk('s3')->setVisibility($song->file_path, 'public');
                $fixed[] = $song->title . ' - audio fixed';
            }
            
            // Set cover to public
            if ($song->cover_image && Storage::disk('s3')->exists($song->cover_image)) {
                Storage::disk('s3')->setVisibility($song->cover_image, 'public');
                $fixed[] = $song->title . ' - cover fixed';
            }
        } catch (\Exception $e) {
            $fixed[] = $song->title . ' - ERROR: ' . $e->getMessage();
        }
    }
    
    return response()->json([
        'message' => 'Fixed permissions',
        'results' => $fixed
    ]);
});

// ================================
// SONG ROUTES (Fixed Order)
// ================================
// IMPORTANT: More specific routes MUST come BEFORE dynamic {id} routes

Route::get('/songs', [SongController::class, 'index']);
Route::post('/songs', [SongController::class, 'store']);

// These specific routes MUST be before /songs/{id}
Route::get('/songs/{id}/signed-url', [SongController::class, 'getSignedUrl']);
Route::get('/songs/{id}/stream', [SongController::class, 'streamAudio']);

// Generic {id} routes come LAST
Route::get('/songs/{id}', [SongController::class, 'show']);
Route::delete('/songs/{id}', [SongController::class, 'destroy']);

// ================================
// PLAYLIST ROUTES
// ================================
Route::get('/playlists', [PlaylistController::class, 'index']);
Route::post('/playlists', [PlaylistController::class, 'store']);


Route::post('/test-upload', function(Request $request) {
    \Log::info('Request data:', $request->all());
    \Log::info('Files:', $request->allFiles());
    
    if (!$request->hasFile('file_path')) {
        return response()->json(['error' => 'No file received'], 400);
    }
    
    $file = $request->file('file_path');
    \Log::info('File info:', [
        'name' => $file->getClientOriginalName(),
        'size' => $file->getSize(),
        'valid' => $file->isValid(),
        'error' => $file->getError()
    ]);
    
    return response()->json(['message' => 'File received OK']);
});
Route::get('/test-s3-permissions', function() {
    try {
        // Test write
        $result = Storage::disk('s3')->put('test-permission.txt', 'Testing permissions');
        
        // Test read
        $exists = Storage::disk('s3')->exists('test-permission.txt');
        
        // Cleanup
        Storage::disk('s3')->delete('test-permission.txt');
        
        return response()->json([
            'write' => $result ? 'SUCCESS ✅' : 'FAILED ❌',
            'read' => $exists ? 'SUCCESS ✅' : 'FAILED ❌',
            'message' => 'IAM permissions are working!'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'hint' => 'Add s3:PutObject permission to your IAM user'
        ], 500);
    }
});