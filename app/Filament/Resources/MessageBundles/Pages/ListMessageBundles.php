<?php

namespace App\Filament\Resources\MessageBundles\Pages;

use App\Filament\Resources\MessageBundles\MessageBundleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMessageBundles extends ListRecords
{
    protected static string $resource = MessageBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
