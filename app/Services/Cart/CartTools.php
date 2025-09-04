<?php

namespace App\Services\Cart;

use App\Models\Cart;

final class CartTools
{
    /** Полностью очищает корзину пользователя (без удаления самой записи Cart). */
    public static function clearUserCart(int $userId): void
    {
        $cart = Cart::firstOrCreate(['user_id' => $userId]);

        // удаляем опции у позиций, затем сами позиции
        foreach ($cart->items()->cursor() as $item) {
            $item->options()->delete();
            $item->delete();
        }
    }
}