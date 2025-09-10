<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code','type','value_percent','value_cents','min_order_cents','max_discount_cents',
        'max_uses','per_user_max_uses','uses_count','starts_at','ends_at','is_active',
        'stripe_coupon_id','stripe_coupon_currency',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    // 👉 всегда храним верхним регистром
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    public function computeDiscountCents(int $subtotalCents): int
    {
        $discount = $this->type === 'percent'
            ? (int) round($subtotalCents * (max(0, (int) $this->value_percent) / 100))
            : max(0, (int) $this->value_cents);

        if ($this->max_discount_cents) {
            $discount = min($discount, (int) $this->max_discount_cents);
        }
        return min($discount, $subtotalCents);
    }

    public function isApplicable(?int $userId, int $subtotalCents): array
    {
        if (! $this->is_active) return [false, 'Code is inactive'];
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) return [false, 'Code is not active yet'];
        if ($this->ends_at && $now->gt($this->ends_at))   return [false, 'Code has expired'];
        if ($this->min_order_cents && $subtotalCents < $this->min_order_cents) return [false, 'Order is below the minimum amount'];
        if ($this->max_uses && $this->uses_count >= $this->max_uses) return [false, 'Code usage limit reached'];

        if ($userId && $this->per_user_max_uses) {
            $used = $this->redemptions()->where('user_id', $userId)->count();
            if ($used >= $this->per_user_max_uses) return [false, 'You have already used this code'];
        }

        $discount = $this->computeDiscountCents($subtotalCents);
        if ($discount <= 0) return [false, 'No discount for this cart'];

        return [true, null];
    }
}