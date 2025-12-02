<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SongController extends Controller
{
    // ================================
    // 1. Get All Songs
    // ================================
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

public function store(Request $request) 
{
    $validator = Validator::make($request->all(), [
        'title'        => 'required|string|max:255',
        'artist'       => 'required|string|max:255',
        'description'  => 'nullable|string',
        'file_path'    => 'required|file|mimes:mp3,wav,m4a,flac|max:51200',
        'cover_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        // -----------------------------------------------------
        // 1. Upload AUDIO file to S3
        // -----------------------------------------------------
        $audioFile = $request->file('file_path');
        
        // Debug: Check file validity
        if (!$audioFile || !$audioFile->isValid()) {
            \Log::error('Invalid audio file received');
            return response()->json(['error' => 'Invalid file uploaded'], 400);
        }
        
        \Log::info('File details:', [
            'original_name' => $audioFile->getClientOriginalName(),
            'size' => $audioFile->getSize(),
            'mime' => $audioFile->getMimeType(),
            'path' => $audioFile->getRealPath()
        ]);
        
        $audioFileName = Str::uuid() . '_' . time() . '.' . $audioFile->getClientOriginalExtension();

        // Upload to S3 with detailed error logging
        try {
            \Log::info('Attempting S3 upload', [
                'filename' => $audioFileName,
                'disk' => 's3',
                'bucket' => config('filesystems.disks.s3.bucket')
            ]);

            // Try without 'public' visibility first
            $audioPath = Storage::disk('s3')->putFileAs(
                'songs',
                $audioFile,
                $audioFileName
            );

            \Log::info('S3 upload result: ' . ($audioPath ?: 'FAILED'));

            if (!$audioPath) {
                throw new \Exception('S3 putFileAs returned false/null');
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            \Log::error('AWS S3 Error: ' . $e->getMessage());
            \Log::error('AWS Error Code: ' . $e->getAwsErrorCode());
            return response()->json([
                'error' => 'S3 upload failed',
                'details' => $e->getMessage()
            ], 500);
        }

        // Get the full S3 URL
        $audioUrl = Storage::disk('s3')->url($audioPath);

        // -----------------------------------------------------
        // 2. Upload COVER IMAGE to S3 (if provided)
        // -----------------------------------------------------
        $coverUrl = null;

        if ($request->hasFile('cover_image')) {
            $coverFile = $request->file('cover_image');
            $coverFileName = Str::uuid() . '_' . time() . '.' . $coverFile->getClientOriginalExtension();

            $coverPath = Storage::disk('s3')->putFileAs(
                'covers',
                $coverFile,
                $coverFileName
            );

            if ($coverPath) {
                $coverUrl = Storage::disk('s3')->url($coverPath);
            }
        }

        // -----------------------------------------------------
        // 3. Save data in database
        // -----------------------------------------------------
        $song = Song::create([
            'title'       => $request->title,
            'artist'      => $request->artist,
            'description' => $request->description,
            'file_path'   => $audioUrl,    // Store full URL
            'cover_image' => $coverUrl,    // Store full URL
        ]);

        return response()->json([
            'message' => 'Song uploaded successfully!',
            'song' => $song
        ], 201);

    } catch (\Exception $e) {
        \Log::error('Song upload failed: ' . $e->getMessage());
        
        return response()->json([
            'error' => 'Upload failed: ' . $e->getMessage()
        ], 500);
    }
}
    // ================================
    // 3. Show Song
    // ================================
    public function show($id)
    {
        $song = Song::findOrFail($id);

        return response()->json([
            'id'          => $song->id,
            'title'       => $song->title,
            'artist'      => $song->artist,
            'description' => $song->description,
            'file_path'   => $song->file_url,
            'cover_image' => $song->cover_url,
            'duration'    => $song->duration,
            'file_size'   => $song->file_size,
        ]);
    }

    // ================================
    // 4. Delete Song + S3 Files
    // ================================
    public function destroy($id)
    {
        $song = Song::findOrFail($id);

        try {
            if ($song->file_path && Storage::disk('s3')->exists($song->file_path)) {
                Storage::disk('s3')->delete($song->file_path);
            }

            if ($song->cover_image && Storage::disk('s3')->exists($song->cover_image)) {
                Storage::disk('s3')->delete($song->cover_image);
            }

            $song->delete();

            return response()->json(['message' => 'Deleted successfully ✔']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // 5. Generate Signed URL
    // ================================
    public function getSignedUrl($id)
    {
        $song = Song::findOrFail($id);

        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl(
                $song->file_path,
                now()->addMinutes(60)
            )
        ]);
    }

    // ================================
    // 6. Stream from S3
    // ================================
    public function streamAudio($id)
    {
        $song = Song::findOrFail($id);

        $stream = Storage::disk('s3')->readStream($song->file_path);
        
        $mime = Storage::disk('s3')->mimeType($song->file_path);
        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => Storage::disk('s3')->size($song->file_path),
            'Accept-Ranges'  => 'bytes',
        ]);
    }

    // ================================
    // Utility: get duration
    // ================================
    private function getAudioDuration($filePath)
    {
        try {
            if (class_exists('\getID3')) {
                $getID3 = new \getID3;
                $info = $getID3->analyze($filePath);

                return $info['playtime_seconds'] ?? null;
            }
        } catch (\Exception $e) {
            \Log::error('Duration check failed: ' . $e->getMessage());
        }

        return null;
    }
}

