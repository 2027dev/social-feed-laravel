<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Redis;

class LikeRepository
{
    public function toggle(int $userId, int $postId): bool
    {
        $key = "post:{$postId}:likes";

        if (Redis::sismember($key, $userId)) {
            Redis::srem($key, $userId);

            return false;
        }

        Redis::sadd($key, $userId);

        return true;
    }

    public function count(int $postId): int
    {
        return (int) Redis::scard("post:{$postId}:likes");
    }

    public function isLikedBy(int $userId, int $postId): bool
    {
        return (bool) Redis::sismember("post:{$postId}:likes", $userId);
    }

    /**
     * @param  array<int, int>  $postIds
     * @return array<int, int>
     */
    public function likedPostIds(int $userId, array $postIds): array
    {
        return array_values(array_filter($postIds, fn (int $id) => $this->isLikedBy($userId, $id)));
    }
}
