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

    // ================================
    // 2. Upload Song to S3 (React FIXED)
    // ================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'artist'       => 'required|string|max:255',
            'description'  => 'nullable|string',

            // 🔥 FIX: React sends "file_path", NOT "file"
            'file_path'    => 'required|file|mimes:mp3,wav,m4a,flac|max:51200',

            'cover_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // -----------------------------------------------------
            // Upload AUDIO file
            // -----------------------------------------------------
            $audioFile = $request->file('file_path'); // 🔥 FIXED HERE
            $audioFileName = Str::uuid() . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $audioPath = 'songs/' . $audioFileName;

            Storage::disk('s3')->put(
                $audioPath,
                file_get_contents($audioFile),
                'public'
            );

            // -----------------------------------------------------
            // Upload COVER IMAGE if provided
            // -----------------------------------------------------
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

            // File meta
            $fileSize = $audioFile->getSize();
            $duration = $this->getAudioDuration($audioFile->getRealPath());

            // -----------------------------------------------------
            // Save in DB
            // -----------------------------------------------------
            $song = Song::create([
                'title'       => $request->title,
                'artist'      => $request->artist,
                'description' => $request->description,
                'file_path'   => $audioPath,
                'cover_image' => $coverPath,
                'duration'    => $duration,
                'file_size'   => $fileSize,
            ]);

            return response()->json([
                'id'          => $song->id,
                'title'       => $song->title,
                'artist'      => $song->artist,
                'file_path'   => $song->file_url,
                'cover_image' => $song->cover_url,
                'message'     => 'Song uploaded successfully ✔',
            ], 201);

        } catch (\Exception $e) {
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

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type'   => 'audio/mpeg',
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
