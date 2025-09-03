<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\ContactMessagesRefresherWidget::class,
        ];
    }

    #[On('refreshTable')]
    public function refreshTableFromEvent(): void
    {
        // Мagic-экшен Livewire v3 — заставляет компонент страницы перерендериться,
        // а вместе с ним обновится и таблица.
        $this->dispatch('$refresh');
    }
}