<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionValue extends Model
{
    protected $fillable = [
        'option_group_id',
        'title',
        'price_delta_cents',
        'value_percent',
        'is_active',
        'is_default',
        'position',
        'delta_cents',
        'delta_percent',
        'allow_class_value_ids',
        'allow_slot_value_ids',
        'meta', // 👈 важно
    ];

    protected $casts = [
        'is_active'             => 'bool',
        'is_default'            => 'bool',
        'value_percent'         => 'float',
        'delta_cents'           => 'integer',
        'delta_percent'         => 'float',
        'allow_class_value_ids' => 'array',
        'allow_slot_value_ids'  => 'array',
        'meta'                  => 'array', // 👈 важно
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    protected static function booted()
    {
        static::saving(function (OptionValue $value) {
            if ($value->is_default) {
                $group = $value->group()->first();
                if ($group && $group->type === OptionGroup::TYPE_RADIO) {
                    static::where('option_group_id', $value->option_group_id)
                        ->when($value->exists, fn ($q) => $q->where('id', '!=', $value->id))
                        ->update(['is_default' => false]);
                }
            }
        });
    }
}