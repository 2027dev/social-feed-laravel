<?php

namespace App\Livewire;

use App\Auth\RedisUserProvider;
use App\Repositories\LikeRepository;
use App\Repositories\PostRepository;
use Illuminate\Support\Carbon;
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

    public function share(): void
    {
        $this->validate();

        $disk = config('filesystems.default');
        $extension = $this->image->getClientOriginalExtension() ?: 'jpg';
        $path = 'posts/'.Str::uuid().'.'.$extension;

        Storage::disk($disk)->put($path, file_get_contents($this->image->getRealPath()), 'public');

        app(PostRepository::class)->create(Auth::id(), $this->body, $path, $disk);

        $this->reset(['body', 'image']);
        $this->loaded = max($this->loaded, $this->perPage);
    }

    public function loadMore(): void
    {
        $this->loaded += $this->perPage;
    }

    public function toggleLike(int $postId): void
    {
        app(LikeRepository::class)->toggle(Auth::id(), $postId);
    }

    public function deletePost(int $postId): void
    {
        $posts = app(PostRepository::class);
        $post = $posts->find($postId);

        abort_if($post === null, 404);
        abort_unless((int) $post['user_id'] === Auth::id(), 403);

        $posts->delete($postId);
    }

    public function render()
    {
        $postRepository = app(PostRepository::class);
        $likeRepository = app(LikeRepository::class);
        $users = app(RedisUserProvider::class);

        [$feed, $hasMore] = $postRepository->paginate($this->loaded);

        $userNames = [];
        foreach ($feed as $post) {
            $userId = (int) $post['user_id'];
            $userNames[$userId] ??= $users->retrieveById($userId)?->name ?? 'Unknown';
        }

        $postIds = array_map(fn ($post) => (int) $post['id'], $feed);
        $likedPostIds = $likeRepository->likedPostIds(Auth::id(), $postIds);

        $posts = array_map(fn ($post) => [
            'id' => (int) $post['id'],
            'user_id' => (int) $post['user_id'],
            'user_name' => $userNames[(int) $post['user_id']],
            'body' => $post['body'],
            'image_url' => Storage::disk($post['image_disk'])->url($post['image_path']),
            'created_at' => Carbon::createFromTimestamp((int) $post['created_at']),
            'likes_count' => $likeRepository->count((int) $post['id']),
        ], $feed);

        return view('livewire.feed', [
            'posts' => $posts,
            'hasMore' => $hasMore,
            'likedPostIds' => $likedPostIds,
        ]);
    }
}
