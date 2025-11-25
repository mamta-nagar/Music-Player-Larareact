<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SongController extends Controller
{
    // ✅ 1. Get all songs
    public function index()
    {
       $songs = Song::latest()->get()->map(function ($song) {
            return [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist,
                'description' => $song->description,
                'file_path' => $song->file_url,
                'cover_image' => $song->cover_url,
                'duration' => $song->duration,
                'file_size' => $song->file_size,
            ];
        });
        return response()->json($songs);
    }

    // // ✅ 2. Store (upload) a new song
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'title' => 'required|string',
    //         'artist' => 'required|string',
    //         'file' => 'required|mimes:mp3,wav|max:10240', // 10MB max
    //     ]);

    //     // store file in local storage for now
    //     $path = $request->file('file')->store('songs', 'public');

    //     // create DB entry
    //     $song = Song::create([
    //         'title' => $request->title,
    //         'artist' => $request->artist,
    //         'description' => $request->description,
    //         'file_path' => url('storage/' . $path), // creates a public URL
            
    //     ]);
       
    //     return response()->json($song, 201);
    // }

    // // ✅ 3. Get one song
    // public function show($id)
    // {
    //     $song = Song::findOrFail($id);
    //     return response()->json($song);
    // }

    // // ✅ 4. Delete song
    // public function destroy($id)
    // {
    //     $song = Song::findOrFail($id);
    //     Storage::disk('public')->delete(str_replace('/storage/', '', $song->file_path));
    //     $song->delete();

    //     return response()->json(['message' => 'Song deleted successfully']);
    // }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'artist' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:mp3,wav,m4a,flac|max:51200', // 50MB max
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Upload audio file to S3
            $audioFile = $request->file('file');
            $audioFileName = Str::uuid() . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $audioPath = 'songs/' . $audioFileName;
            
            // Store in S3 with public visibility
            Storage::disk('s3')->put(
                $audioPath, 
                file_get_contents($audioFile), 
                'public'
            );

            // Upload cover image if provided
            $coverPath = null;
            if ($request->hasFile('cover_image')) {
                $coverFile = $request->file('cover_image');
                $coverFileName = Str::uuid() . '_' . time() . '.' . $coverFile->getClientOriginalExtension();
                $coverPath = 'covers/' . $coverFileName;
                
                Storage::disk('s3')->put(
                    $coverPath, 
                    file_get_contents($coverFile), 
                    'public'
                );
            }

            // Get file size
            $fileSize = $audioFile->getSize();

            // Get duration (optional - requires getID3)
            $duration = $this->getAudioDuration($audioFile->getRealPath());

            $song = Song::create([
                'title' => $request->title,
                'artist' => $request->artist,
                'description' => $request->description,
                'file_path' => $audioPath,
                'cover_image' => $coverPath,
                'duration' => $duration,
                'file_size' => $fileSize,
            ]);

            return response()->json([
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist,
                'file_path' => $song->file_url,
                'cover_image' => $song->cover_url,
                'message' => 'Song uploaded successfully to S3',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $song = Song::findOrFail($id);
        
        return response()->json([
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist,
            'description' => $song->description,
            'file_path' => $song->file_url,
            'cover_image' => $song->cover_url,
            'duration' => $song->duration,
            'file_size' => $song->file_size,
        ]);
    }

    public function destroy($id)
    {
        $song = Song::findOrFail($id);
        
        try {
            // Delete audio file from S3
            if ($song->file_path && Storage::disk('s3')->exists($song->file_path)) {
                Storage::disk('s3')->delete($song->file_path);
            }

            // Delete cover image from S3
            if ($song->cover_image && Storage::disk('s3')->exists($song->cover_image)) {
                Storage::disk('s3')->delete($song->cover_image);
            }

            $song->delete();

            return response()->json(['message' => 'Song deleted successfully from S3']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSignedUrl($id)
    {
        // For private files, generate a temporary signed URL
        $song = Song::findOrFail($id);
        
        $url = Storage::disk('s3')->temporaryUrl(
            $song->file_path,
            now()->addMinutes(60) // URL valid for 60 minutes
        );

        return response()->json(['url' => $url]);
    }

    private function getAudioDuration($filePath)
    {
        try {
            if (class_exists('\getID3')) {
                $getID3 = new \getID3;
                $fileInfo = $getID3->analyze($filePath);
                return isset($fileInfo['playtime_seconds']) 
                    ? round($fileInfo['playtime_seconds']) 
                    : null;
            }
        } catch (\Exception $e) {
            \Log::error('Duration extraction failed: ' . $e->getMessage());
        }
        
        return null;
    }

    public function streamAudio($id)
    {
        // Stream audio directly from S3
        $song = Song::findOrFail($id);
        
        $stream = Storage::disk('s3')->readStream($song->file_path);
        
        return response()->stream(function() use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => Storage::disk('s3')->size($song->file_path),
            'Accept-Ranges' => 'bytes',
        ]);
    }
}

