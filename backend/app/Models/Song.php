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
    // If already a full URL, return as-is
    if (str_starts_with($this->file_path, 'http')) {
        return $this->file_path;
    }
    // Otherwise, build the URL
    return Storage::disk('s3')->url($this->file_path);
}

public function getCoverUrlAttribute()
{
    if (!$this->cover_image) return null;
    
    if (str_starts_with($this->cover_image, 'http')) {
        return $this->cover_image;
    }
    return Storage::disk('s3')->url($this->cover_image);
}
}
