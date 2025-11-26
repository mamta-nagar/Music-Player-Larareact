<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;   // ✅ Added this

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'description',
        'file_path',
        'cover_image',
        'duration',
        'file_size'
    ];

    protected $appends = ['file_url', 'cover_url'];

    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk('s3')->url($this->file_path);
    }

    public function getCoverUrlAttribute()
    {
        if (!$this->cover_image) {
            return null;
        }

        return Storage::disk('s3')->url($this->cover_image);
    }

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class, 'playlist_song');
    }
}
