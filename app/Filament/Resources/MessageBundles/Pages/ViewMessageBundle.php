<?php

namespace App\Filament\Resources\MessageBundles\Pages;

use App\Filament\Resources\MessageBundles\MessageBundleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMessageBundle extends ViewRecord
{
    protected static string $resource = MessageBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
