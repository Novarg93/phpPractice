<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaProfile extends Model
{
    protected $fillable = [
        'key','title','pricing_mode',
        'ga1_cents','ga2_cents','ga3_cents','ga4_cents',
        'ga1_percent','ga2_percent','ga3_percent','ga4_percent',
    ];

    protected $casts = [
        'ga1_cents' => 'int','ga2_cents' => 'int','ga3_cents' => 'int','ga4_cents' => 'int',
        'ga1_percent' => 'float','ga2_percent' => 'float','ga3_percent' => 'float','ga4_percent' => 'float',
    ];
}