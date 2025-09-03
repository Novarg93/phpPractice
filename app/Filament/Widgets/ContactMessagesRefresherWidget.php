<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ContactMessagesRefresherWidget extends Widget
{
    // ВАЖНО: без "static"
    protected string $view = 'filament.widgets.contact-messages-refresher';

    // Не нужен поллинг — обновляемся только по событиям
    protected ?string $pollingInterval = null;

    // Чтобы виджет не был "ленивым" (монтировался сразу)
    protected static bool $isLazy = false;

    public function ping(): void
    {
        // Попросим страницу списка перерисовать таблицу
        $this->dispatch('refreshTable')
            ->to(\App\Filament\Resources\ContactMessages\Pages\ListContactMessages::class);
    }
}