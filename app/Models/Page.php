<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'text',
        'order',
        'seo_title',
        'seo_description',
        'seo_og_title',
        'seo_og_description',
        'seo_og_image',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function getRouteKeyName(): string
{
    return 'code';
}
    
}