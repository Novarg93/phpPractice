<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use App\Services\Cart\CartMerger;

class MergeCartOnLogin
{
    public function __construct(private Request $request, private CartMerger $merger) {}

    public function handle(Login $event): void
    {
        $this->merger->mergeGuestCartIntoUser($this->request, $event->user->id);
    }
}