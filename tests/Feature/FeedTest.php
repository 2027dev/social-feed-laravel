<?php

namespace Tests\Feature;

use App\Auth\RedisUserProvider;
use App\Livewire\Feed;
use App\Repositories\PostRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ResetsRedis;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use ResetsRedis;

    public function test_a_user_can_share_a_post_with_an_image_stored_on_disk(): void
    {
        Storage::fake('public');

        $user = app(RedisUserProvider::class)->create('Test User', 'test@example.com', 'password');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('body', 'Hello world')
            ->set('image', UploadedFile::fake()->image('photo.jpg'))
            ->call('share');

        $posts = app(PostRepository::class)->paginate(10)[0];

        $this->assertCount(1, $posts);
        $this->assertSame('Hello world', $posts[0]['body']);
        $this->assertSame((string) $user->id, (string) $posts[0]['user_id']);

        Storage::disk('public')->assertExists($posts[0]['image_path']);
    }

    public function test_a_user_can_like_and_unlike_a_post(): void
    {
        $user = app(RedisUserProvider::class)->create('Test User', 'test@example.com', 'password');
        $post = app(PostRepository::class)->create($user->id, 'Hello', 'posts/fake.jpg', 'public');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->call('toggleLike', $post['id']);

        $this->assertTrue(app(\App\Repositories\LikeRepository::class)->isLikedBy($user->id, $post['id']));

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->call('toggleLike', $post['id']);

        $this->assertFalse(app(\App\Repositories\LikeRepository::class)->isLikedBy($user->id, $post['id']));
    }

    public function test_a_user_can_delete_their_own_post_and_the_image_is_removed(): void
    {
        Storage::fake('public');

        $user = app(RedisUserProvider::class)->create('Test User', 'test@example.com', 'password');

        $path = 'posts/to-delete.jpg';
        Storage::disk('public')->put($path, 'fake-image-contents');

        $posts = app(PostRepository::class);
        $post = $posts->create($user->id, 'Hello', $path, 'public');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->call('deletePost', $post['id']);

        $this->assertNull($posts->find($post['id']));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_user_cannot_delete_another_users_post(): void
    {
        $owner = app(RedisUserProvider::class)->create('Owner', 'owner@example.com', 'password');
        $other = app(RedisUserProvider::class)->create('Other', 'other@example.com', 'password');

        $posts = app(PostRepository::class);
        $post = $posts->create($owner->id, 'Hello', 'posts/fake.jpg', 'public');

        Livewire::actingAs($other)
            ->test(Feed::class)
            ->call('deletePost', $post['id'])
            ->assertForbidden();

        $this->assertNotNull($posts->find($post['id']));
    }

    public function test_feed_shows_newest_post_first_and_paginates(): void
    {
        $user = app(RedisUserProvider::class)->create('Test User', 'test@example.com', 'password');

        $posts = app(PostRepository::class);
        $older = $posts->create($user->id, 'Older', 'posts/a.jpg', 'public');
        $newer = $posts->create($user->id, 'Newer', 'posts/b.jpg', 'public');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->assertSeeInOrder([$newer['id'], $older['id']]);
    }
}
