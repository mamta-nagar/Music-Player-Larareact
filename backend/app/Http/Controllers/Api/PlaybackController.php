<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlaybackSession;
use App\Events\PlaybackStateChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlaybackController extends Controller
{
    // Get or create session
    public function getSession(Request $request)
    {
        $sessionId = $request->input('session_id') ?? Str::uuid();
        
        $session = PlaybackSession::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => 1, // Replace with auth user
                'is_playing' => false,
                'current_time' => 0,
            ]
        );

        return response()->json([
            'session_id' => $session->session_id,
            'playback_state' => [
                'current_song_id' => $session->current_song_id,
                'current_time' => $session->current_time,
                'is_playing' => $session->is_playing,
                'volume' => $session->volume,
                'active_device_id' => $session->active_device_id,
            ],
        ]);
    }

    // Update playback state
    public function updateState(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'current_song_id' => 'nullable|integer',
            'current_time' => 'nullable|numeric',
            'is_playing' => 'nullable|boolean',
            'volume' => 'nullable|numeric|min:0|max:1',
            'device_id' => 'required|string',
        ]);

        $session = PlaybackSession::where('session_id', $validated['session_id'])->first();

        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        // Update session
        if (isset($validated['current_song_id'])) {
            $session->current_song_id = $validated['current_song_id'];
        }
        if (isset($validated['current_time'])) {
            $session->current_time = $validated['current_time'];
        }
        if (isset($validated['is_playing'])) {
            $session->is_playing = $validated['is_playing'];
        }
        if (isset($validated['volume'])) {
            $session->volume = $validated['volume'];
        }
        
        $session->active_device_id = $validated['device_id'];
        $session->save();

        // Broadcast to all devices
        broadcast(new PlaybackStateChanged($session->session_id, [
            'current_song_id' => $session->current_song_id,
            'current_time' => $session->current_time,
            'is_playing' => $session->is_playing,
            'volume' => $session->volume,
            'active_device_id' => $session->active_device_id,
            'updated_by' => $validated['device_id'],
        ]));

        return response()->json([
            'success' => true,
            'playback_state' => [
                'current_song_id' => $session->current_song_id,
                'current_time' => $session->current_time,
                'is_playing' => $session->is_playing,
                'volume' => $session->volume,
            ],
        ]);
    }

    // Register device
    public function registerDevice(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'device_id' => 'required|string',
            'device_name' => 'required|string',
            'device_type' => 'required|string', // web, mobile, desktop
        ]);

        $session = PlaybackSession::where('session_id', $validated['session_id'])->first();
        
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $devices = $session->connected_devices ?? [];
        $devices[$validated['device_id']] = [
            'name' => $validated['device_name'],
            'type' => $validated['device_type'],
            'last_seen' => now()->toIso8601String(),
        ];
        
        $session->connected_devices = $devices;
        $session->save();

        return response()->json(['success' => true, 'devices' => $devices]);
    }

    // Get connected devices
    public function getDevices(Request $request)
    {
        $sessionId = $request->input('session_id');
        $session = PlaybackSession::where('session_id', $sessionId)->first();
        
        return response()->json([
            'devices' => $session->connected_devices ?? [],
            'active_device_id' => $session->active_device_id,
        ]);
    }
}