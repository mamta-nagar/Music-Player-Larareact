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
    if (!$this->file_path) return null;
    
    // Direct URL construction - works 100% of the time
    $bucket = env('AWS_BUCKET');
    $region = env('AWS_DEFAULT_REGION', 'us-east-1');
    $path = ltrim($this->file_path, '/');
    
    return "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";
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
