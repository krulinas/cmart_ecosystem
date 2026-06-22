<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Services\NewsPostPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsPostController extends Controller
{
    private const MAX_IMAGES = 5;

    public function publicIndex()
    {
        $posts = NewsPost::query()
            ->with('images')
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (NewsPost $post) => NewsPostPresenter::fromModel($post))
            ->values();

        return response()->json($posts);
    }

    public function index()
    {
        $posts = NewsPost::query()
            ->with(['images', 'author'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (NewsPost $post) => NewsPostPresenter::fromModel($post, true))
            ->values();

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);

        $post = NewsPost::create(array_merge($validated, [
            'author_id' => $request->user()->id,
            'published_at' => $validated['published_at'] ?? now(),
        ]));

        $this->attachUploadedImages($request, $post);

        return response()->json([
            'message' => '201 Created: News post created successfully.',
            'post' => NewsPostPresenter::fromModel($post->fresh(['images', 'author']), true),
        ], 201);
    }

    public function show(NewsPost $news_post)
    {
        $news_post->load(['images', 'author']);

        return response()->json(NewsPostPresenter::fromModel($news_post, true));
    }

    public function update(Request $request, NewsPost $news_post)
    {
        $validated = $this->validatePost($request, true);

        if ($request->boolean('remove_banner')) {
            $this->removeAllImages($news_post);
            $validated['image_path'] = null;
        }

        $news_post->update($validated);

        if ($request->filled('remove_image_ids')) {
            $this->removeImagesById($news_post, (array) $request->input('remove_image_ids'));
        }

        $this->attachUploadedImages($request, $news_post);

        return response()->json([
            'message' => '200 OK: News post updated successfully.',
            'post' => NewsPostPresenter::fromModel($news_post->fresh(['images', 'author']), true),
        ]);
    }

    public function destroy(NewsPost $news_post)
    {
        $news_post->delete();

        return response()->json([
            'message' => '200 OK: News post deleted successfully.',
        ]);
    }

    private function validatePost(Request $request, bool $partial = false): array
    {
        $validated = $request->validate([
            'title' => ($partial ? 'sometimes|' : '') . 'required|string|max:255',
            'excerpt' => ($partial ? 'sometimes|' : '') . 'required|string|max:500',
            'body' => 'nullable|string|max:10000',
            'category' => ($partial ? 'sometimes|' : '') . 'required|string|max:100',
            'image_url' => 'nullable|url|max:2000',
            'published_at' => 'nullable|date',
            'is_published' => 'sometimes|boolean',
            'banner' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:' . self::MAX_IMAGES,
            'images.*' => 'file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_banner' => 'nullable|boolean',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer',
        ]);

        unset(
            $validated['banner'],
            $validated['images'],
            $validated['remove_banner'],
            $validated['remove_image_ids'],
        );

        return $validated;
    }

    private function collectUploadFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('banner')) {
            $files[] = $request->file('banner');
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function attachUploadedImages(Request $request, NewsPost $post): void
    {
        $files = $this->collectUploadFiles($request);
        if ($files === []) {
            return;
        }

        $existingCount = $post->images()->count();
        $availableSlots = self::MAX_IMAGES - $existingCount;

        if ($availableSlots <= 0) {
            return;
        }

        $hasPrimary = $post->images()->where('is_primary', true)->exists();

        foreach (array_slice($files, 0, $availableSlots) as $offset => $file) {
            $path = $file->store('news', 'public');

            $post->images()->create([
                'image_path' => $path,
                'sort_order' => $existingCount + $offset,
                'is_primary' => !$hasPrimary && $offset === 0,
            ]);

            if ($offset === 0 && !$hasPrimary) {
                $hasPrimary = true;
            }
        }

        $this->syncPrimaryImagePath($post->fresh('images'));
    }

    private function removeImagesById(NewsPost $post, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $images = $post->images()->whereIn('id', $ids)->get();
        foreach ($images as $image) {
            $image->delete();
        }

        $this->reassignPrimaryIfNeeded($post);
        $this->syncPrimaryImagePath($post->fresh('images'));
    }

    private function removeAllImages(NewsPost $post): void
    {
        $post->load('images');

        foreach ($post->images as $image) {
            $image->delete();
        }

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->updateQuietly(['image_path' => null]);
    }

    private function reassignPrimaryIfNeeded(NewsPost $post): void
    {
        if ($post->images()->where('is_primary', true)->exists()) {
            return;
        }

        $first = $post->images()->orderBy('sort_order')->orderBy('id')->first();
        if ($first) {
            $first->update(['is_primary' => true]);
        }
    }

    private function syncPrimaryImagePath(NewsPost $post): void
    {
        $post->loadMissing('images');

        $primary = $post->images->firstWhere('is_primary', true)
            ?? $post->images->sortBy('sort_order')->first();

        $newPath = $primary?->image_path;

        if ($newPath === null) {
            return;
        }

        if ($newPath !== $post->image_path) {
            if ($post->image_path && $post->image_path !== $newPath) {
                Storage::disk('public')->delete($post->image_path);
            }

            $post->updateQuietly(['image_path' => $newPath]);
        }
    }
}
