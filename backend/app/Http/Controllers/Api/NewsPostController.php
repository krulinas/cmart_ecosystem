<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsPostController extends Controller
{
    public function publicIndex()
    {
        $posts = NewsPost::query()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($posts);
    }

    public function index()
    {
        return response()->json(
            NewsPost::with('author')->orderByDesc('published_at')->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);
        $validated['image_path'] = $this->storeBanner($request);

        $post = NewsPost::create(array_merge($validated, [
            'author_id' => $request->user()->id,
            'published_at' => $validated['published_at'] ?? now(),
        ]));

        return response()->json([
            'message' => '201 Created: News post created successfully.',
            'post' => $post->load('author'),
        ], 201);
    }

    public function show(NewsPost $news_post)
    {
        return response()->json($news_post->load('author'));
    }

    public function update(Request $request, NewsPost $news_post)
    {
        $validated = $this->validatePost($request, true);
        $imagePath = $this->resolveBannerPath($request, $news_post);

        if ($request->hasFile('banner') || $request->boolean('remove_banner')) {
            $validated['image_path'] = $imagePath;
        }

        $news_post->update($validated);

        return response()->json([
            'message' => '200 OK: News post updated successfully.',
            'post' => $news_post->fresh('author'),
        ]);
    }

    public function destroy(NewsPost $news_post)
    {
        $this->deleteBannerFile($news_post->image_path);
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
            'remove_banner' => 'nullable|boolean',
        ]);

        unset($validated['banner'], $validated['remove_banner']);

        return $validated;
    }

    private function storeBanner(Request $request): ?string
    {
        if (!$request->hasFile('banner')) {
            return null;
        }

        return $request->file('banner')->store('news_banners', 'public');
    }

    private function resolveBannerPath(Request $request, NewsPost $post): ?string
    {
        if ($request->boolean('remove_banner')) {
            $this->deleteBannerFile($post->image_path);

            return null;
        }

        if ($request->hasFile('banner')) {
            $this->deleteBannerFile($post->image_path);

            return $request->file('banner')->store('news_banners', 'public');
        }

        return $post->image_path;
    }

    private function deleteBannerFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
