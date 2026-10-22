<?php

namespace App\Livewire;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Feed extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:2000')]
    public string $body = '';

    #[Validate('required|image|max:8192')]
    public $image;

    public int $perPage = 10;

    public int $loaded = 10;

    public function mount(): void
    {
        //
    }

    public function share(): void
    {
        $this->validate();

        $disk = config('filesystems.default');

        $extension = $this->image->getClientOriginalExtension() ?: 'jpg';
        $path = 'posts/'.Str::uuid().'.'.$extension;

        Storage::disk($disk)->put($path, file_get_contents($this->image->getRealPath()), 'public');

        Auth::user()->posts()->create([
            'body' => $this->body,
            'image_path' => $path,
            'image_disk' => $disk,
        ]);

        $this->reset(['body', 'image']);

        $this->loaded = max($this->loaded, $this->perPage);
    }

    public function loadMore(): void
    {
        $this->loaded += $this->perPage;
    }

    public function toggleLike(int $postId): void
    {
        $userId = Auth::id();

        $existing = Like::where('user_id', $userId)->where('post_id', $postId)->first();

        if ($existing) {
            $existing->delete();
        } else {
            Like::create(['user_id' => $userId, 'post_id' => $postId]);
        }
    }

    public function deletePost(int $postId): void
    {
        $post = Post::findOrFail($postId);

        abort_unless($post->user_id === Auth::id(), 403);

        $post->delete();
    }

    public function render()
    {
        $posts = Post::query()
            ->with('user')
            ->withCount('likes')
            ->latest()
            ->take($this->loaded + 1)
            ->get();

        $hasMore = $posts->count() > $this->loaded;

        $posts = $posts->take($this->loaded);

        $userId = Auth::id();

        $likedPostIds = Like::where('user_id', $userId)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        return view('livewire.feed', [
            'posts' => $posts,
            'hasMore' => $hasMore,
            'likedPostIds' => $likedPostIds,
        ]);
    }
}
