<div wire:poll.5s="$refresh" class="max-w-xl mx-auto space-y-6">
    <div class="bg-white shadow-sm rounded-lg p-4">
        <form wire:submit="share" class="space-y-3">
            <textarea
                wire:model="body"
                rows="3"
                placeholder="What's happening?"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            ></textarea>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div>
                <input type="file" wire:model="image" accept="image/*" class="block w-full text-sm text-gray-600" />
                @error('image') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <div wire:loading wire:target="image" class="text-sm text-gray-400 mt-1">Uploading...</div>

                @if ($image)
                    <img src="{{ $image->temporaryUrl() }}" class="mt-2 max-h-48 rounded-md" />
                @endif
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="share"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 disabled:opacity-50"
            >
                Post
            </button>
        </form>
    </div>

    <div class="space-y-6" id="feed-posts">
        @forelse ($posts as $post)
            <div wire:key="post-{{ $post->id }}" class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="p-4 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $post->user->name }}</p>
                        <p class="text-xs text-gray-400">{{ $post->created_at->diffForHumans() }}</p>
                    </div>

                    @if ($post->user_id === auth()->id())
                        <button
                            wire:click="deletePost({{ $post->id }})"
                            wire:confirm="Delete this post?"
                            class="text-xs text-red-500 hover:text-red-700"
                        >
                            Delete
                        </button>
                    @endif
                </div>

                @if ($post->body)
                    <p class="px-4 pb-3 text-gray-800 whitespace-pre-line">{{ $post->body }}</p>
                @endif

                <img src="{{ $post->imageUrl() }}" class="w-full max-h-[32rem] object-cover" loading="lazy" />

                <div class="p-4 flex items-center gap-2">
                    <button
                        wire:click="toggleLike({{ $post->id }})"
                        wire:key="like-btn-{{ $post->id }}"
                        class="inline-flex items-center gap-1 text-sm font-medium {{ in_array($post->id, $likedPostIds) ? 'text-rose-600' : 'text-gray-500' }} hover:text-rose-600"
                    >
                        <span>{{ in_array($post->id, $likedPostIds) ? '♥' : '♡' }}</span>
                        <span>{{ in_array($post->id, $likedPostIds) ? 'Liked' : 'Like' }}</span>
                    </button>

                    <span class="text-sm text-gray-500">{{ $post->likes_count }} {{ \Illuminate\Support\Str::plural('like', $post->likes_count) }}</span>
                </div>
            </div>
        @empty
            <p class="text-center text-gray-400 py-12">No posts yet. Be the first to share something!</p>
        @endforelse
    </div>

    @if ($hasMore)
        <div x-intersect="$wire.loadMore()" class="py-6 text-center text-gray-400 text-sm">
            Loading more...
        </div>
    @endif
</div>
