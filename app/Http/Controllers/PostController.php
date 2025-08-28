<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function index(Request $request)
    {
        // show only published by default; tweak as needed
        $query = Post::query()
            ->when(true, fn ($q) => $q->where('status', 'published'))
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $posts = $query->paginate(10)->withQueryString();

        return Inertia::render('Posts/Index', [
            'posts' => $posts->through(function (Post $post) {
                return [
                    'id'           => $post->id,
                    'title'        => $post->title,
                    'slug'         => $post->slug,
                    'image_url'    => $this->publicImageUrl($post->cover_image),
                    'published_at' => optional($post->published_at)->toDateTimeString(),
                    // short preview for list
                    'excerpt'      => Str::limit(strip_tags((string) $post->content), 180),
                    'seo' => [
                        'title'        => $post->seo_title,
                        'description'  => $post->seo_description,
                        'og_title'     => $post->seo_og_title,
                        'og_description'=> $post->seo_og_description,
                        'og_image'      => $this->publicImageUrl($post->seo_og_image),
                    ],
                ];
            }),
        ]);
    }

    public function show(Post $post)
    {
        return Inertia::render('Posts/Show', [
            'post' => [
                'id'           => $post->id,
                'title'        => $post->title,
                'slug'         => $post->slug,
                'status'       => $post->status,
                'image_url'    => $this->publicImageUrl($post->cover_image),
                'content'      => (string) $post->content,
                'published_at' => optional($post->published_at)->toDateTimeString(),
                'seo' => [
                    'title'         => $post->seo_title,
                    'description'   => $post->seo_description,
                    'og_title'      => $post->seo_og_title,
                    'og_description'=> $post->seo_og_description,
                    'og_image'      => $post->seo_og_image,
                ],
            ],
        ]);
    }

    private function publicImageUrl(?string $path): ?string
        {
            if (empty($path)) {
                return null;
            }

            // Уже абсолютный URL или отданный из /storage — возвращаем как есть
            if (Str::startsWith($path, ['http://', 'https://', '/storage/'])) {
                return $path;
            }

            // Нормализуем путь (без ведущего слэша)
            $normalized = ltrim($path, '/');

            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            // Если файл есть на диске public — вернём корректный URL от диска
            if ($disk->exists($normalized)) {
                return $disk->url($normalized);
            }

            // Фолбэк через asset() — полезен для IDE и нестандартных драйверов
            return asset('storage/' . $normalized);
        }

}