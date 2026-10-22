<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'body',
        'image_path',
        'image_disk',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function imageUrl(): string
    {
        return Storage::disk($this->image_disk)->url($this->image_path);
    }

    protected static function booted(): void
    {
        static::deleting(function (Post $post) {
            Storage::disk($post->image_disk)->delete($post->image_path);
        });
    }
}
