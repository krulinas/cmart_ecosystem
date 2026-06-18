<?php

namespace App\Services;

use App\Models\NewsPost;

class NewsPostPresenter
{
    public static function fromModel(NewsPost $post, bool $withAuthor = false): array
    {
        $post->loadMissing('images');

        if ($withAuthor) {
            $post->loadMissing('author');
        }

        $images = $post->galleryImagesForApi();
        $primary = collect($images)->firstWhere('is_primary', true) ?? ($images[0] ?? null);
        $displayUrl = $primary['image_url'] ?? null;
        $externalUrl = $post->getRawOriginal('image_url');

        $payload = array_merge($post->only([
            'id',
            'title',
            'excerpt',
            'body',
            'category',
            'image_path',
            'published_at',
            'is_published',
            'author_id',
            'created_at',
            'updated_at',
        ]), [
            'image_url' => $displayUrl ?? $externalUrl,
            'external_image_url' => $externalUrl,
            'banner_url' => $displayUrl ?? $externalUrl,
            'images' => $images,
        ]);

        if ($withAuthor && $post->relationLoaded('author')) {
            $payload['author'] = $post->author;
        }

        return $payload;
    }
}
