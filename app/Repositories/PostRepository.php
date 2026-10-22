<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class PostRepository
{
    public function create(int $userId, string $body, string $imagePath, string $imageDisk): array
    {
        $id = Redis::incr('posts:next_id');

        $attributes = [
            'id' => $id,
            'user_id' => $userId,
            'body' => $body,
            'image_path' => $imagePath,
            'image_disk' => $imageDisk,
            'created_at' => now()->timestamp,
        ];

        Redis::hmset("post:{$id}", $attributes);
        Redis::zadd('posts:feed', $id, $id);

        return $attributes;
    }

    public function find(int $id): ?array
    {
        $data = Redis::hgetall("post:{$id}");

        return $data === [] ? null : $data;
    }

    /**
     * @return array{0: array<int, array>, 1: bool}
     */
    public function paginate(int $limit): array
    {
        $ids = Redis::zrevrange('posts:feed', 0, $limit);
        $hasMore = count($ids) > $limit;
        $ids = array_slice($ids, 0, $limit);

        $posts = array_values(array_filter(array_map(
            fn ($id) => $this->find((int) $id),
            $ids
        )));

        return [$posts, $hasMore];
    }

    public function delete(int $id): void
    {
        $post = $this->find($id);

        if ($post === null) {
            return;
        }

        Storage::disk($post['image_disk'])->delete($post['image_path']);

        Redis::del("post:{$id}");
        Redis::del("post:{$id}:likes");
        Redis::zrem('posts:feed', $id);
    }
}
