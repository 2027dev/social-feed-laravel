<?php

namespace Tests\Feature;

use App\Livewire\Feed;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_share_a_post_with_an_image_stored_on_disk(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('body', 'Hello world')
            ->set('image', UploadedFile::fake()->image('photo.jpg'))
            ->call('share');

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'body' => 'Hello world',
        ]);

        $post = Post::first();

        $this->assertNotNull($post->image_path);
        Storage::disk('public')->assertExists($post->image_path);
    }

    public function test_a_user_can_like_and_unlike_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'image_path' => 'posts/fake.jpg']);

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->call('toggleLike', $post->id);

        $this->assertDatabaseHas('likes', ['user_id' => $user->id, 'post_id' => $post->id]);

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->call('toggleLike', $post->id);

        $this->assertDatabaseMissing('likes', ['user_id' => $user->id, 'post_id' => $post->id]);
    }

    public function test_a_user_can_delete_their_own_post_and_the_image_is_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $path = 'posts/to-delete.jpg';
        Storage::disk('public')->put($path, 'fake-image-contents');

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'image_path' => $path,
            'image_disk' => 'public',
        ]);

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->call('deletePost', $post->id);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_user_cannot_delete_another_users_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $post = Post::factory()->create(['user_id' => $owner->id, 'image_path' => 'posts/fake.jpg']);

        Livewire::actingAs($other)
            ->test(Feed::class)
            ->call('deletePost', $post->id)
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_feed_shows_newest_post_first_and_paginates(): void
    {
        $user = User::factory()->create();

        $older = Post::factory()->create(['user_id' => $user->id, 'image_path' => 'posts/a.jpg', 'created_at' => now()->subMinute()]);
        $newer = Post::factory()->create(['user_id' => $user->id, 'image_path' => 'posts/b.jpg', 'created_at' => now()]);

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->assertSeeInOrder([$newer->id, $older->id]);
    }
}
