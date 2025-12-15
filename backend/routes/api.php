<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\PlaylistController;
use Illuminate\Support\Facades\Storage;
use App\Models\Song;

// ================================
// PUBLIC ROUTES (No Authentication Required)
// ================================

Route::get('/test', function () {
    return response()->json([
        'message' => 'API working successfully 🎉',
        'status' => true
    ]);
});

// Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ================================
// PROTECTED ROUTES (Authentication Required)
// ================================

Route::middleware('auth:api')->group(function () {
    
    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // ================================
    // SONG ROUTES
    // ================================
    Route::get('/songs', [SongController::class, 'index']);
    Route::post('/songs', [SongController::class, 'store']);
    
    // Specific routes MUST come before generic {id} routes
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
    
    // ================================
    // UTILITY/DEBUG ROUTES (Keep protected)
    // ================================
    
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
    
    Route::get('/test-song-url/{id}', function($id) {
        $song = Song::findOrFail($id);
        
        return response()->json([
            'song_id' => $song->id,
            'title' => $song->title,
            'file_path' => $song->file_path,
            'url_valid' => filter_var($song->file_path, FILTER_VALIDATE_URL) ? 'YES' : 'NO',
            'test_fetch' => 'Try opening the URL in a new browser tab',
        ]);
    });
    
    Route::get('/fix-song-urls', function() {
        $songs = Song::all();
        $fixed = [];
        
        foreach ($songs as $song) {
            $originalUrl = $song->file_path;
            
            // Remove backslashes
            $cleanUrl = str_replace('\\/', '/', $originalUrl);
            
            if ($originalUrl !== $cleanUrl) {
                $song->file_path = $cleanUrl;
                
                // Also fix cover_image if it exists
                if ($song->cover_image) {
                    $song->cover_image = str_replace('\\/', '/', $song->cover_image);
                }
                
                $song->save();
                $fixed[] = $song->title . ' - URL fixed';
            }
        }
        
        return response()->json([
            'message' => 'Fixed all song URLs',
            'fixed_count' => count($fixed),
            'details' => $fixed
        ]);
    });
});

// ================================
// DEBUG/TEST ROUTES (Public for testing - Remove in production)
// ================================

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

Route::get('/verify-s3-setup', function() {
    $config = config('filesystems.disks.s3');
    
    $uploadTest = function() {
        try {
            $result = Storage::disk('s3')->put('test.txt', 'hello');
            Storage::disk('s3')->delete('test.txt');
            return $result ? 'SUCCESS ✅' : 'FAILED ❌';
        } catch (\Exception $e) {
            return 'ERROR: ' . $e->getMessage();
        }
    };
    
    return response()->json([
        'bucket' => $config['bucket'],
        'region' => $config['region'],
        'has_visibility' => isset($config['visibility']) ? $config['visibility'] : 'Not set (Good!)',
        'has_acl_options' => isset($config['options']['ACL']) ? 'YES (Remove this!)' : 'No (Good!)',
        'throw_enabled' => $config['throw'] ?? false,
        'upload_test' => $uploadTest()
    ]);
});

Route::get('/check-acl-status', function() {
    try {
        // Try to set visibility
        Storage::disk('s3')->put('test-acl.txt', 'testing');
        Storage::disk('s3')->setVisibility('test-acl.txt', 'public');
        Storage::disk('s3')->delete('test-acl.txt');
        
        return response()->json(['acl_status' => 'ENABLED ✅']);
    } catch (\Exception $e) {
        return response()->json([
            'acl_status' => 'DISABLED ❌',
            'error' => $e->getMessage()
        ]);
    }
});

use App\Http\Controllers\Api\PlaybackController;

Route::prefix('playback')->group(function () {
    Route::get('/session', [PlaybackController::class, 'getSession']);
    Route::post('/update', [PlaybackController::class, 'updateState']);
    Route::post('/device/register', [PlaybackController::class, 'registerDevice']);
    Route::get('/devices', [PlaybackController::class, 'getDevices']);
});