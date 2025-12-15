<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaybackSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'current_song_id',
        'current_time',
        'is_playing',
        'volume',
        'active_device_id',
        'connected_devices',
        'last_sync_at',
    ];

    protected $casts = [
        'current_time' => 'float',
        'is_playing' => 'boolean',
        'volume' => 'float',
        'connected_devices' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function currentSong()
    {
        return $this->belongsTo(Song::class, 'current_song_id');
    }
}