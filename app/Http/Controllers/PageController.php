<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;

class PageController extends Controller
{
    // если используешь getRouteKeyName() → достаточно Page $page
    public function show(Page $page)
    {
        return Inertia::render('Pages/Show', [
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'code' => $page->code,
                'text' => $page->text,
                'seo' => [
                    'title' => $page->seo_title,
                    'description' => $page->seo_description,
                    'og_title' => $page->seo_og_title,
                    'og_description' => $page->seo_og_description,
                    'og_image' => $page->seo_og_image,
                ],
            ],
        ]);
    }
}