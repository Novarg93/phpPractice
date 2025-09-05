<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\UniqueD4Synchronizer;


class GaProfile extends Model
{
    protected $fillable = [
        'key',
        'title',
        'pricing_mode',
        'ga1_cents',
        'ga2_cents',
        'ga3_cents',
        'ga4_cents',
        'ga1_percent',
        'ga2_percent',
        'ga3_percent',
        'ga4_percent',
    ];

    protected $casts = [
        'ga1_cents' => 'int',
        'ga2_cents' => 'int',
        'ga3_cents' => 'int',
        'ga4_cents' => 'int',
        'ga1_percent' => 'float',
        'ga2_percent' => 'float',
        'ga3_percent' => 'float',
        'ga4_percent' => 'float',
    ];

    protected static function booted(): void
    {
        static::saved(function (GaProfile $profile) {
            OptionGroup::query()
                ->where('type', OptionGroup::TYPE_UNIQUE_D4)
                ->where('unique_d4_is_global', true)
                ->where('ga_profile_id', $profile->id)
                ->select('id') // экономим память
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        // берём свежую модель, т.к. sync читает поля группы
                        if ($group = OptionGroup::find($row->id)) {
                            UniqueD4Synchronizer::sync($group);
                        }
                    }
                });
        });
    }

}
